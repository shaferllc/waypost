# Waypost MCP server

Connect [Cursor](https://cursor.com) (or any MCP client) to your Waypost app so the agent can **create and update tasks** (including OKR links, initiative dates, planning status, tags), **add wishlist ideas**, **pin links**, read an **activity changelog**, and call **any JSON endpoint** on your server.

The server always talks to Waypost over **HTTP** (`WAYPOST_BASE_URL` + `/api/...`) using your **Sanctum personal access token**. It sends **`X-Waypost-Source`** on mutating requests: from **`WAYPOST_X_WAYPOST_SOURCE`** env, else **`x_waypost_source`** in `waypost.json` (included when you download it from the project — default **`ai`**), else **`mcp`**. That label appears in the API changelog and in the project’s **Recent activity** (`client_source`). There is no separate local data store.

## Setup

1. **Easiest:** open a project in Waypost → **Sync** tab. Download the setup ZIP (`waypost.json`, **`.cursor/rules/…`**, README) into your **repo root**. Use **Install in … (MCP)** or **Copy MCP config** — with **`@waypost/mcp-server` published to npm**, the config is **`npx -y @waypost/mcp-server@…`** (no local copy of this folder, no Waypost-hosted MCP process — the server still talks to your existing Waypost **HTTPS API**). A **project API token** is created when you open Sync (copy once); paste into `waypost.json` as `api_token` **locally** (never commit) or set **`WAYPOST_API_TOKEN`** in MCP env. If your Waypost instance sets **`WAYPOST_MCP_NPM_PACKAGE=`** (empty), copy **`mcp/waypost-server`** into the repo, run **`npm install`**, and open the repo root so **`cwd`** + **`tsx`** paths work.
2. **Alternatively:** **Profile → API tokens** for a token that works on every project you own.
3. From this directory:

   ```bash
   npm install
   ```

   Optional (only if you want `dist/` and `npm start`): `npm run build`

4. **Publish to npm** (maintainers): bump **`version`** in this `package.json`, commit, then either:
   - **CI:** tag `mcp-server-vX.Y.Z` (must match `version`) and push — GitHub Actions **Publish @waypost/mcp-server** runs `npm publish --access public` (set repo secret **`NPM_TOKEN`**), or  
   - **Manual:** `npm run build` && `npm publish --access public` from this directory.  
   The Laravel app defaults **`WAYPOST_MCP_NPM_PACKAGE`** from this file on deploy (no duplicate version in `.env` unless you override).

## Cursor

Open **Cursor Settings → MCP** and add a server (or edit your MCP config JSON).

Example:

```json
{
  "mcpServers": {
    "waypost": {
      "command": "npx",
      "args": ["-y", "@waypost/mcp-server@1.0.0"],
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
