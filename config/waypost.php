<?php

return [

    /*
    |--------------------------------------------------------------------------
    | waypost.json — default X-Waypost-Source hint
    |--------------------------------------------------------------------------
    |
    | Included in the downloadable manifest so clients send X-Waypost-Source on
    | API calls. Must be a slug in App\Support\WaypostSource::allowedSources().
    |
    */

    'manifest_x_waypost_source' => env('WAYPOST_MANIFEST_X_WAYPOST_SOURCE', 'ai'),

    /*
    |--------------------------------------------------------------------------
    | Extra client / agent labels
    |--------------------------------------------------------------------------
    |
    | Comma-separated slugs (a-z, digits, underscore, hyphen) merged with the
    | built-in list for X-Waypost-Source, agent-events "agent", and changelog.
    |
    */

    'extra_client_sources' => array_values(array_filter(array_map(
        static fn (string $s): string => strtolower(trim($s)),
        explode(',', (string) env('WAYPOST_EXTRA_CLIENT_SOURCES', '')),
    ), static fn (string $s): bool => $s !== '')),

    /*
    |--------------------------------------------------------------------------
    | Public base URL (browser + MCP / waypost.json)
    |--------------------------------------------------------------------------
    |
    | Defaults to APP_URL. Set this when the URL editors and the MCP server must
    | call differs from APP_URL (uncommon). Example: local HTTPS with Valet/Herd
    | — set APP_URL=https://waypost.test (and optionally mirror it here).
    |
    */

    'public_url' => env('WAYPOST_PUBLIC_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | MCP HTTP server (POST /mcp/waypost)
    |--------------------------------------------------------------------------
    |
    | Set WAYPOST_MCP_ENABLED=false to turn off Streamable HTTP MCP while keeping
    | /mcp/waypost/reachable and API routes available for diagnostics.
    |
    */

    'mcp_enabled' => filter_var(
        env('WAYPOST_MCP_ENABLED', '1'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | MCP HTTP request logging
    |--------------------------------------------------------------------------
    |
    | When true, each /mcp/waypost response is logged (no body or secrets) and
    | includes X-Waypost-Mcp-Request-Id for correlation with storage/logs.
    |
    | Defaults to on when APP_ENV=local. Set WAYPOST_MCP_LOG_REQUESTS=false to disable locally,
    | or true in production for support.
    |
    */
    'mcp_log_requests' => filter_var(
        env('WAYPOST_MCP_LOG_REQUESTS', env('APP_ENV') === 'local' ? '1' : '0'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | MCP — Private Network Access (Chromium / Cursor)
    |--------------------------------------------------------------------------
    |
    | When true, MCP responses under mcp/* include Access-Control-Allow-Private-Network
    | so Cursor/Electron can complete CORS preflight to http://*.test and similar local URLs.
    |
    */

    'mcp_allow_private_network_access_header' => filter_var(
        env('WAYPOST_MCP_ALLOW_PRIVATE_NETWORK_ACCESS_HEADER', '1'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | MCP URL scheme (Herd / Valet http→https redirect)
    |--------------------------------------------------------------------------
    |
    | When null (default): if the public base URL is http://*.test, the MCP endpoint URL
    | uses https:// so Cursor preflight hits PHP (nginx often 301s http OPTIONS to HTML).
    | Set WAYPOST_MCP_UPGRADE_HTTP_TO_HTTPS=false to keep http:// for Docker-only HTTP.
    |
    */

    'mcp_upgrade_http_to_https' => ($v = env('WAYPOST_MCP_UPGRADE_HTTP_TO_HTTPS')) === null
        ? null
        : filter_var($v, FILTER_VALIDATE_BOOL),

];
