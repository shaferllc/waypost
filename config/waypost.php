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
    | MCP server — npm package for editors (npx)
    |--------------------------------------------------------------------------
    |
    | When set (e.g. @shaferllc/mcp-server@1.0.0), end-user MCP config uses
    | npx -y <value> — no local copy of mcp/waypost-server. Publish the package
    | to npm first or editors will see npm 404.
    |
    | Default is empty: local workspace mode (npx tsx + cwd under
    | mcp/waypost-server). Users copy that folder, run npm install, open repo root.
    |
    | After publishing, set this on production to match package.json, e.g.
    | WAYPOST_MCP_NPM_PACKAGE=@shaferllc/mcp-server@1.0.0
    |
    */

    'mcp_npm_package' => env('WAYPOST_MCP_NPM_PACKAGE', ''),

];
