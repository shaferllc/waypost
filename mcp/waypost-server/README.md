# Waypost MCP server

Connect [Cursor](https://cursor.com) (or any MCP client) to your Waypost app so the agent can **create and update tasks** (including OKR links, initiative dates, planning status, tags), **add wishlist ideas**, **pin links**, and read an **activity changelog** (what changed via API/MCP).

The server talks to Waypost over the **HTTP API** with your **Sanctum personal access token** and sends `X-Waypost-Source: mcp` so actions are labeled in the changelog.

## Setup

1. **Easiest:** open a project in Waypost and use **Sync with Cursor & this directory** — a **project API token** is created automatically (copy once). Download **`waypost.json`** there for `api_base` and `project_id`. Optionally paste the token into `waypost.json` as `api_token` **locally** (never commit), or set **`WAYPOST_API_TOKEN`** in MCP env.
2. **Alternatively:** **Profile → API tokens** for a token that works on every project you own.
3. From this directory:

   ```bash
   npm install
   npm run build
   ```

4. Note the **absolute path** to `dist/index.js` (e.g. `/Users/you/Projects/waypost/mcp/waypost-server/dist/index.js`).

## Cursor

Open **Cursor Settings → MCP** and add a server (or edit your MCP config JSON).

Example:

```json
{
  "mcpServers": {
    "waypost": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/TO/waypost/mcp/waypost-server/dist/index.js"],
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

Restart Cursor or reload MCP after changes.

## Tools

| Tool | Purpose |
|------|---------|
| `waypost_list_projects` | List projects (ids, names, urls) |
| `waypost_get_project` | Project + roadmap `versions` |
| `waypost_create_task` | New board task (optional theme, assignee, priority, dates, OKR, tags) |
| `waypost_update_task` | PATCH an existing task by id |
| `waypost_create_wishlist_idea` | New wishlist item |
| `waypost_add_project_link` | Pin a URL on the Links tab |
| `waypost_get_changelog` | Recent logged actions (`source` includes `mcp`) |

## Changelog

Creates/updates from the API are recorded when you use the **mutating** endpoints. Entries include `source` (`mcp`, `api`, etc.), `action`, `summary`, and `meta`. Inspect them in-app via **API docs** (`GET /api/changelog`) or ask the agent to call `waypost_get_changelog`.
