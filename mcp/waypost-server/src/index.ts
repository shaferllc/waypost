#!/usr/bin/env node
import { readFileSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

type WaypostManifest = {
  api_base?: string;
  project_id?: number;
  project_name?: string;
  /** Prefer WAYPOST_API_TOKEN in MCP config; never commit this field. */
  api_token?: string;
};

function loadWaypostManifest(): WaypostManifest | null {
  let dir = process.cwd();
  for (let depth = 0; depth < 16; depth++) {
    const candidates = [join(dir, "waypost.json"), join(dir, ".waypost", "config.json")];
    for (const filePath of candidates) {
      if (existsSync(filePath)) {
        try {
          const raw = readFileSync(filePath, "utf8");
          return JSON.parse(raw) as WaypostManifest;
        } catch {
          return null;
        }
      }
    }
    const parent = dirname(dir);
    if (parent === dir) {
      break;
    }
    dir = parent;
  }
  return null;
}

const manifest = loadWaypostManifest();
const baseUrl = (
  process.env.WAYPOST_BASE_URL ??
  manifest?.api_base ??
  "http://127.0.0.1:8000"
).replace(/\/$/, "");

const fromEnv = process.env.WAYPOST_PROJECT_ID;
const fromManifest = manifest?.project_id;
const parsedDefault =
  fromEnv !== undefined && fromEnv !== ""
    ? parseInt(fromEnv, 10)
    : typeof fromManifest === "number" && Number.isFinite(fromManifest)
      ? fromManifest
      : NaN;
const defaultProjectId = Number.isFinite(parsedDefault) ? parsedDefault : undefined;

const token =
  (process.env.WAYPOST_API_TOKEN && process.env.WAYPOST_API_TOKEN !== ""
    ? process.env.WAYPOST_API_TOKEN
    : undefined) ??
  (typeof manifest?.api_token === "string" && manifest.api_token !== "" ? manifest.api_token : undefined);

if (!token) {
  console.error(
    "waypost-mcp: set WAYPOST_API_TOKEN, or add api_token to waypost.json (do not commit). Project tokens are created on each Waypost project; waypost.json carries api_base + project_id.",
  );
  process.exit(1);
}

const MCP_SOURCE = "mcp";

function requireProjectId(explicit: number | undefined): number {
  if (explicit != null && Number.isFinite(explicit)) {
    return explicit;
  }
  if (defaultProjectId != null) {
    return defaultProjectId;
  }
  throw new Error(
    "Missing project_id. Pass it in the tool, download waypost.json from your project in Waypost into this repo root, or set WAYPOST_PROJECT_ID.",
  );
}

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

function toolError(message: string): { content: Array<{ type: "text"; text: string }>; isError: boolean } {
  return { content: [{ type: "text", text: message }], isError: true };
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
  "waypost_workspace_status",
  {
    title: "Waypost MCP workspace status",
    description:
      "Shows resolved API base URL and default project_id from env and/or waypost.json (walks up from cwd). Use after saving waypost.json in the repo root.",
    annotations: readAnnotations,
  },
  async () =>
    textResult({
      api_base: baseUrl,
      default_project_id: defaultProjectId ?? null,
      manifest_project_name: manifest?.project_name ?? null,
    }),
);

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
    description:
      "Load a project with roadmap versions. project_id optional if waypost.json or WAYPOST_PROJECT_ID is set.",
    inputSchema: {
      project_id: z
        .number()
        .int()
        .positive()
        .optional()
        .describe("Project id; defaults from waypost.json / WAYPOST_PROJECT_ID"),
    },
    annotations: readAnnotations,
  },
  async (args) => {
    try {
      const id = requireProjectId(args.project_id);
      return textResult(await waypostFetch(`/projects/${id}`));
    } catch (e) {
      return toolError(e instanceof Error ? e.message : String(e));
    }
  },
);

server.registerTool(
  "waypost_create_task",
  {
    title: "Create a Waypost task",
    description:
      "Adds a task to a project board. project_id optional when waypost.json is in the repo root. Logs to changelog (source=mcp).",
    inputSchema: {
      project_id: z
        .number()
        .int()
        .positive()
        .optional()
        .describe("Defaults from waypost.json / WAYPOST_PROJECT_ID"),
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
    try {
      const projectId = requireProjectId(args.project_id);
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
        await waypostFetch(`/projects/${projectId}/tasks`, {
          method: "POST",
          body: JSON.stringify(payload),
        }),
      );
    } catch (e) {
      return toolError(e instanceof Error ? e.message : String(e));
    }
  },
);

server.registerTool(
  "waypost_create_wishlist_idea",
  {
    title: "Add a Waypost wishlist idea",
    description:
      "Adds an idea to the project wishlist. project_id optional when waypost.json exists. Logged in changelog (source=mcp).",
    inputSchema: {
      project_id: z.number().int().positive().optional(),
      title: z.string().min(1).max(255),
      notes: z.string().max(5000).optional().describe("URL or longer notes"),
    },
    annotations: writeAnnotations,
  },
  async (args) => {
    try {
      const projectId = requireProjectId(args.project_id);
      return textResult(
        await waypostFetch(`/projects/${projectId}/wishlist-items`, {
          method: "POST",
          body: JSON.stringify({ title: args.title, notes: args.notes }),
        }),
      );
    } catch (e) {
      return toolError(e instanceof Error ? e.message : String(e));
    }
  },
);

server.registerTool(
  "waypost_add_project_link",
  {
    title: "Pin a URL on a Waypost project",
    description:
      "Adds a link on the Links tab. project_id optional when waypost.json exists. Title defaults to URL host if omitted.",
    inputSchema: {
      project_id: z.number().int().positive().optional(),
      url: z.string().url().max(2048),
      title: z.string().max(120).optional(),
    },
    annotations: writeAnnotations,
  },
  async (args) => {
    try {
      const projectId = requireProjectId(args.project_id);
      return textResult(
        await waypostFetch(`/projects/${projectId}/links`, {
          method: "POST",
          body: JSON.stringify({ url: args.url, title: args.title }),
        }),
      );
    } catch (e) {
      return toolError(e instanceof Error ? e.message : String(e));
    }
  },
);

server.registerTool(
  "waypost_get_changelog",
  {
    title: "Read Waypost activity changelog",
    description:
      "Recent API/MCP actions (newest first). Omit project_id to include all your projects; pass project_id to filter (e.g. default from waypost_workspace_status).",
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
