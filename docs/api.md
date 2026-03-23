# Waypost HTTP API

In the app (signed in): open **API docs** in the top nav or go to `/docs/api`.

**Cursor / MCP:** see `mcp/waypost-server/README.md` for a local MCP server that creates tasks and reads the changelog.

Personal API for your account: list projects, add **pinned links (URLs)**, **wishlist ideas**, and **tasks** on a board. All routes require a **Sanctum personal access token** (Bearer).

**Local dev login** (`composer setup` or `php artisan db:seed`): `test@example.com` / `password` (see `.env.example`).

Base path: `/api` (e.g. `https://your-domain.test/api/projects`).

## 1. Create an API token

1. Sign in to Waypost in the browser.
2. Open **Profile**.
3. Under **API tokens**, enter a name (e.g. `Bookmarklet`) and click **Create token**.
4. Copy the token immediately; it is only shown once.

Use it on every request:

```http
Authorization: Bearer YOUR_PLAIN_TEXT_TOKEN
Accept: application/json
Content-Type: application/json
```

Optional on **mutating** requests (tasks, wishlist, links): set `X-Waypost-Source` to a short label (`mcp`, `cursor`, `api`, etc.). It is stored on the **changelog** so you can tell Cursor apart from other clients.

API responses are JSON. Unauthenticated requests return `401` with JSON (not an HTML login page).

## 2. Target a project

Projects belong to your user. **`project_id`** in URLs is the numeric primary key from the app (or from the list endpoint below).

### List your projects

`GET /api/projects`

Returns every project you own:

```json
{
  "data": [
    { "id": 1, "name": "My app", "description": "...", "url": "https://example.com" }
  ]
}
```

`url` may be `null` if not set in the app.

Use `data[].id` as `{project}` in the routes below.

### Project detail and roadmap versions (optional)

`GET /api/projects/{project}`

Use this when you need **`version_id`** for tasks tied to a roadmap version:

```json
{
  "data": {
    "id": 1,
    "name": "My app",
    "description": "...",
    "url": "https://example.com",
    "versions": [
      { "id": 4, "name": "v1.0", "target_date": "2026-06-01" }
    ]
  }
}
```

If you are not another user’s collaborator, requests for someone else’s `project` id return **403 Forbidden**.

## 3. Add a project link (URL)

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

## 4. Add a wishlist idea

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

## 5. Create a task

`POST /api/projects/{project}/tasks`

| Field        | Required | Notes |
|--------------|----------|--------|
| `title`      | yes      | max 255 |
| `body`       | no       | max 5000 |
| `status`     | no       | defaults to `todo`. One of: `backlog`, `todo`, `in_progress`, `done` |
| `version_id` | no       | must be a roadmap version that belongs to this project (from `GET /api/projects/{project}`) |

New tasks are appended to the end of the chosen **status** column (same ordering rules as the web UI).

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

## 6. Activity changelog

`GET /api/changelog`

Query params:

- `limit` — optional, 1–100 (default 40)
- `project_id` — optional, only entries for that project

Returns newest-first rows: `source`, `action`, `summary`, `meta` (JSON), `project_id`, `created_at`.

Creating a **task**, **wishlist item**, or **project link** via the API appends a changelog row. Unknown `X-Waypost-Source` values are stored as `api`.

## 7. Quick reference

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/changelog` | Recent activity (tasks, wishlist, links) |
| `GET` | `/api/projects` | List projects (`id`, `name`, `description`, `url`) |
| `GET` | `/api/projects/{project}` | Project + `versions` for `version_id` |
| `POST` | `/api/projects/{project}/links` | Add pinned URL |
| `POST` | `/api/projects/{project}/wishlist-items` | Add wishlist idea |
| `POST` | `/api/projects/{project}/tasks` | Create task |

## 8. CORS and browser extensions

Calls from **another website’s JavaScript** may be blocked by the browser until CORS allows your origin. Typical setups:

- **Server-side** script, **curl**, or **extension background/service worker**: no CORS issue for simple Bearer requests.
- **Bookmarklet or page script** on arbitrary sites: configure Laravel CORS to allow your origins, or proxy through your own backend.

## 9. Revoking access

In **Profile → API tokens**, revoke a token to invalidate it immediately.
