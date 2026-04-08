import './bootstrap';
import Sortable from 'sortablejs';

const kanbanSortables = [];
const matrixSortables = [];
const eisenhowerSortables = [];

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

function destroyMatrixSortables() {
    while (matrixSortables.length) {
        const s = matrixSortables.pop();
        try {
            s.destroy();
        } catch {
            //
        }
    }
}

function destroyEisenhowerSortables() {
    while (eisenhowerSortables.length) {
        const s = eisenhowerSortables.pop();
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
            chosenClass: 'kanban-sortable-chosen',
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

function collectMatrixPayload(boardRoot) {
    const payload = {};
    boardRoot.querySelectorAll('[data-matrix-list]').forEach((col) => {
        const key = col.getAttribute('data-matrix-key');
        payload[key] = [...col.querySelectorAll('[data-task-id]')].map((listEl) =>
            parseInt(listEl.getAttribute('data-task-id'), 10),
        );
    });

    return payload;
}

function initMatrixBoard(boardRoot) {
    if (!boardRoot) {
        return;
    }

    destroyMatrixSortables();

    const sortEnabled = boardRoot.dataset.matrixInit !== '0';

    boardRoot.querySelectorAll('[data-matrix-list]').forEach((listEl) => {
        if (!sortEnabled) {
            return;
        }
        const sortable = Sortable.create(listEl, {
            group: { name: 'matrix', pull: true, put: true },
            animation: 150,
            handle: '.kanban-card-handle',
            draggable: '[data-task-id]',
            ghostClass: 'opacity-50',
            chosenClass: 'kanban-sortable-chosen',
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
                comp.call('syncMatrixBoard', collectMatrixPayload(boardRoot));
            },
        });
        matrixSortables.push(sortable);
    });
}

function tryInitMatrixFromNode(el) {
    if (!el) {
        return;
    }
    const board = el.matches('[data-matrix-board]') ? el : el.querySelector('[data-matrix-board]');
    if (board) {
        queueMicrotask(() => initMatrixBoard(board));
    }
}

function collectEisenhowerPayload(boardRoot) {
    const payload = {};
    boardRoot.querySelectorAll('[data-eisenhower-list]').forEach((col) => {
        const key = col.getAttribute('data-eisenhower-key');
        payload[key] = [...col.querySelectorAll('[data-task-id]')].map((listEl) =>
            parseInt(listEl.getAttribute('data-task-id'), 10),
        );
    });

    return payload;
}

function initEisenhowerBoard(boardRoot) {
    if (!boardRoot) {
        return;
    }

    destroyEisenhowerSortables();

    const sortEnabled = boardRoot.dataset.eisenhowerInit !== '0';

    boardRoot.querySelectorAll('[data-eisenhower-list]').forEach((listEl) => {
        if (!sortEnabled) {
            return;
        }
        const sortable = Sortable.create(listEl, {
            group: { name: 'eisenhower', pull: true, put: true },
            animation: 150,
            handle: '.kanban-card-handle',
            draggable: '[data-task-id]',
            ghostClass: 'opacity-50',
            chosenClass: 'kanban-sortable-chosen',
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
                comp.call('syncEisenhowerBoard', collectEisenhowerPayload(boardRoot));
            },
        });
        eisenhowerSortables.push(sortable);
    });
}

function tryInitEisenhowerFromNode(el) {
    if (!el) {
        return;
    }
    const board = el.matches('[data-eisenhower-board]') ? el : el.querySelector('[data-eisenhower-board]');
    if (board) {
        queueMicrotask(() => initEisenhowerBoard(board));
    }
}

function refreshLayoutSortables(root) {
    if (!root) {
        return;
    }
    if (!root.querySelector('[data-kanban-board]')) {
        destroyKanbanSortables();
    }
    if (!root.querySelector('[data-matrix-board]')) {
        destroyMatrixSortables();
    }
    if (!root.querySelector('[data-eisenhower-board]')) {
        destroyEisenhowerSortables();
    }
    tryInitKanbanFromNode(root);
    tryInitMatrixFromNode(root);
    tryInitEisenhowerFromNode(root);
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('component.init', ({ component }) => {
        refreshLayoutSortables(component.el);
    });

    Livewire.hook('morph.updated', ({ el, component }) => {
        refreshLayoutSortables(component?.el || el);
    });
});
