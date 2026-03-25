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

const taskStatusSchema = z.enum(["backlog", "todo", "in_progress", "in_review", "done"]);

const planningStatusSchema = z.enum(["on_time", "in_progress", "not_started", "behind", "blocked"]);

const taskPrioritySchema = z.union([z.literal(1), z.literal(2), z.literal(3)]);

function jsonBody(payload: Record<string, unknown>): string {
  return JSON.stringify(
    Object.fromEntries(Object.entries(payload).filter(([, v]) => v !== undefined)),
  );
}

/** Path segment under `/api` only; query params go in the tool's `query` object. */
function assertSafeRelativeApiPath(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  if (normalized.includes("..")) {
    throw new Error("path must not contain '..'");
  }
  if (normalized.includes("?") || normalized.includes("#")) {
    throw new Error("do not put ? or # in path; use the query object for query parameters");
  }
  if (normalized.length > 1024) {
    throw new Error("path too long");
  }
  if (!/^\/[a-zA-Z0-9/_.-]+$/.test(normalized)) {
    throw new Error(
      "path must be relative to /api using letters, digits, /, _, ., and - (e.g. /projects/1/tasks)",
    );
  }
  return normalized;
}

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
  if (text.trim() === "") {
    if (!res.ok) {
      throw new Error(`Waypost API HTTP ${res.status}: ${res.statusText || "empty body"}`);
    }
    return null;
  }
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

const httpToolAnnotations = {
  readOnlyHint: false,
  destructiveHint: true,
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
      status: taskStatusSchema.optional().describe("Kanban column; default todo"),
      version_id: z
        .number()
        .int()
        .positive()
        .optional()
        .describe("Roadmap version id from waypost_get_project"),
      theme_id: z.number().int().positive().optional().describe("Roadmap theme id"),
      assigned_to: z.number().int().positive().optional().describe("User id to assign"),
      priority: taskPrioritySchema.optional().describe("1 low, 2 normal, 3 high"),
      due_date: z.string().max(32).optional().describe("ISO date YYYY-MM-DD"),
      starts_at: z.string().max(32).optional().describe("Initiative start YYYY-MM-DD"),
      ends_at: z.string().max(32).optional().describe("Initiative end YYYY-MM-DD"),
      planning_status: planningStatusSchema.optional(),
      okr_objective_id: z.number().int().positive().optional().describe("OKR objective id in this project"),
      tags: z.array(z.string().max(64)).max(50).optional(),
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
        theme_id: args.theme_id,
        assigned_to: args.assigned_to,
        priority: args.priority,
        due_date: args.due_date,
        starts_at: args.starts_at,
        ends_at: args.ends_at,
        planning_status: args.planning_status,
        okr_objective_id: args.okr_objective_id,
        tags: args.tags,
      };
      return textResult(
        await waypostFetch(`/projects/${projectId}/tasks`, {
          method: "POST",
          body: jsonBody(body),
        }),
      );
    } catch (e) {
      return toolError(e instanceof Error ? e.message : String(e));
    }
  },
);

server.registerTool(
  "waypost_update_task",
  {
    title: "Update a Waypost task",
    description:
      "PATCH a task (title, status, OKR link, initiative dates, planning status, tags, etc.). project_id optional when waypost.json is set.",
    inputSchema: {
      project_id: z.number().int().positive().optional(),
      task_id: z.number().int().positive().describe("Task id"),
      title: z.string().min(1).max(255).optional(),
      body: z.string().max(5000).nullable().optional(),
      status: taskStatusSchema.optional(),
      version_id: z.number().int().positive().nullable().optional(),
      theme_id: z.number().int().positive().nullable().optional(),
      assigned_to: z.number().int().positive().nullable().optional(),
      priority: taskPrioritySchema.optional(),
      due_date: z.string().max(32).nullable().optional(),
      starts_at: z.string().max(32).nullable().optional(),
      ends_at: z.string().max(32).nullable().optional(),
      planning_status: planningStatusSchema.nullable().optional(),
      okr_objective_id: z.number().int().positive().nullable().optional(),
      tags: z.array(z.string().max(64)).max(50).nullable().optional(),
    },
    annotations: writeAnnotations,
  },
  async (args) => {
    try {
      const projectId = requireProjectId(args.project_id);
      const { task_id: taskId, project_id: _p, ...rest } = args;
      const body: Record<string, unknown> = { ...rest };
      return textResult(
        await waypostFetch(`/projects/${projectId}/tasks/${taskId}`, {
          method: "PATCH",
          body: jsonBody(body),
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
  "waypost_http_request",
  {
    title: "Call the Waypost HTTP API (full CRUD)",
    description:
      "Performs GET/POST/PATCH/DELETE against your Waypost server at WAYPOST_BASE_URL + /api + path. Uses the same Bearer token as other tools. Use this for any endpoint not covered by a dedicated tool (list tasks, delete tasks, project CRUD, links/wishlist CRUD, roadmap versions/themes, etc.). Path is relative to /api (e.g. /projects/1/tasks). Do not pass secrets in query or json_body.",
    inputSchema: {
      method: z.enum(["GET", "POST", "PATCH", "DELETE"]).describe("HTTP method"),
      path: z
        .string()
        .min(1)
        .describe("URL path under /api, starting with / (e.g. /projects/12/links/3)"),
      query: z
        .record(z.string(), z.union([z.string(), z.number(), z.boolean()]))
        .optional()
        .describe("Optional query string parameters"),
      json_body: z
        .record(z.string(), z.unknown())
        .optional()
        .describe("JSON object body for POST/PATCH (ignored for GET/DELETE)"),
    },
    annotations: httpToolAnnotations,
  },
  async (args) => {
    try {
      const path = assertSafeRelativeApiPath(args.path);
      const params = new URLSearchParams();
      if (args.query) {
        for (const [k, v] of Object.entries(args.query)) {
          params.set(k, String(v));
        }
      }
      const qs = params.toString();
      const pathWithQuery = `${path}${qs ? `?${qs}` : ""}`;
      const init: RequestInit = { method: args.method };
      if (
        (args.method === "POST" || args.method === "PATCH") &&
        args.json_body !== undefined
      ) {
        init.body = JSON.stringify(args.json_body);
      }
      return textResult(await waypostFetch(pathWithQuery, init));
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
