# Waypost MCP server

Connect [Cursor](https://cursor.com) (or any MCP client) to your Waypost app so the agent can **create tasks**, **add wishlist ideas**, **pin links**, and read an **activity changelog** (what changed via API/MCP).

The server talks to Waypost over the **HTTP API** with your **Sanctum personal access token** and sends `X-Waypost-Source: mcp` so actions are labeled in the changelog.

## Setup

1. In Waypost: **Profile → API tokens** → create a token and copy it.
2. From this directory:

   ```bash
   npm install
   npm run build
   ```

3. Note the **absolute path** to `dist/index.js` (e.g. `/Users/you/Projects/waypost/mcp/waypost-server/dist/index.js`).

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
- **`WAYPOST_API_TOKEN`**: The plaintext token shown once when you created it in Profile.

Restart Cursor or reload MCP after changes.

## Tools

| Tool | Purpose |
|------|---------|
| `waypost_list_projects` | List projects (ids, names, urls) |
| `waypost_get_project` | Project + roadmap `versions` |
| `waypost_create_task` | New board task |
| `waypost_create_wishlist_idea` | New wishlist item |
| `waypost_add_project_link` | Pin a URL on the Links tab |
| `waypost_get_changelog` | Recent logged actions (`source` includes `mcp`) |

## Changelog

Creates/updates from the API are recorded when you use the **mutating** endpoints. Entries include `source` (`mcp`, `api`, etc.), `action`, `summary`, and `meta`. Inspect them in-app via **API docs** (`GET /api/changelog`) or ask the agent to call `waypost_get_changelog`.
