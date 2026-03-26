<?php

namespace App\Mcp\Tools;

use App\Support\WaypostMcpInternalApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_http_request')]
#[Description('Performs GET/POST/PATCH/DELETE against this Waypost app at /api + path using your Bearer token. Path is relative to /api (e.g. /projects/1/tasks). Use the query argument for query parameters; do not put ? in path. Do not pass secrets in query or json_body.')]
class WaypostProxyApiTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:GET,POST,PATCH,DELETE'],
            'path' => ['required', 'string'],
            'query' => ['nullable', 'array'],
            'json_body' => ['nullable', 'array'],
        ]);

        $method = $validated['method'];
        $path = $validated['path'];
        $query = [];
        if (isset($validated['query']) && is_array($validated['query'])) {
            foreach ($validated['query'] as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }
                if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                    $query[$key] = $value;
                }
            }
        }

        $jsonBody = null;
        if (in_array($method, ['POST', 'PATCH'], true) && array_key_exists('json_body', $validated) && $validated['json_body'] !== null) {
            $jsonBody = $validated['json_body'];
        }

        try {
            $symfony = WaypostMcpInternalApi::dispatch($method, $path, $query, $jsonBody);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return Response::error('Failed to call the Waypost API.');
        }

        $raw = $symfony->getContent();
        $status = $symfony->getStatusCode();

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            $raw = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($status >= 400) {
            return Response::error($raw !== '' ? $raw : 'HTTP '.$status);
        }

        return Response::text($raw !== '' ? $raw : 'HTTP '.$status);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'method' => $schema->string()->enum(['GET', 'POST', 'PATCH', 'DELETE'])->required(),
            'path' => $schema->string()->required(),
            'query' => $schema->object()->nullable(),
            'json_body' => $schema->object()->nullable(),
        ];
    }
}
