<?php

namespace App\Observers;

use App\Events\ProjectDataUpdated;
use App\Models\WishlistItem;
use App\Services\ProjectActivityRecorder;

class WishlistItemObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(WishlistItem $item): void
    {
        broadcast(new ProjectDataUpdated($item->project_id));
        $this->maybeRecord($item, 'wishlist_item.created', [
            'title' => $item->title,
        ]);
    }

    public function deleted(WishlistItem $item): void
    {
        broadcast(new ProjectDataUpdated($item->project_id));
        $this->maybeRecord($item, 'wishlist_item.deleted', [
            'title' => $item->title,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function maybeRecord(WishlistItem $item, string $action, array $properties): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->activity->record(auth()->user(), $item->project_id, $action, 'wishlist_item', $item->id, $properties);
    }
}
