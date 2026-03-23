#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const baseUrl = (process.env.WAYPOST_BASE_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const token = process.env.WAYPOST_API_TOKEN;

if (!token) {
  console.error("waypost-mcp: set WAYPOST_API_TOKEN (Sanctum personal access token from Profile → API tokens).");
  process.exit(1);
}

const MCP_SOURCE = "mcp";

async function waypostFetch(path: string, init: RequestInit = {}): Promise<unknown> {
  const url = `${baseUrl}/api${path.startsWith("/") ? path : `/${path}`}`;
  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
    "X-Waypost-Source": MCP_SOURCE,
    ...(init.headers as Record<string, string> | undefined),
  };
  if (init.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }
  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  let parsed: unknown;
  try {
    parsed = JSON.parse(text) as unknown;
  } catch {
    parsed = { _raw: text };
  }
  if (!res.ok) {
    const msg =
      typeof parsed === "object" && parsed !== null && "message" in parsed
        ? String((parsed as { message: unknown }).message)
        : text.slice(0, 800);
    throw new Error(`Waypost API HTTP ${res.status}: ${msg}`);
  }
  return parsed;
}

function textResult(data: unknown): { content: Array<{ type: "text"; text: string }> } {
  return {
    content: [{ type: "text", text: typeof data === "string" ? data : JSON.stringify(data, null, 2) }],
  };
}

const readAnnotations = {
  readOnlyHint: true,
  destructiveHint: false,
  idempotentHint: true,
  openWorldHint: true,
} as const;

const writeAnnotations = {
  readOnlyHint: false,
  destructiveHint: false,
  idempotentHint: false,
  openWorldHint: true,
} as const;

const server = new McpServer({
  name: "waypost",
  version: "1.0.0",
});

server.registerTool(
  "waypost_list_projects",
  {
    title: "List Waypost projects",
    description:
      "Returns all projects for the token owner (id, name, description, url). Use ids with other Waypost tools.",
    annotations: readAnnotations,
  },
  async () => textResult(await waypostFetch("/projects")),
);

server.registerTool(
  "waypost_get_project",
  {
    title: "Get one Waypost project",
    description: "Load a project with roadmap versions (for version_id when creating tasks).",
    inputSchema: {
      project_id: z.number().int().positive().describe("Project id from waypost_list_projects"),
    },
    annotations: readAnnotations,
  },
  async (args) => textResult(await waypostFetch(`/projects/${args.project_id}`)),
);

server.registerTool(
  "waypost_create_task",
  {
    title: "Create a Waypost task",
    description:
      "Adds a task to a project board. Default status is todo. Logs to Waypost changelog with source=mcp.",
    inputSchema: {
      project_id: z.number().int().positive().describe("Project id"),
      title: z.string().min(1).max(255).describe("Task title"),
      body: z.string().max(5000).optional().describe("Optional description / notes"),
      status: z
        .enum(["backlog", "todo", "in_progress", "done"])
        .optional()
        .describe("Kanban column; default todo"),
      version_id: z
        .number()
        .int()
        .positive()
        .optional()
        .describe("Roadmap version id from waypost_get_project"),
    },
    annotations: writeAnnotations,
  },
  async (args) => {
    const body: Record<string, unknown> = {
      title: args.title,
      body: args.body,
      status: args.status,
      version_id: args.version_id,
    };
    const payload = Object.fromEntries(
      Object.entries(body).filter(([, v]) => v !== undefined && v !== null),
    );
    return textResult(
      await waypostFetch(`/projects/${args.project_id}/tasks`, {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    );
  },
);

server.registerTool(
  "waypost_create_wishlist_idea",
  {
    title: "Add a Waypost wishlist idea",
    description: "Adds an idea to the project wishlist. Logged in changelog (source=mcp).",
    inputSchema: {
      project_id: z.number().int().positive(),
      title: z.string().min(1).max(255),
      notes: z.string().max(5000).optional().describe("URL or longer notes"),
    },
    annotations: writeAnnotations,
  },
  async (args) =>
    textResult(
      await waypostFetch(`/projects/${args.project_id}/wishlist-items`, {
        method: "POST",
        body: JSON.stringify({ title: args.title, notes: args.notes }),
      }),
    ),
);

server.registerTool(
  "waypost_add_project_link",
  {
    title: "Pin a URL on a Waypost project",
    description: "Adds a link row on the Links tab. Title defaults to URL host if omitted.",
    inputSchema: {
      project_id: z.number().int().positive(),
      url: z.string().url().max(2048),
      title: z.string().max(120).optional(),
    },
    annotations: writeAnnotations,
  },
  async (args) =>
    textResult(
      await waypostFetch(`/projects/${args.project_id}/links`, {
        method: "POST",
        body: JSON.stringify({ url: args.url, title: args.title }),
      }),
    ),
);

server.registerTool(
  "waypost_get_changelog",
  {
    title: "Read Waypost activity changelog",
    description:
      "Recent API/MCP actions for your account (tasks, wishlist, links). Newest first. Use to see what changed from Cursor or other clients.",
    inputSchema: {
      limit: z.number().int().min(1).max(100).optional().describe("Max rows (default 40)"),
      project_id: z.number().int().positive().optional().describe("Filter to one project"),
    },
    annotations: readAnnotations,
  },
  async (args) => {
    const params = new URLSearchParams();
    if (args.limit !== undefined) {
      params.set("limit", String(args.limit));
    }
    if (args.project_id !== undefined) {
      params.set("project_id", String(args.project_id));
    }
    const q = params.toString();
    return textResult(await waypostFetch(`/changelog${q ? `?${q}` : ""}`));
  },
);

const transport = new StdioServerTransport();
await server.connect(transport);
