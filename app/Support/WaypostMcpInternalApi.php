<?php

namespace App\Support;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class WaypostMcpInternalApi
{
    /**
     * Container key: true while handling an API subrequest from MCP tools (not forgeable by HTTP clients).
     */
    public const INTERNAL_DISPATCH_BINDING = 'waypost.mcp_internal_api_dispatch';

    public static function isInternalDispatch(): bool
    {
        return app()->bound(self::INTERNAL_DISPATCH_BINDING)
            && app(self::INTERNAL_DISPATCH_BINDING) === true;
    }

    /**
     * Dispatch a subrequest to this app's `/api` routes using the same Bearer token as the MCP request.
     *
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, mixed>|null  $jsonBody  For POST/PATCH only
     */
    public static function dispatch(string $method, string $path, array $query = [], ?array $jsonBody = null): SymfonyResponse
    {
        $method = strtoupper($method);
        $path = WaypostMcpApiPath::assertSafeRelativeApiPath($path);

        $user = Auth::user();
        if ($user === null) {
            throw new \RuntimeException('Not authenticated.');
        }

        WaypostMcpApiPath::assertUserMayAccessProjectsInApiPath($user, $path);

        $uri = '/api'.$path;
        if ($query !== []) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query($query);
        }

        $server = [
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => $method,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WAYPOST_SOURCE' => 'mcp',
        ];

        $auth = request()->header('Authorization');
        if (is_string($auth) && $auth !== '') {
            $server['HTTP_AUTHORIZATION'] = $auth;
        }

        $content = null;
        if (in_array($method, ['POST', 'PATCH'], true) && $jsonBody !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($jsonBody, JSON_THROW_ON_ERROR);
        }

        $sub = Request::create($uri, $method, [], [], [], $server, $content);

        $kernel = app(Kernel::class);

        app()->instance(self::INTERNAL_DISPATCH_BINDING, true);
        try {
            $response = $kernel->handle($sub);
        } finally {
            app()->forgetInstance(self::INTERNAL_DISPATCH_BINDING);
        }

        // Do not call Kernel::terminate() for nested requests: terminateMiddleware()
        // resolves route middleware by container make(name) and breaks on aliases like token.project.

        return $response;
    }
}
