<?php

$mcpNpmDefault = '@waypost/mcp-server@1.0.0';
$mcpPkgPath = dirname(__DIR__).'/mcp/waypost-server/package.json';
if (is_readable($mcpPkgPath)) {
    $decoded = json_decode((string) file_get_contents($mcpPkgPath), true);
    if (is_array($decoded)
        && isset($decoded['name'], $decoded['version'])
        && is_string($decoded['name'])
        && is_string($decoded['version'])
        && $decoded['name'] !== ''
        && $decoded['version'] !== ''
    ) {
        $mcpNpmDefault = $decoded['name'].'@'.$decoded['version'];
    }
}

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
    | End-user MCP config uses: npx -y <this value>
    | No local copy of mcp/waypost-server is required once this package is
    | published to npm (npm publish in mcp/waypost-server, scope may need
    | --access public). You are not hosting a separate MCP HTTP service.
    |
    | Default spec is built from mcp/waypost-server/package.json (name@version)
    | so deploys stay aligned when you bump that file. Override with this env
    | if you must pin a different published build.
    |
    | Set to empty string to generate config that runs from a git checkout
    | (cwd + tsx src/index.ts under mcp/waypost-server).
    |
    */

    'mcp_npm_package' => env('WAYPOST_MCP_NPM_PACKAGE', $mcpNpmDefault),

];
