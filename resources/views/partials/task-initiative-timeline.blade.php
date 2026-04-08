@props([
    'tasks',
    'interactive' => false,
    'emptyHint' => null,
])

@php
    use App\Models\TaskLink;

    /** @var \Illuminate\Support\Collection<int, \App\Models\Task> $tasks */
    $tlTasks = $tasks->filter(fn ($t) => $t->initiativeStart() && $t->initiativeEnd())->values();
    $rangeStart = null;
    $rangeEnd = null;
    foreach ($tlTasks as $t) {
        $s = $t->initiativeStart();
        $e = $t->initiativeEnd();
        if ($s && ($rangeStart === null || $s->lt($rangeStart))) {
            $rangeStart = $s->copy()->startOfDay();
        }
        if ($e && ($rangeEnd === null || $e->gt($rangeEnd))) {
            $rangeEnd = $e->copy()->endOfDay();
        }
    }
    if ($rangeStart && $rangeEnd) {
        $timelineStart = $rangeStart->copy()->startOfMonth();
        $timelineEnd = $rangeEnd->copy()->endOfMonth();
        $totalDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);
        $monthCursor = $timelineStart->copy();
        $monthLabels = [];
        while ($monthCursor->lte($timelineEnd)) {
            $monthLabels[] = $monthCursor->copy();
            $monthCursor->addMonth()->startOfMonth();
        }
        $quarterLabel = 'Q'.$timelineStart->quarter.' '.$timelineStart->format('Y');
    } else {
        $timelineStart = null;
        $totalDays = 1;
        $monthLabels = [];
        $quarterLabel = null;
    }

    $taskBlocked = static function ($tt): bool {
        if (isset($tt->blocking_links_count)) {
            return $tt->blocking_links_count > 0;
        }

        return $tt->relationLoaded('linksAsTarget')
            && $tt->linksAsTarget->where('type', TaskLink::TYPE_BLOCKS)->isNotEmpty();
    };
@endphp

@if ($timelineStart && $tlTasks->isNotEmpty())
    <div class="space-y-2">
        @if ($quarterLabel)
            <div class="flex ps-[11rem] pe-1">
                <div class="flex-1 text-center text-[10px] font-semibold uppercase tracking-wide text-ink/40">
                    {{ $quarterLabel }}
                </div>
            </div>
        @endif
        <div class="flex ps-[11rem] pe-1">
            @foreach ($monthLabels as $m)
                <div class="flex-1 min-w-0 border-l border-cream-200 px-1 text-center text-[10px] font-semibold uppercase tracking-wide text-ink/50">
                    {{ $m->format('M Y') }}
                </div>
            @endforeach
        </div>
        @foreach ($tlTasks as $tt)
            @php
                $ts = $tt->initiativeStart()->startOfDay();
                $te = $tt->initiativeEnd()->endOfDay();
                $leftPct = min(100, max(0, ($timelineStart->diffInDays($ts) / $totalDays) * 100));
                $spanDays = max(1, $ts->diffInDays($te) + 1);
                $widthPct = min(100 - $leftPct, max(0.8, ($spanDays / $totalDays) * 100));
                $ttBlocked = $taskBlocked($tt);
            @endphp
            <div class="flex items-center gap-3 text-sm">
                @if ($interactive)
                    <button
                        type="button"
                        wire:click="openTaskDetail({{ $tt->id }})"
                        class="w-44 shrink-0 truncate text-left text-xs font-medium text-sage-dark hover:underline"
                        title="{{ $tt->title }}"
                    >
                        {{ $tt->title }}
                    </button>
                @else
                    <span class="w-44 shrink-0 truncate text-xs font-medium text-ink" title="{{ $tt->title }}">{{ $tt->title }}</span>
                @endif
                <div class="relative h-7 min-w-0 flex-1 rounded-md {{ $ttBlocked ? 'bg-amber-50 ring-1 ring-amber-200' : 'bg-cream-100' }}">
                    <div
                        class="absolute top-1 bottom-1 rounded-md {{ $ttBlocked ? 'bg-amber-600' : 'bg-sage' }} shadow-sm"
                        style="left: {{ $leftPct }}%; width: {{ $widthPct }}%; min-width: 4px;"
                    ></div>
                </div>
                @if ($ttBlocked)
                    <span class="shrink-0 text-[10px] font-semibold uppercase text-amber-900">Blocked</span>
                @endif
            </div>
        @endforeach
    </div>
    @if ($tlTasks->contains($taskBlocked))
        <p class="mt-4 text-xs text-ink/55">Amber bars indicate tasks with a <strong>blocked by</strong> dependency. Resolve links on the task detail panel.</p>
    @endif
@else
    <p class="text-sm text-ink/55">
        @if ($emptyHint)
            {{ $emptyHint }}
        @elseif ($tasks->isEmpty())
            Add tasks on the Board tab, then set initiative start/end dates (or a due date) to populate this timeline.
        @else
            No schedulable dates yet. Add start/end dates or a due date on tasks—those dates drive this view and spot overlaps.
        @endif
    </p>
@endif
