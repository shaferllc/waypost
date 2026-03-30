import './bootstrap';
import Sortable from 'sortablejs';

const kanbanSortables = [];

function destroyKanbanSortables() {
    while (kanbanSortables.length) {
        const s = kanbanSortables.pop();
        try {
            s.destroy();
        } catch {
            //
        }
    }
}

function collectKanbanPayload(boardRoot) {
    const payload = {};
    boardRoot.querySelectorAll('[data-kanban-list]').forEach((col) => {
        const st = col.getAttribute('data-kanban-status');
        payload[st] = [...col.querySelectorAll('[data-task-id]')].map((el) =>
            parseInt(el.getAttribute('data-task-id'), 10),
        );
    });

    return payload;
}

function initKanbanBoard(boardRoot) {
    if (!boardRoot) {
        return;
    }

    destroyKanbanSortables();

    const sortEnabled = boardRoot.dataset.kanbanInit !== '0';

    boardRoot.querySelectorAll('[data-kanban-list]').forEach((listEl) => {
        if (!sortEnabled) {
            return;
        }
        const sortable = Sortable.create(listEl, {
            group: { name: 'kanban', pull: true, put: true },
            animation: 150,
            handle: '.kanban-card-handle',
            draggable: '[data-task-id]',
            ghostClass: 'opacity-50',
            chosenClass: 'ring-2 ring-teal-400',
            dragClass: 'cursor-grabbing',
            onEnd: () => {
                const componentEl = boardRoot.closest('[wire\\:id]');
                if (!componentEl) {
                    return;
                }
                const id = componentEl.getAttribute('wire:id');
                const comp = window.Livewire.find(id);
                if (!comp) {
                    return;
                }
                comp.call('syncKanban', collectKanbanPayload(boardRoot));
            },
        });
        kanbanSortables.push(sortable);
    });
}

function tryInitKanbanFromNode(el) {
    if (!el) {
        return;
    }
    const board = el.matches('[data-kanban-board]') ? el : el.querySelector('[data-kanban-board]');
    if (board) {
        queueMicrotask(() => initKanbanBoard(board));
    }
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('component.init', ({ component }) => {
        tryInitKanbanFromNode(component.el);
    });

    Livewire.hook('morph.updated', ({ el, component }) => {
        if (component?.el && !component.el.querySelector('[data-kanban-board]')) {
            destroyKanbanSortables();
        }
        tryInitKanbanFromNode(component?.el || el);
    });
});
