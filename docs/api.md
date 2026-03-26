# Waypost HTTP API

In the app (signed in): open **API docs** in the top nav or go to `/docs/api`.

**Cursor / MCP:** Waypost exposes MCP over **HTTPS** at **`{public_base_url}/mcp/waypost`** (see **`mcp_url`** in downloadable **waypost.json**). Point your editor’s remote MCP config at that URL with **`Authorization: Bearer`** and your project token (e.g. set **`WAYPOST_API_TOKEN`** in the environment and reference it in MCP headers). Generated config also sets **`Accept: application/json, text/event-stream`** and **`MCP-Protocol-Version`** so Streamable HTTP clients negotiate correctly; the app normalizes **`Accept`** on this route so unauthenticated calls return **401 JSON** instead of an HTML login redirect. If the editor logs an **SSE fallback** with **405** on **GET**, that is normal for this server (optional GET stream is not enabled); fix any **POST** errors first (token, **`APP_URL` / `WAYPOST_PUBLIC_URL`**, HTTPS). The **`waypost_http_request`** tool performs **GET/POST/PATCH/DELETE** against **`/api/...`** using the same Bearer token; direct API calls from other clients still use **`WAYPOST_BASE_URL`** (or **`api_base`** in **waypost.json**) plus **`X-Waypost-Source`** on mutating requests (**`waypost.json`** → **`x_waypost_source`**, default **`ai`**).

Personal API for your account: **full CRUD** on **projects**, **roadmap versions**, **roadmap themes**, **pinned links**, **wishlist ideas**, and **tasks** (including **OKR links**, **initiative dates**, and **planning status**). All routes require a **Sanctum personal access token** (Bearer).

The **browser UI** can refresh in near real time when **Laravel Reverb** is configured (`BROADCAST_CONNECTION=reverb`, `composer run reverb` or `php artisan reverb:start`, and `VITE_REVERB_*` in `.env`). The API itself stays HTTP; Reverb only pushes lightweight `project.{id}` events to open project pages. See **`docs/reverb-production.md`** for a second-terminal dev workflow and production notes.

**Local dev login** (`composer setup` or `php artisan db:seed`): `test@example.com` / `password` (see `.env.example`).

Base path: `/api` (e.g. `https://your-domain.test/api/projects`).

## 1. Create an API token

### Project token (Cursor / MCP)

Each project can have a **scoped** token created automatically when you create the project or first open it (if you can edit the project). Copy it from the **Sync with Cursor & this directory** panel on the project page, or from the banner right after **Create project**. That token only works for **that** project’s API routes and changelog. Rotate it from the same panel if needed.

Download **`waypost.json`** from the project page for `api_base`, **`mcp_url`**, `project_id`, and **`x_waypost_source`** (default `ai` — send that value as **`X-Waypost-Source`** on mutating API calls so edits appear in the project **Recent activity** and changelog). You can paste the token into MCP as `WAYPOST_API_TOKEN`, or add an `api_token` field to `waypost.json` **locally** — do **not** commit secrets. On the server, override the default label with **`WAYPOST_MANIFEST_X_WAYPOST_SOURCE`** in `.env` if needed.

In the web UI, **Download Cursor setup (ZIP)** (signed-in project page) bundles `waypost.json`, **`.cursor/rules/waypost-agent-activity.mdc`**, and a README — extract to your repo root, then add the token and merge **Copy MCP config** into Cursor’s MCP settings.

### General token (Profile)

1. Sign in to Waypost in the browser.
2. Open **Profile**.
3. Under **API tokens**, enter a name (e.g. `Bookmarklet`) and click **Create token**.
4. Copy the token immediately; it is only shown once.

Profile tokens are **not** limited to one project (useful for bookmarklets or multi-project clients).

Use it on every request:

```http
Authorization: Bearer YOUR_PLAIN_TEXT_TOKEN
Accept: application/json
Content-Type: application/json
```

Optional on **mutating** requests: set **`X-Waypost-Source`** to a slug that identifies the client (`cursor`, `github_copilot`, `windsurf`, `claude_code`, `ai`, `mcp`, `api`, `web`, … — see **`supported_agent_types`** in downloadable **waypost.json**, or add custom slugs with **`WAYPOST_EXTRA_CLIENT_SOURCES`**). It is stored on the **changelog** and, for API requests, in project **activity** as `client_source`. Unknown slugs are stored as **`api`**.

API responses are JSON. Unauthenticated requests return `401` with JSON (not an HTML login page).

## 2. Target a project

Projects belong to your user. **`project_id`** in URLs is the numeric primary key from the app (or from the list endpoint below).

### List your projects

`GET /api/projects`

With a **project-scoped** token, returns at most that one project (if you still have access). With a normal Profile token, returns every project you own:

```json
{
  "data": [
    { "id": 1, "name": "My app", "description": "...", "url": "https://example.com" }
  ]
}
```

`url` may be `null` if not set in the app.

Use `data[].id` as `{project}` in the routes below.

### Project detail, themes, and roadmap versions

`GET /api/projects/{project}`

Use this when you need **`version_id`** or **`theme_id`** for tasks:

```json
{
  "data": {
    "id": 1,
    "name": "My app",
    "description": "...",
    "url": "https://example.com",
    "themes": [
      { "id": 2, "name": "Platform", "color": "#3b82f6", "sort_order": 1 }
    ],
    "versions": [
      { "id": 4, "name": "v1.0", "target_date": "2026-06-01" }
    ]
  }
}
```

If you are not another user’s collaborator, requests for someone else’s `project` id return **403 Forbidden**.

### Create, update, and delete a project

`POST /api/projects` — JSON: `name` (required), optional `description`, `url`. Returns `201` with `data`. **Project-scoped tokens cannot create projects** (403, message: `Project-scoped tokens cannot create projects.`).

`PATCH /api/projects/{project}` — optional `name`, `description`, `url`, `archived_at` (nullable date).

`DELETE /api/projects/{project}` — `204 No Content`.

## 3. Project links (URLs)

Pins a URL on the project’s **Links** tab (same as the web UI).

`POST /api/projects/{project}/links`

| Field   | Required | Notes |
|---------|----------|--------|
| `url`   | yes      | valid URL, max 2048 chars |
| `title` | no       | max 120; if omitted, the URL **host** is used (e.g. `github.com`), or `Link` if missing |

```bash
curl -sS -X POST "https://your-domain.test/api/projects/1/links" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://github.com/org/repo","title":"Main repo"}'
```

`GET /api/projects/{project}/links` — list pinned links.

`PATCH /api/projects/{project}/links/{link}` — optional `url`, `title`.

`DELETE /api/projects/{project}/links/{link}` — `204 No Content`.

## 4. Wishlist ideas

`POST /api/projects/{project}/wishlist-items`

| Field   | Required | Notes        |
|---------|----------|--------------|
| `title` | yes      | max 255 chars |
| `notes` | no       | URL or text, max 5000 |

Example:

```bash
curl -sS -X POST "https://your-domain.test/api/projects/1/wishlist-items" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title":"Read later","notes":"https://example.com/article"}'
```

`GET /api/projects/{project}/wishlist-items` — list items.

`PATCH /api/projects/{project}/wishlist-items/{wishlist_item}` — optional `title`, `notes`, `sort_order`.

`DELETE /api/projects/{project}/wishlist-items/{wishlist_item}` — `204 No Content`.

## 5. Tasks

### List and get

`GET /api/projects/{project}/tasks` — all tasks for the project (task payload per row).

`GET /api/projects/{project}/tasks/{task}` — one task; if the task belongs to another project, **404**.

### Create a task

`POST /api/projects/{project}/tasks`

| Field                 | Required | Notes |
|-----------------------|----------|--------|
| `title`               | yes      | max 255 |
| `body`                | no       | max 5000 |
| `status`              | no       | defaults to `todo`. One of: `backlog`, `todo`, `in_progress`, `in_review`, `done` |
| `version_id`          | no       | roadmap version id for this project (from `GET /api/projects/{project}`) |
| `theme_id`            | no       | roadmap theme id for this project |
| `assigned_to`         | no       | user id (project owner or member) |
| `priority`            | no       | `1` (low), `2` (normal), `3` (high); default `2` |
| `due_date`            | no       | `Y-m-d` |
| `starts_at`           | no       | initiative start `Y-m-d` (roadmap / timeline) |
| `ends_at`             | no       | initiative end `Y-m-d` (must be on or after `starts_at` when both sent) |
| `planning_status`     | no       | one of: `on_time`, `in_progress`, `not_started`, `behind`, `blocked` |
| `okr_objective_id`    | no       | must be an objective whose parent goal belongs to this project |
| `tags`                | no       | array of strings (max 64 chars each) |

New tasks are appended to the end of the chosen **status** column (same ordering rules as the web UI).

**Response `data`** includes the fields above (with `null` where unset), plus `id`, `project_id`, `position`, and `created_at` (ISO 8601).

Minimal example (lands in **To do**):

```bash
curl -sS -X POST "https://your-domain.test/api/projects/1/tasks" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title":"Fix login redirect"}'
```

With description, column, and roadmap version:

```bash
curl -sS -X POST "https://your-domain.test/api/projects/1/tasks" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "title":"Ship API docs",
    "body":"Publish docs/api.md and verify curl examples.",
    "status":"in_progress",
    "version_id":4
  }'
```

### 5b. Update a task

`PATCH /api/projects/{project}/tasks/{task}`

Send only fields you want to change (all optional except you must send at least one valid field per request). Same validation rules as create for each key.

Example — set planning fields and OKR link:

```bash
curl -sS -X PATCH "https://your-domain.test/api/projects/1/tasks/12" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "okr_objective_id": 3,
    "starts_at": "2026-03-01",
    "ends_at": "2026-04-15",
    "planning_status": "in_progress"
  }'
```

**Response `data`** includes: `id`, `project_id`, `version_id`, `theme_id`, `assigned_to`, `title`, `body`, `status`, `position`, `priority`, `due_date`, `starts_at`, `ends_at`, `planning_status`, `okr_objective_id`, `tags`, `updated_at`.

### 5c. Delete a task

`DELETE /api/projects/{project}/tasks/{task}` — returns `204 No Content`.

## 6. Roadmap versions

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/projects/{project}/versions` | List versions |
| `POST` | `/api/projects/{project}/versions` | Create (`name` required; optional `description`, `target_date`, `released_at`, `release_notes`, `sort_order`) |
| `PATCH` | `/api/projects/{project}/versions/{version}` | Update |
| `DELETE` | `/api/projects/{project}/versions/{version}` | Delete (`204`) |

## 7. Roadmap themes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/projects/{project}/themes` | List themes |
| `POST` | `/api/projects/{project}/themes` | Create (`name` required; optional `color`, `sort_order`) |
| `PATCH` | `/api/projects/{project}/themes/{theme}` | Update |
| `DELETE` | `/api/projects/{project}/themes/{theme}` | Delete (`204`) |

## 8. Activity changelog

`GET /api/changelog`

Query params:

- `limit` — optional, 1–100 (default 40)
- `project_id` — optional, only entries for that project

Returns newest-first rows: `source`, `action`, `summary`, `meta` (JSON), `project_id`, `created_at`.

Creating or updating **projects**, **tasks**, **wishlist items**, **links**, **roadmap versions**, or **roadmap themes** via the API can append changelog rows.

### AI assist start / end (monitoring)

`POST /api/projects/{project}/agent-events`

| Field | Required | Notes |
|-------|----------|--------|
| `phase` | yes | `start` (assist began) or `end` (assist finished for this turn) |
| `agent` | no | Which assistant/client (same slug set as `X-Waypost-Source` / `supported_agent_types`). If omitted, inferred from **`X-Waypost-Source`**, else **`api`** |
| `session_ref` | no | Reuse the same value on start/end to correlate pairs in `meta` |
| `note` | no | Short label (e.g. user goal), max 500 chars |

Response `201` with `{ "data": { "phase": "…", "agent": "…", "recorded": true } }`. Creates changelog actions **`agent.started`** / **`agent.ended`** (summary includes the agent) and matching **project activity** rows with **`agent`** and **`client_source`** (from the header) when called via the API.

In the web app, use **Download Cursor rule (AI start/end)** on the project **Sync with Cursor** panel to save **`.cursor/rules/waypost-agent-activity.mdc`**. With Waypost MCP, call **`waypost_log_agent_phase`**.

## 9. Quick reference

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/changelog` | Recent activity |
| `GET` | `/api/projects` | List projects |
| `POST` | `/api/projects` | Create project (not with scoped token) |
| `GET` | `/api/projects/{project}` | Project + `themes` + `versions` |
| `PATCH` | `/api/projects/{project}` | Update project |
| `DELETE` | `/api/projects/{project}` | Delete project |
| `GET` | `/api/projects/{project}/links` | List links |
| `POST` | `/api/projects/{project}/links` | Add link |
| `PATCH` | `/api/projects/{project}/links/{link}` | Update link |
| `DELETE` | `/api/projects/{project}/links/{link}` | Delete link |
| `GET` | `/api/projects/{project}/wishlist-items` | List wishlist |
| `POST` | `/api/projects/{project}/wishlist-items` | Add wishlist item |
| `PATCH` | `/api/projects/{project}/wishlist-items/{wishlist_item}` | Update wishlist item |
| `DELETE` | `/api/projects/{project}/wishlist-items/{wishlist_item}` | Delete wishlist item |
| `GET` | `/api/projects/{project}/tasks` | List tasks |
| `GET` | `/api/projects/{project}/tasks/{task}` | Get one task |
| `POST` | `/api/projects/{project}/tasks` | Create task |
| `PATCH` | `/api/projects/{project}/tasks/{task}` | Update task |
| `DELETE` | `/api/projects/{project}/tasks/{task}` | Delete task |
| `GET` | `/api/projects/{project}/versions` | List roadmap versions |
| `POST` | `/api/projects/{project}/versions` | Create version |
| `PATCH` | `/api/projects/{project}/versions/{version}` | Update version |
| `DELETE` | `/api/projects/{project}/versions/{version}` | Delete version |
| `GET` | `/api/projects/{project}/themes` | List roadmap themes |
| `POST` | `/api/projects/{project}/themes` | Create theme |
| `PATCH` | `/api/projects/{project}/themes/{theme}` | Update theme |
| `DELETE` | `/api/projects/{project}/themes/{theme}` | Delete theme |
| `POST` | `/api/projects/{project}/agent-events` | Log AI assist **start** / **end** (`phase`, optional `session_ref`, `note`) |

## 10. CORS and browser extensions

Calls from **another website’s JavaScript** may be blocked by the browser until CORS allows your origin. Typical setups:

- **Server-side** script, **curl**, or **extension background/service worker**: no CORS issue for simple Bearer requests.
- **Bookmarklet or page script** on arbitrary sites: configure Laravel CORS to allow your origins, or proxy through your own backend.

## 11. Revoking access

In **Profile → API tokens**, revoke a token to invalidate it immediately.
