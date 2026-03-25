<?php

namespace App\Support;

final class WaypostSource
{
    /** @var list<string> */
    private const CORE = ['api', 'mcp', 'web', 'cursor', 'extension', 'ai'];

    /**
     * Built-in agent / client labels for X-Waypost-Source, agent-events, and changelog.
     * Add more via config waypost.extra_client_sources or WAYPOST_EXTRA_CLIENT_SOURCES.
     *
     * @var list<string>
     */
    private const BUILTIN_AGENTS = [
        'amp',
        'amazon_q',
        'antigravity',
        'aider',
        'claude_code',
        'cline',
        'codex',
        'codex_cli',
        'continue',
        'copilot',
        'cody',
        'crush',
        'devin',
        'droid',
        'gemini',
        'github_copilot',
        'jetbrains',
        'kilocode',
        'opencode',
        'phpstorm',
        'roo',
        'sourcegraph_cody',
        'tabnine',
        'trae',
        'void',
        'vscode',
        'warp',
        'windsurf',
        'zed',
    ];

    /**
     * @return list<string>
     */
    public static function allowedSources(): array
    {
        $extras = config('waypost.extra_client_sources', []);
        if (! is_array($extras)) {
            $extras = [];
        }

        $merged = array_merge(self::CORE, self::BUILTIN_AGENTS, $extras);
        $seen = [];
        foreach ($merged as $item) {
            if (! is_string($item)) {
                continue;
            }
            $s = strtolower(trim($item));
            if ($s === '' || ! self::isValidSlug($s)) {
                continue;
            }
            $seen[$s] = true;
        }

        $list = array_keys($seen);
        sort($list);

        return array_values($list);
    }

    /**
     * Labels suggested for waypost.json / agent-events (drops generic transport labels).
     *
     * @return list<string>
     */
    public static function suggestedAgentTypes(): array
    {
        $drop = ['api', 'web', 'mcp', 'extension'];

        return array_values(array_filter(
            self::allowedSources(),
            static fn (string $s): bool => ! in_array($s, $drop, true),
        ));
    }

    /**
     * Normalize an X-Waypost-Source header (or pre-set label) for storage and display.
     */
    public static function normalize(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return 'api';
        }

        return in_array($normalized, self::allowedSources(), true) ? $normalized : 'api';
    }

    private static function isValidSlug(string $s): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $s);
    }
}
