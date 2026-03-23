<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Support\Facades\Http;

class WebhookDispatcher
{
    public function dispatch(Project $project, string $event, array $payload): void
    {
        $hooks = ProjectWebhook::query()
            ->where('project_id', $project->id)
            ->where('active', true)
            ->get();

        foreach ($hooks as $hook) {
            if (! $this->hookSubscribesTo($hook, $event)) {
                continue;
            }
            $body = json_encode([
                'event' => $event,
                'project_id' => $project->id,
                'data' => $payload,
            ], JSON_THROW_ON_ERROR);
            $sig = hash_hmac('sha256', $body, $hook->secret);
            try {
                Http::timeout(5)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Waypost-Event' => $event,
                        'X-Waypost-Signature' => $sig,
                    ])
                    ->withBody($body, 'application/json')
                    ->post($hook->url);
            } catch (\Throwable) {
                //
            }
        }
    }

    private function hookSubscribesTo(ProjectWebhook $hook, string $event): bool
    {
        $events = $hook->events;
        if ($events === null || $events === []) {
            return true;
        }

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
