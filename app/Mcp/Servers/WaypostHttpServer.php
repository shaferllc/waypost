<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\WaypostCreateProjectTool;
use App\Mcp\Tools\WaypostCreateTaskTool;
use App\Mcp\Tools\WaypostGetProjectTool;
use App\Mcp\Tools\WaypostListProjectsTool;
use App\Mcp\Tools\WaypostListTasksTool;
use App\Mcp\Tools\WaypostLogAgentPhaseTool;
use App\Mcp\Tools\WaypostProxyApiTool;
use App\Mcp\Tools\WaypostSetActiveProjectTool;
use App\Mcp\Tools\WaypostUpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('waypost')]
#[Version('1.0.0')]
class WaypostHttpServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
Waypost MCP connects this assistant to your Waypost account over HTTPS (no local npm server).

**Tokens:** A **Profile API token** (Profile → API tokens) can list and create projects via any client. A **project Sync token** is scoped for direct HTTP API calls; **MCP internal** calls can touch any project your user can **view** (create project, then tasks on the new id without swapping tokens).

**Default project (per API token):** `waypost_create_project` sets the MCP default to the new project when **`scope_followup_to_new_project`** is true (default). Then **`project_id` may be omitted** on `waypost_get_project`, `waypost_list_tasks`, `waypost_create_task`, `waypost_update_task`, `waypost_log_agent_phase`. Use **`waypost_set_active_project`** to switch. Persisted in cache keyed by token id (not by chat).

**Convenience tools:** `waypost_list_projects`, `waypost_get_project`, `waypost_create_project` (optional **`issue_sync_token: true`** returns `sync_token`, `waypost_json`, `cursor_mcp_install_url`), `waypost_set_active_project`, `waypost_list_tasks`, `waypost_create_task`, `waypost_update_task`, `waypost_log_agent_phase`.

**Low-level:** `waypost_http_request` — raw GET/POST/PATCH/DELETE under `/api` with `path` (e.g. `/projects/12/tasks`), optional `query`, optional `json_body`.

Set `WAYPOST_API_TOKEN` in the editor environment for MCP Authorization; do not commit tokens.

**Monorepo / multiple products:** Prefer **`waypost_set_active_project`** or create-with-followup so omitted `project_id` targets the right backlog. For long-lived multi-repo setups, a **Profile API token** avoids per-repo token churn.
MARKDOWN;

    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        WaypostListProjectsTool::class,
        WaypostGetProjectTool::class,
        WaypostCreateProjectTool::class,
        WaypostSetActiveProjectTool::class,
        WaypostListTasksTool::class,
        WaypostCreateTaskTool::class,
        WaypostUpdateTaskTool::class,
        WaypostLogAgentPhaseTool::class,
        WaypostProxyApiTool::class,
    ];
}
