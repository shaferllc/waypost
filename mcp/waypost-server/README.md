# Waypost MCP server

Connect [Cursor](https://cursor.com) (or any MCP client) to your Waypost app so the agent can **create and update tasks** (including OKR links, initiative dates, planning status, tags), **add wishlist ideas**, **pin links**, read an **activity changelog**, and call **any JSON endpoint** on your server.

The server always talks to Waypost over **HTTP** (`WAYPOST_BASE_URL` + `/api/...`) using your **Sanctum personal access token**. It sends **`X-Waypost-Source`** on mutating requests: from **`WAYPOST_X_WAYPOST_SOURCE`** env, else **`x_waypost_source`** in `waypost.json` (included when you download it from the project — default **`ai`**), else **`mcp`**. That label appears in the API changelog and in the project’s **Recent activity** (`client_source`). There is no separate local data store.

## Setup

1. **Easiest:** open a project in Waypost → **Sync** tab. Download the setup ZIP (`waypost.json`, **`.cursor/rules/…`**, README) into your **repo root**. Copy this **`mcp/waypost-server`** folder from the Waypost codebase into the same repo root, then `npm install` and `npm run build` here. Use **Install in … (MCP)** or **Copy MCP config** — defaults use **`${workspaceFolder}/mcp/waypost-server/dist/index.js`**, so open your **repository root** as the editor workspace (or set `args` to an **absolute** path to `dist/index.js` if you use user-level MCP only). A **project API token** is created when you open Sync (copy once); paste into `waypost.json` as `api_token` **locally** (never commit) or set **`WAYPOST_API_TOKEN`** in MCP env.
2. **Alternatively:** **Profile → API tokens** for a token that works on every project you own.
3. From this directory:

   ```bash
   npm install
   npm run build
   ```

4. Prefer **`${workspaceFolder}/mcp/waypost-server/dist/index.js`** in MCP `args` when your workspace is the repo root; otherwise note an **absolute path** to `dist/index.js`.

## Cursor

Open **Cursor Settings → MCP** and add a server (or edit your MCP config JSON).

Example:

```json
{
  "mcpServers": {
    "waypost": {
      "command": "node",
      "args": ["${workspaceFolder}/mcp/waypost-server/dist/index.js"],
      "env": {
        "WAYPOST_BASE_URL": "http://127.0.0.1:8000",
        "WAYPOST_API_TOKEN": "paste-your-token-here"
      }
    }
  }
}
```

- **`WAYPOST_BASE_URL`**: Your app URL **without** a trailing slash (same as `APP_URL`).
- **`WAYPOST_API_TOKEN`**: Plaintext Sanctum token (project token from the project page, or a Profile token). If omitted, the server can read **`api_token`** from `waypost.json` (still do not commit that file with secrets).
- **`WAYPOST_X_WAYPOST_SOURCE`**: Optional override for the `X-Waypost-Source` header (e.g. `ai`, `mcp`, `cursor`). Overrides `x_waypost_source` in `waypost.json`.

Restart Cursor or reload MCP after changes.

## Tools

| Tool | Purpose |
|------|---------|
| `waypost_workspace_status` | Resolved `WAYPOST_BASE_URL`, default `project_id`, and effective `X-Waypost-Source` |
| `waypost_log_agent_phase` | Log **AI assist start/end** (`phase`: `start` \| `end`) for monitoring — same as **Download Cursor rule** in the app |
| `waypost_http_request` | **GET/POST/PATCH/DELETE** any path under `/api` (full CRUD: projects, tasks list/delete, links/wishlist CRUD, roadmap versions/themes, etc.) |
| `waypost_list_projects` | List projects (ids, names, urls) |
| `waypost_get_project` | Project + roadmap `themes` and `versions` |
| `waypost_create_task` | New board task (optional theme, assignee, priority, dates, OKR, tags) |
| `waypost_update_task` | PATCH an existing task by id |
| `waypost_create_wishlist_idea` | New wishlist item |
| `waypost_add_project_link` | Pin a URL on the Links tab |
| `waypost_get_changelog` | Recent logged actions (`source` includes `mcp`) |

## Changelog

Creates/updates from the API are recorded when you use the **mutating** endpoints. Entries include `source` (`mcp`, `api`, etc.), `action`, `summary`, and `meta`. Inspect them in-app via **API docs** (`GET /api/changelog`) or ask the agent to call `waypost_get_changelog`.
