/**
 * Agile Board — board.js
 * Vanilla JS: fetch-based API calls, HTML5 drag-and-drop, sidebar, modal, toasts.
 */
const board = (() => {

    // ── State ──────────────────────────────────────────────────────────────────
    let boards = [];
    let currentBoardId = null;

    let tasks = [];
    let users = [];
    let comments = [];
    let attachments = [];
    let draggingId = null;
    let draggingColumnId = null;
    let sidebarId = null;
    let addStatus = 'todo';
    let searchVal = '';
    let notifications = [];
    let editingCommentId = null;
    let editingCommentBody = '';
    let _lastTasksJson = '';  // used by pollTasks to skip no-op re-renders

    // Undo-delete state (tasks)
    let pendingDelete = null;
    let pendingDeleteTimer = null;

    const PRIORITIES = [
        { id: 'low',  label: 'Low',      color: '#4ae8a3' },
        { id: 'mid',  label: 'Mid',      color: '#e8c84a' },
        { id: 'high', label: 'High',     color: '#e8734a' },
        { id: 'crit', label: 'Critical', color: '#e84a4a' },
    ];

    // Dynamic columns — loaded per board from API
    let columns = [];

    // Determine dynamic API root for deployments under subfolders
    const APP_BASE = typeof APP_BASE_PATH === 'string' ? APP_BASE_PATH : '';
    const API_ROOT = `${APP_BASE}/api`;

    // ── Idle timeout & auto-logout ─────────────────────────────────────────────
    const IDLE_MS = 30 * 60 * 1000; // 30 minutes
    let idleTimer = null;

    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(doIdleLogout, IDLE_MS);
    }

    function doIdleLogout() {
        navigator.sendBeacon(`${APP_BASE}/logout_beacon.php`);
        window.location.href = `${APP_BASE}/login.php?reason=idle`;
    }

    function bindIdleReset() {
        ['mousemove','keydown','mousedown','touchstart','scroll'].forEach(evt => {
            document.addEventListener(evt, resetIdleTimer, { passive: true });
        });
        resetIdleTimer();
    }

    function bindBeaconLogout() {
        // Only send beacon on true tab/window close, not on internal navigation.
        // We detect internal navigation by setting a flag on any same-origin link click.
        let navigatingInternally = false;

        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[href]');
            if (!a) return;
            try {
                const url = new URL(a.href, window.location.origin);
                if (url.origin === window.location.origin) {
                    navigatingInternally = true;
                }
            } catch (_) {}
        });

        // Also flag form submissions (logout form, settings form, etc.)
        document.addEventListener('submit', () => { navigatingInternally = true; });

        const sendBeacon = () => {
            if (!navigatingInternally) {
                navigator.sendBeacon(`${APP_BASE}/logout_beacon.php`);
            }
            navigatingInternally = false;
        };

        window.addEventListener('pagehide', sendBeacon);
        window.addEventListener('beforeunload', sendBeacon);
    }

    function bindNavigationLinks() {
        document.querySelectorAll('.user-nav-link').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });
    }

    // ── Init ───────────────────────────────────────────────────────────────────
    // Entry point for the app. Loads current user state, boards, columns, and
    // tasks from the API, then initializes UI event handlers and renders the
    // main board view.
    async function init() {
        applySavedTheme();

        await Promise.all([loadUsers(), loadBoards()]);
        await Promise.all([loadColumns(), loadTasks()]);

        renderBoardList();
        renderCurrentBoardName();
        renderTeamAvatars();
        renderColumnManager();

        bindSearch();
        bindNotifPanel();
        bindKeyboardShortcuts();
        bindThemeToggle();
        bindBoardActions();
        bindNavigationLinks();
        bindIdleReset();
        bindBeaconLogout();

        const newTaskBtn = document.getElementById('new-task-btn');
        if (newTaskBtn) {
            newTaskBtn.addEventListener('click', () => openAdd(columns[0]?.status_key || 'todo'));
        }
    }

    // ── API helpers ────────────────────────────────────────────────────────────
    // Helper for JSON API requests. Sends the payload as JSON and throws an
    // Error if the response is not OK, so callers can handle errors uniformly.
    async function apiFetch(url, opts = {}) {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json' },
            ...opts,
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }

        return data;
    }

    // Helper for file uploads. This bypasses JSON encoding so the browser can
    // send multipart form data with a file attachment.
    async function uploadFetch(url, formData) {
        const res = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }
        return data;
    }

    // Load all users and refresh assignment dropdowns. The app caches users
    // locally and uses them for task assignee selection and avatar display.
    async function loadUsers() {
        try {
            users = await apiFetch(`${API_ROOT}/get_users.php`);
            populateAssigneeSelect();
        } catch (e) {
            console.error('loadUsers:', e);
        }
    }

    // Load the available boards, restore last selected board if possible, and
    // fall back to the first board when the previous board has disappeared.
    async function loadBoards() {
        try {
            boards = await apiFetch(`${API_ROOT}/get_boards.php`);

            const savedBoardId = localStorage.getItem('currentBoardId');
            const hasSavedBoard = boards.some(b => String(b.id) === String(savedBoardId));

            if (hasSavedBoard) {
                currentBoardId = parseInt(savedBoardId, 10);
            } else if (boards.length) {
                currentBoardId = boards[0].id;
                localStorage.setItem('currentBoardId', String(currentBoardId));
            } else {
                currentBoardId = null;
            }
        } catch (e) {
            console.error('loadBoards:', e);
            boards = [];
            currentBoardId = null;
        }
    }

    async function loadTasks() {
        if (!currentBoardId) {
            tasks = [];
            renderBoard();
            return;
        }

        try {
            tasks = await apiFetch(`${API_ROOT}/get_tasks.php?board_id=${encodeURIComponent(currentBoardId)}`);
            renderBoard();
        } catch (e) {
            console.error('loadTasks:', e);
            tasks = [];
            renderBoard();
        }
    }

    // Load the workflow columns for the currently selected board. Columns are
    // the board lanes that determine task status groups and drag/drop targets.
    async function loadColumns() {
        if (!currentBoardId) { columns = []; return; }
        try {
            columns = await apiFetch(`${API_ROOT}/get_columns.php?board_id=${encodeURIComponent(currentBoardId)}`);
        } catch (e) {
            console.error('loadColumns:', e);
            columns = [];
        }
    }

    // ── Boards ─────────────────────────────────────────────────────────────────
    // Render the board selection sidebar. The active board is highlighted,
    // and admin users get delete controls inline with each row.
    function renderBoardList() {
        const el = document.getElementById('boards-list');
        if (!el) return;

        if (!boards.length) {
            el.innerHTML = `<div class="notif-empty">No boards yet</div>`;
            return;
        }

        el.innerHTML = boards.map(b => `
            <div class="board-row ${String(b.id) === String(currentBoardId) ? 'active' : ''}">
                <button
                    class="board-nav-item"
                    type="button"
                    onclick="board.switchBoard(${Number(b.id)})"
                    aria-label="Open board ${esc(b.name)}">
                    ${esc(b.name)}
                </button>

                ${IS_ADMIN ? `
                <button
                    class="board-delete-btn"
                    type="button"
                    onclick="board.showDeleteBoardModal(${Number(b.id)})"
                    title="Delete board"
                    aria-label="Delete board ${esc(b.name)}">
                    ×
                </button>
                ` : ''}
            </div>
        `).join('');
    }

    // Update the top header text to show the currently selected board name.
    function renderCurrentBoardName() {
        const el = document.getElementById('current-board-name');
        if (!el) return;

        const current = boards.find(b => String(b.id) === String(currentBoardId));
        el.textContent = current ? current.name : 'No Board';
    }

    // Switch the current board context. This updates local storage, resets the
    // search state, closes any open sidebar forms, and reloads columns/tasks.
    async function switchBoard(boardId) {
        if (String(boardId) === String(currentBoardId)) return;

        currentBoardId = boardId;
        localStorage.setItem('currentBoardId', String(currentBoardId));

        searchVal = '';
        const searchInput = document.getElementById('search-input');
        if (searchInput) searchInput.value = '';

        closeSidebar();
        closeAdd();

        renderBoardList();
        renderCurrentBoardName();
        await Promise.all([loadColumns(), loadTasks()]);
        renderColumnManager();
    }

    function bindBoardActions() {
        const newBoardBtn = document.getElementById('new-board-btn');
        if (!newBoardBtn) return;

        newBoardBtn.addEventListener('click', showCreateBoardModal);
    }

    // Show the modal dialog used to enter a new board name.
    // This function builds the overlay dynamically and registers form handlers.
    function showCreateBoardModal() {
        const existing = document.getElementById('board-modal-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'board-modal-overlay';
        overlay.className = 'overlay';
        overlay.style.display = 'flex';
        overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };

        overlay.innerHTML = `
            <div class="modal" onclick="event.stopPropagation()" style="max-width:360px">
                <span class="section-label">New Board</span>
                <div style="margin-top:14px">
                    <div class="field-label" style="margin-bottom:6px">Board Name</div>
                    <input type="text" id="board-name-inp" placeholder="e.g. Sprint Planning" maxlength="100" autofocus>
                </div>
                <div class="modal-footer" style="margin-top:16px">
                    <button class="btn-primary" type="button" id="board-create-btn">Create Board</button>
                    <button class="btn-ghost" type="button" onclick="document.getElementById('board-modal-overlay').remove()">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById('board-create-btn')?.addEventListener('click', submitCreateBoard);
        document.getElementById('board-name-inp')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') submitCreateBoard();
        });
    }

    // Show a confirmation modal before deleting a board. This is the UI-level
    // guard to prevent accidental deletion of entire boards and their tasks.
    function showDeleteBoardModal(boardId) {
        const boardToDelete = boards.find(b => b.id == boardId);
        if (!boardToDelete) return;

        const existing = document.getElementById('board-delete-modal-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'board-delete-modal-overlay';
        overlay.className = 'overlay';
        overlay.style.display = 'flex';
        overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };

        overlay.innerHTML = `
            <div class="modal" onclick="event.stopPropagation()" style="max-width:360px">
                <span class="section-label">Delete Board</span>
                <div style="margin-top:14px; color: var(--text-2); line-height:1.6;">
                    Are you sure you want to delete <strong>${esc(boardToDelete.name)}</strong>?<br>
                    This will permanently remove all tasks, comments, and attachments on this board.
                </div>
                <div class="modal-footer" style="margin-top:16px; justify-content: space-between;">
                    <button class="btn-primary" type="button" id="board-delete-confirm-btn">Delete</button>
                    <button class="btn-ghost" type="button" onclick="document.getElementById('board-delete-modal-overlay').remove()">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        document.getElementById('board-delete-confirm-btn')?.addEventListener('click', () => {
            document.getElementById('board-delete-modal-overlay')?.remove();
            deleteBoard(boardId);
        });
    }

    // Submit a new board to the API. On success, the newly created board is
    // added to local state and the UI is refreshed to select it immediately.
    async function submitCreateBoard() {
        const nameEl = document.getElementById('board-name-inp');
        const name = nameEl ? nameEl.value : '';
        const trimmed = name.trim();

        if (!trimmed) {
            showToast('Board name is required');
            nameEl?.focus();
            return;
        }

        try {
            const data = await apiFetch(`${API_ROOT}/create_board.php`, {
                method: 'POST',
                body: JSON.stringify({ name: trimmed }),
            });

            boards.push(data);
            currentBoardId = data.id;
            localStorage.setItem('currentBoardId', String(currentBoardId));

            searchVal = '';
            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.value = '';

            document.getElementById('board-modal-overlay')?.remove();
            renderBoardList();
            renderCurrentBoardName();
            await Promise.all([loadColumns(), loadTasks()]);
            renderColumnManager();

            showToast(`Created board "${data.name}"`);
        } catch (e) {
            console.error('createBoard:', e);
            showToast(e.message || 'Failed to create board');
        }
    }

    // Delete a board. The UI is updated optimistically, then the API call is
    // made. If the API fails, the deletion is rolled back locally.
    async function deleteBoard(boardId) {
        const boardToDelete = boards.find(b => b.id == boardId);
        if (!boardToDelete) return;

        if (boards.length <= 1) {
            showToast('Cannot delete the last board');
            return;
        }

        const originalIndex = boards.findIndex(b => b.id == boardId);
        const previousBoardId = currentBoardId;

        // Remove from UI immediately
        boards = boards.filter(b => b.id != boardId);

        // If deleting the current board, switch locally to another one
        if (String(boardId) === String(currentBoardId)) {
            currentBoardId = boards.length ? boards[0].id : null;

            if (currentBoardId) {
                localStorage.setItem('currentBoardId', String(currentBoardId));
            } else {
                localStorage.removeItem('currentBoardId');
            }
        }

        try {
            await apiFetch(`${API_ROOT}/delete_board.php`, {
                method: 'POST',
                body: JSON.stringify({ id: boardId }),
            });

            renderBoardList();
            renderCurrentBoardName();
            await Promise.all([loadColumns(), loadTasks()]);
            renderColumnManager();

            showToast(`Deleted "${boardToDelete.name}"`);
        } catch (e) {
            console.error('deleteBoard:', e);

            boards.splice(originalIndex, 0, boardToDelete);
            currentBoardId = previousBoardId;
            if (currentBoardId) {
                localStorage.setItem('currentBoardId', String(currentBoardId));
            }

            renderBoardList();
            renderCurrentBoardName();
            await Promise.all([loadColumns(), loadTasks()]);
            renderColumnManager();

            showToast(e.message || 'Failed to delete board');
        }
    }

    // ── Rendering ──────────────────────────────────────────────────────────────
    // Rebuild the in-page board view from current tasks and columns.
    // This function supports search filtering and ensures the DOM order matches
    // the column order returned by the server.
    function renderBoard() {
        const query = searchVal.toLowerCase();
        const board = document.getElementById('board');
        if (!board) return;

        const visible = tasks.filter(t =>
            !query ||
            String(t.title || '').toLowerCase().includes(query) ||
            String(t.tags || '').toLowerCase().includes(query) ||
            String(t.description || '').toLowerCase().includes(query)
        );

        // Build columns from server data instead of hardcoded markup so each board
        // can own its own workflow shape.
        const newKeys = new Set(columns.map(c => c.status_key));

        // Remove columns that no longer exist
        board.querySelectorAll('.col').forEach(el => {
            if (!newKeys.has(el.dataset.status)) el.remove();
        });

        // Add/update columns in order
        columns.forEach((col, idx) => {
            let colEl = board.querySelector(`.col[data-status="${col.status_key}"]`);

            if (!colEl) {
                colEl = document.createElement('div');
                colEl.className = 'col';
                colEl.dataset.status = col.status_key;
                board.appendChild(colEl);
            }

            // Ensure correct DOM order
            const children = [...board.children];
            if (children[idx] !== colEl) board.insertBefore(colEl, children[idx]);

            const colTasks = visible.filter(t => t.status === col.status_key);

            colEl.innerHTML = `
                <div class="col-header">
                    <div class="col-dot" style="background:${col.color}"></div>
                    <span class="col-title">${esc(col.name)}</span>
                    <span class="col-count">${colTasks.length}</span>
                </div>
                <div class="task-list" id="list-${col.status_key}"
                     ondragover="board.onDragOver(event,'${col.status_key}')"
                     ondragleave="board.onDragLeave(event)"
                     ondrop="board.onDrop(event,'${col.status_key}')"></div>
                <button class="add-task-btn" type="button"
                        onclick="board.openAdd('${col.status_key}')">+ Add task</button>
            `;

            const list = colEl.querySelector('.task-list');
            colTasks.forEach(t => list.appendChild(makeCard(t)));
        });

        updateProgress();
    }

    // Create a single task card DOM element. Cards are draggable and open the
    // sidebar when clicked. The card includes priority, assignee, tags, and age.
    function makeCard(task) {
        const user = users.find(u => u.id == task.assigned_to);
        const pri = PRIORITIES.find(p => p.id === task.priority);
        const tags = (task.tags || '').split(',').map(t => t.trim()).filter(Boolean);
        const ageStr = taskAge(task.created_at);

        const card = document.createElement('div');
        card.className = 'task-card';
        card.dataset.id = task.id;
        card.draggable = true;
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `Open task ${task.title}`);

        card.innerHTML = `
            <div class="card-top">
                <div class="card-title">${esc(task.title)}</div>
                ${user
    ? `<div class="card-avatar" title="${esc(user.display_name)}"
           style="background:${user.color}22;border-color:${user.color};color:${user.color}">
           ${esc(user.avatar)}
       </div>`
    : `<div class="card-avatar card-avatar-empty"></div>`}
            </div>
            ${task.description
                ? `<p class="card-desc">${esc(task.description)}</p>`
                : ''}
            <div class="card-meta">
                ${pri ? `<span class="badge-pri"
                    style="background:${pri.color}18;color:${pri.color};border-color:${pri.color}44">
                    ${pri.label}</span>` : ''}
                ${tags.map(t => `<span class="badge-tag">${esc(t)}</span>`).join('')}
                ${task.story_points ? `<span class="story-points">${task.story_points}pt</span>` : ''}
                <span class="card-age">${ageStr}</span>
            </div>`;

        card.addEventListener('dragstart', e => onDragStart(e, task.id));
        card.addEventListener('dragend', onDragEnd);
        card.addEventListener('click', () => openSidebar(task.id));
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openSidebar(task.id);
            }
        });

        return card;
    }

    function renderTeamAvatars() {
        const el = document.getElementById('team-avatars');
        if (!el) return;

        el.innerHTML = users.map((u, i) =>
            `<div class="team-avatar" title="${esc(u.display_name)}"
                  style="margin-left:${i ? '-6px' : '0'};background:${u.color}22;border:1.5px solid ${u.color};color:${u.color}">
                ${esc(u.avatar)}
            </div>`
        ).join('');
    }

    // Calculate sprint progress from the full task set.
    // This uses the board's Done column, not the currently filtered search results.
    function updateProgress() {
        const total = tasks.length;
        const doneStatuses = new Set(
            columns
                .filter(c => String(c.status_key).trim().toLowerCase() === 'done')
                .map(c => String(c.status_key).trim().toLowerCase())
        );

        const doneTasks = tasks.filter(t =>
            doneStatuses.has(String(t.status).trim().toLowerCase())
        );

        const done = doneTasks.length;
        const pct = total ? Math.round((done / total) * 100) : 0;

        const sprintPct = document.getElementById('sprint-pct');
        const progressFill = document.getElementById('progress-fill');

        if (sprintPct) sprintPct.textContent = `${pct}%`;
        if (progressFill) progressFill.style.width = `${pct}%`;

        const totalSP = tasks.reduce((s, t) => s + (parseInt(t.story_points) || 0), 0);
        const doneSP = doneTasks.reduce((s, t) => s + (parseInt(t.story_points) || 0), 0);

        const spDoneEl = document.getElementById('sp-done');
        const spTotalEl = document.getElementById('sp-total');
        if (spDoneEl) spDoneEl.textContent = `${doneSP} pts done`;
        if (spTotalEl) spTotalEl.textContent = `${totalSP} pts total`;
}

    // ── Drag & Drop ────────────────────────────────────────────────────────────
    // Drag lifecycle handlers for moving tasks between columns.
    // Only status changes are sent to the server on drop.
    function onDragStart(e, id) {
        draggingId = id;
        e.dataTransfer.effectAllowed = 'move';
        e.currentTarget.classList.add('dragging');
    }

    function onDragEnd() {
        draggingId = null;
        document.querySelectorAll('.task-card').forEach(c => c.classList.remove('dragging'));
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));
    }

    function onDragOver(e, status) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));

        const list = document.getElementById(`list-${status}`);
        if (list) {
            list.classList.add('drag-over');
        }
    }

    function onDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    // Handle dropping a task card into a new status column. This updates the
    // client preview immediately, then persists the status update to the API.
    async function onDrop(e, status) {
        e.preventDefault();
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));

        if (!draggingId) return;

        const task = tasks.find(t => t.id == draggingId);
        if (!task || task.status === status) {
            draggingId = null;
            return;
        }

        const oldStatus = task.status;
        task.status = status;
        renderBoard();

        const colLabel = columns.find(c => c.status_key === status)?.name || status;

        try {
            await apiFetch(`${API_ROOT}/update_task.php`, {
                method: 'POST',
                body: JSON.stringify({ id: draggingId, status }),
            });

            notify(`"${task.title}" moved to ${colLabel}`);

            if (sidebarId == task.id) {
                renderSidebarFields(task);
                loadComments(task.id);

                const overlay = document.getElementById('sidebar-overlay');
                if (overlay) {
                    overlay.style.display = 'flex';
                }
            }
        } catch (e) {
            console.error('onDrop API error:', e);
            showToast('Failed to move task');
            task.status = oldStatus;
            renderBoard();
        }

        draggingId = null;
    }

    // ── Sidebar ────────────────────────────────────────────────────────────────
    // Open the task detail sidebar for the selected task. Sidebar state is
    // derived from the local task object and refreshed with comments/attachments.
    function openSidebar(id) {
        sidebarId = id;
        const task = tasks.find(t => t.id == id);
        if (!task) return;

        const title = document.getElementById('s-title');
        const desc = document.getElementById('s-desc');
        const tags = document.getElementById('s-tags');
        const overlay = document.getElementById('sidebar-overlay');

        if (title) title.value = task.title;
        if (desc) desc.value = task.description || '';
        if (tags) {
            tags.value = (task.tags || '')
                .split(',')
                .map(t => t.trim())
                .filter(Boolean)
                .join(', ');
        }

        // Story points
        const spEl = document.getElementById('s-story-points');
        if (spEl) spEl.value = task.story_points || '';

        renderSidebarFields(task);
        loadComments(task.id);
        loadAttachments(task.id);

        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function renderSidebarFields(task) {
        const pg = document.getElementById('s-priority-group');
        const sg = document.getElementById('s-status-group');
        const ag = document.getElementById('s-assignee-group');

        if (pg) {
            pg.innerHTML = PRIORITIES.map(p => {
                const active = task.priority === p.id;
                return `<button class="pill-btn" type="button" onclick="board.setSidebarField('priority','${p.id}')"
                    style="border-color:${active ? p.color : ''};background:${active ? p.color + '22' : ''};color:${active ? p.color : ''}">
                    ${p.label}</button>`;
            }).join('');
        }

        if (sg) {
            sg.innerHTML = columns.map(c => {
                const active = task.status === c.status_key;
                return `<button class="pill-btn" type="button" onclick="board.setSidebarField('status','${c.status_key}')"
                    style="border-color:${active ? c.color : ''};background:${active ? c.color + '18' : ''};color:${active ? c.color : ''}">
                    ${esc(c.name)}</button>`;
            }).join('');
        }

        if (ag) {
            const noneActive = !task.assigned_to;
            ag.innerHTML = `<button class="pill-btn" type="button" onclick="board.setSidebarField('assigned_to',null)"
                style="border-color:${noneActive ? '#aaa' : ''};color:${noneActive ? '#ccc' : ''}">
                None</button>`;

            ag.innerHTML += users.map(u => {
                const active = task.assigned_to == u.id;
                return `<button class="pill-btn" type="button" onclick="board.setSidebarField('assigned_to',${u.id})"
                    style="border-color:${active ? u.color : ''};background:${active ? u.color + '18' : ''};color:${active ? u.color : ''}">
                    ${esc(u.display_name)}</button>`;
            }).join('');
        }
    }

    function setSidebarField(field, value) {
        const task = tasks.find(t => t.id == sidebarId);
        if (!task) return;

        task[field] = value;
        renderSidebarFields(task);
    }

    function closeSidebar() {
        sidebarId = null;
        comments = [];
        attachments = [];

        const overlay = document.getElementById('sidebar-overlay');
        const commentsList = document.getElementById('comments-list');
        const commentInput = document.getElementById('comment-input');
        const attachmentInput = document.getElementById('attachment-input');
        const attachmentsList = document.getElementById('attachments-list');

        if (commentsList) commentsList.innerHTML = `<div class="comments-empty">No comments yet</div>`;
        if (commentInput) commentInput.value = '';
        if (attachmentInput) attachmentInput.value = '';
        if (attachmentsList) attachmentsList.innerHTML = `<div class="comments-empty">No attachments yet</div>`;

        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async function saveTask() {
        const task = tasks.find(t => t.id == sidebarId);
        if (!task) return;

        const titleEl = document.getElementById('s-title');
        const descEl = document.getElementById('s-desc');
        const tagsEl = document.getElementById('s-tags');

        const title = titleEl ? titleEl.value.trim() : '';
        if (!title) {
            alert('Title is required.');
            return;
        }

        const tagsRaw = tagsEl ? tagsEl.value : '';
        const tags = tagsRaw.split(',').map(t => t.trim()).filter(Boolean).join(',');

        const spEl = document.getElementById('s-story-points');
        const spVal = spEl ? spEl.value.trim() : '';
        const storyPoints = spVal !== '' ? parseInt(spVal, 10) : null;

        const payload = {
            id: task.id,
            title,
            description: descEl ? descEl.value : '',
            status: task.status,
            priority: task.priority,
            assigned_to: task.assigned_to || null,
            tags,
            story_points: storyPoints,
        };

        try {
            const updated = await apiFetch(`${API_ROOT}/update_task.php`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            const idx = tasks.findIndex(t => t.id == sidebarId);
            if (idx !== -1) {
                tasks[idx] = { ...tasks[idx], ...updated };
            }

            renderBoard();
            notify(`Updated "${title}"`);
            closeSidebar();
        } catch (e) {
            console.error('saveTask:', e);
            showToast('Failed to save task');
        }

    }

    async function loadComments(taskId) {
        try {
            comments = await apiFetch(`${API_ROOT}/get_comments.php?task_id=${encodeURIComponent(taskId)}`);
            renderComments();
        } catch (e) {
            console.error('loadComments:', e);
            comments = [];
            renderComments();
        }
    }

    // Load attachments for the task and display them in the sidebar.
    async function loadAttachments(taskId) {
        try {
            attachments = await apiFetch(`${API_ROOT}/get_attachments.php?task_id=${encodeURIComponent(taskId)}`);
            renderAttachments();
        } catch (e) {
            console.error('loadAttachments:', e);
            attachments = [];
            renderAttachments();
        }
    }

    // Render the list of files attached to the task. Each attachment is shown as
    // a download link with metadata and an optional delete action.
    function renderAttachments() {
        const list = document.getElementById('attachments-list');
        if (!list) return;

        if (!attachments.length) {
            list.innerHTML = `<div class="comments-empty">No attachments yet</div>`;
            return;
        }

        list.innerHTML = attachments.map((attachment) => {
            const canDelete = Number(attachment.uploaded_by) === Number(CURRENT_USER.id) || IS_ADMIN;
            const downloadUrl = `${APP_BASE}/download_attachment.php?id=${encodeURIComponent(attachment.id)}`;
            const sizeKb = Math.max(1, Math.round((Number(attachment.size_bytes) || 0) / 1024));

            return `
                <div class="attachment-item">
                    <a class="attachment-link" href="${downloadUrl}">
                        <span class="attachment-icon">📎</span>
                        <div class="attachment-copy">
                            <span class="attachment-name">${esc(attachment.original_name)}</span>
                            <span class="attachment-meta">${sizeKb} KB · ${esc(attachment.display_name)}</span>
                        </div>
                        <span class="attachment-download">Download</span>
                    </a>
                    ${canDelete ? `
                    <button class="attachment-delete-btn btn-ghost btn-compact" type="button" onclick="board.deleteAttachment(${attachment.id})">
                        Delete
                    </button>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function renderComments() {
        const list = document.getElementById('comments-list');
        if (!list) return;

        if (!comments.length) {
            list.innerHTML = `<div class="comments-empty">No comments yet</div>`;
            return;
        }

        list.innerHTML = comments.map(comment => {
            const isOwner = Number(comment.user_id) === Number(CURRENT_USER.id);
            const isEditing = Number(comment.id) === Number(editingCommentId);

            return `
                <div class="comment-item">
                    <div class="comment-head">
                        <div class="comment-user">
                            <div class="comment-avatar"
                                 style="background:${comment.color}22;border-color:${comment.color};color:${comment.color}">
                                ${esc(comment.avatar)}
                            </div>
                            <div>
                                <div class="comment-author">${esc(comment.display_name)}</div>
                                <div class="comment-time">${formatCommentTime(comment.created_at)}</div>
                            </div>
                        </div>

                        ${isOwner ? `
                            <div class="comment-actions">
                                ${
                                    isEditing
                                        ? `
                                            <button class="comment-action-btn" type="button" onclick="board.saveEditedComment(${comment.id})">Save</button>
                                            <button class="comment-action-btn" type="button" onclick="board.cancelEditComment()">Cancel</button>
                                          `
                                        : `
                                            <button class="comment-action-btn" type="button" onclick="board.startEditComment(${comment.id})">Edit</button>
                                            <button class="comment-action-btn danger" type="button" onclick="board.deleteComment(${comment.id})">Delete</button>
                                          `
                                }
                            </div>
                        ` : ''}
                    </div>

                    ${
                        isEditing
                            ? `
                                <textarea
                                    class="comment-edit-input"
                                    id="comment-edit-input-${comment.id}"
                                    rows="3"
                                    oninput="board.setEditingCommentBody(this.value)"
                                >${esc(editingCommentBody)}</textarea>
                              `
                            : `
                                <div class="comment-body">${esc(comment.body)}</div>
                              `
                    }
                </div>
            `;
        }).join('');

        if (editingCommentId !== null) {
            const input = document.getElementById(`comment-edit-input-${editingCommentId}`);
            if (input) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        }
    }

async function addComment() {
    if (!sidebarId) return;

    const input = document.getElementById('comment-input');
    if (!input) return;

    const body = input.value.trim();
    if (!body) {
        showToast('Comment cannot be empty');
        return;
    }

    try {
        const created = await apiFetch(`${API_ROOT}/create_comment.php`, {
            method: 'POST',
            body: JSON.stringify({
                task_id: sidebarId,
                body,
            }),
        });

        comments.push(created);
        renderComments();
        input.value = '';
        showToast('Comment added');
    } catch (e) {
        console.error('addComment:', e);
        showToast(e.message || 'Failed to add comment');
    }
}

function formatCommentTime(createdAt) {
    const iso = typeof createdAt === 'string' ? createdAt.replace(' ', 'T') : createdAt;
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function startEditComment(commentId) {
    const comment = comments.find(c => c.id == commentId);
    if (!comment) return;

    editingCommentId = commentId;
    editingCommentBody = comment.body;
    renderComments();
}

function cancelEditComment() {
    editingCommentId = null;
    editingCommentBody = '';
    renderComments();
}

function setEditingCommentBody(value) {
    editingCommentBody = value;
}

async function saveEditedComment(commentId) {
    const trimmed = editingCommentBody.trim();

    if (!trimmed) {
        showToast('Comment cannot be empty');
        return;
    }

    try {
        const updated = await apiFetch(`${API_ROOT}/update_comment.php`, {
            method: 'POST',
            body: JSON.stringify({
                id: commentId,
                body: trimmed,
            }),
        });

        const idx = comments.findIndex(c => c.id == commentId);
        if (idx !== -1) {
            comments[idx] = updated;
        }

        editingCommentId = null;
        editingCommentBody = '';
        renderComments();
        showToast('Comment updated');
    } catch (e) {
        console.error('saveEditedComment:', e);
        showToast(e.message || 'Failed to update comment');
    }
}

async function deleteComment(commentId) {
    const comment = comments.find(c => c.id == commentId);
    if (!comment) return;

    if (!confirm('Delete this comment?')) return;

    try {
        await apiFetch(`${API_ROOT}/delete_comment.php`, {
            method: 'POST',
            body: JSON.stringify({ id: commentId }),
        });

        comments = comments.filter(c => c.id != commentId);
        renderComments();
        showToast('Comment deleted');
    } catch (e) {
        console.error('deleteComment:', e);
        showToast(e.message || 'Failed to delete comment');
    }
}

    function triggerAttachmentPicker() {
        if (!sidebarId) return;
        document.getElementById('attachment-input')?.click();
    }

    async function uploadAttachment(input) {
        if (!sidebarId || !input?.files?.length) return;

        const file = input.files[0];
        const formData = new FormData();
        formData.append('task_id', String(sidebarId));
        formData.append('attachment', file);

        try {
            const created = await uploadFetch(`${API_ROOT}/upload_attachment.php`, formData);
            attachments.unshift(created);
            renderAttachments();
            showToast(`Attached "${created.original_name}"`);
        } catch (e) {
            console.error('uploadAttachment:', e);
            showToast(e.message || 'Failed to upload attachment');
        } finally {
            input.value = '';
        }
    }

    async function deleteAttachment(attachmentId) {
        const attachment = attachments.find((item) => item.id == attachmentId);
        if (!attachment) return;

        if (!confirm(`Delete attachment "${attachment.original_name}"?`)) return;

        try {
            await apiFetch(`${API_ROOT}/delete_attachment.php`, {
                method: 'POST',
                body: JSON.stringify({ id: attachmentId }),
            });

            attachments = attachments.filter((item) => item.id != attachmentId);
            renderAttachments();
            showToast('Attachment deleted');
        } catch (e) {
            console.error('deleteAttachment:', e);
            showToast(e.message || 'Failed to delete attachment');
        }
    }

    // ── Undo Delete (tasks) ────────────────────────────────────────────────────
    function clearPendingDelete() {
        if (pendingDeleteTimer) {
            clearTimeout(pendingDeleteTimer);
            pendingDeleteTimer = null;
        }
        pendingDelete = null;
    }

    function finalizeDelete(snapshot = pendingDelete) {
        if (!snapshot) return;

        const { task } = snapshot;

        apiFetch(`${API_ROOT}/delete_task.php`, {
            method: 'POST',
            body: JSON.stringify({ id: task.id }),
        })
            .then(() => {
                notify(`Deleted "${task.title}" permanently`);
            })
            .catch((e) => {
                console.error('finalizeDelete:', e);

                const alreadyRestored = tasks.some(t => t.id == task.id);
                if (!alreadyRestored) {
                    const insertAt = Math.min(snapshot.originalIndex, tasks.length);
                    tasks.splice(insertAt, 0, task);
                    renderBoard();
                }

                showToast('Failed to delete task permanently');
            })
            .finally(() => {
                if (pendingDelete && pendingDelete.task.id == task.id) {
                    clearPendingDelete();
                }
            });
    }

    function undoDelete() {
        if (!pendingDelete) return;

        const { task, originalIndex } = pendingDelete;
        const alreadyExists = tasks.some(t => t.id == task.id);

        if (!alreadyExists) {
            const insertAt = Math.min(originalIndex, tasks.length);
            tasks.splice(insertAt, 0, task);
            renderBoard();
        }

        showToast(`Restored "${task.title}"`);
        clearPendingDelete();
    }

    async function deleteTask() {
        if (!sidebarId) return;

        const task = tasks.find(t => t.id == sidebarId);
        if (!task) return;

        if (!confirm(`Delete "${task.title}"?`)) return;

        if (pendingDelete) {
            const previous = pendingDelete;
            clearPendingDelete();
            finalizeDelete(previous);
        }

        const originalIndex = tasks.findIndex(t => t.id == sidebarId);

        tasks = tasks.filter(t => t.id != sidebarId);
        renderBoard();
        closeSidebar();

        pendingDelete = { task, originalIndex };
        showUndoToast(`Deleted "${task.title}"`, undoDelete);

        pendingDeleteTimer = setTimeout(() => {
            const snapshot = pendingDelete;
            if (!snapshot) return;
            clearPendingDelete();
            finalizeDelete(snapshot);
        }, 5000);
    }

    // ── Add task modal ─────────────────────────────────────────────────────────
    function openAdd(status) {
        if (!currentBoardId) {
            showToast('No board selected');
            return;
        }

        addStatus = status;

        const title = document.getElementById('m-title');
        const desc = document.getElementById('m-desc');
        const tags = document.getElementById('m-tags');
        const priority = document.getElementById('m-priority');
        const assignee = document.getElementById('m-assignee');
        const overlay = document.getElementById('modal-overlay');

        if (title) title.value = '';
        if (desc) desc.value = '';
        if (tags) tags.value = '';
        if (priority) priority.value = 'mid';
        if (assignee) assignee.value = '';

        if (overlay) {
            overlay.style.display = 'flex';
        }

        setTimeout(() => {
            if (title) title.focus();
        }, 50);
    }

    function closeAdd() {
        const overlay = document.getElementById('modal-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async function createTask() {
        if (!currentBoardId) {
            showToast('No board selected');
            return;
        }

        const titleEl = document.getElementById('m-title');
        const descEl = document.getElementById('m-desc');
        const tagsEl = document.getElementById('m-tags');
        const priorityEl = document.getElementById('m-priority');
        const assigneeEl = document.getElementById('m-assignee');
        const spEl = document.getElementById('m-story-points');

        const title = titleEl ? titleEl.value.trim() : '';
        if (!title) {
            alert('Title is required.');
            return;
        }

        const tagsRaw = tagsEl ? tagsEl.value : '';
        const tags = tagsRaw.split(',').map(t => t.trim()).filter(Boolean).join(',');
        const uid = assigneeEl ? assigneeEl.value : '';
        const spVal = spEl ? spEl.value : '';

        const payload = {
            board_id: currentBoardId,
            title,
            description: descEl ? descEl.value : '',
            status: addStatus,
            priority: priorityEl ? priorityEl.value : 'mid',
            assigned_to: uid ? parseInt(uid, 10) : null,
            tags,
            story_points: spVal ? parseInt(spVal, 10) : null,
        };

        try {
            const created = await apiFetch(`${API_ROOT}/create_task.php`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            tasks.push(created);
            renderBoard();
            notify(`Created "${title}"`);
            closeAdd();
        } catch (e) {
            console.error('createTask:', e);
            showToast('Failed to create task');
        }
    }

    // ── Column Manager ─────────────────────────────────────────────────────────
    // Render the admin-only column toolbar above the board. This panel allows
    // users to add, delete, and reorder workflow columns for the current board.
    function renderColumnManager() {
        const wrap = document.getElementById('column-manager');
        if (!wrap) return;

        // Only render for admin/sysadmin (IS_ADMIN comes from PHP in index.php)
        if (typeof IS_ADMIN === 'undefined' || !IS_ADMIN) {
            wrap.style.display = 'none';
            return;
        }

        wrap.style.display = 'flex';
        wrap.innerHTML = `
            <span class="section-label" style="align-self:center">Columns:</span>
            ${columns.map((c, idx) => `
                <div class="col-pill" draggable="true" data-col-id="${c.id}" style="border-color:${c.color};color:${c.color}">
                    <span class="col-pill-handle" title="Drag to reorder">⠿</span>
                    <span class="col-dot" style="background:${c.color};width:6px;height:6px;border-radius:50%;display:inline-block;margin:0 6px 0 4px"></span>
                    ${esc(c.name)}
                    ${columns.length > 1
                        ? `<button class="col-pill-del" type="button"
                               onclick="board.deleteColumn(${c.id}, '${esc(c.name)}')"
                               title="Delete column">×</button>`
                        : ''}
                </div>
            `).join('')}
            <button class="add-col-btn" type="button" id="add-col-btn">+ Column</button>
        `;

        document.getElementById('add-col-btn')?.addEventListener('click', showAddColumnModal);

        wrap.querySelectorAll('.col-pill').forEach(pill => {
            pill.addEventListener('dragstart', onColumnDragStart);
            pill.addEventListener('dragover', onColumnDragOver);
            pill.addEventListener('dragleave', onColumnDragLeave);
            pill.addEventListener('drop', onColumnDrop);
            pill.addEventListener('dragend', onColumnDragEnd);
        });
    }

    // Move a column left or right in the local ordering and persist the
    // updated order to the server.
    function moveColumn(colId, direction) {
        const idx = columns.findIndex(c => c.id == colId);
        if (idx === -1) return;

        const targetIdx = idx + direction;
        if (targetIdx < 0 || targetIdx >= columns.length) return;

        const [moved] = columns.splice(idx, 1);
        columns.splice(targetIdx, 0, moved);

        renderColumnManager();
        renderBoard();
        saveColumnOrder();
    }

    async function saveColumnOrder() {
        try {
            await apiFetch(`${API_ROOT}/reorder_columns.php`, {
                method: 'POST',
                body: JSON.stringify({ order: columns.map(c => c.id) }),
            });
        } catch (e) {
            console.error('saveColumnOrder:', e);
            showToast('Failed to save column order');
        }
    }

    // Drag/drop handlers for column reorder inside the admin toolbar.
    function onColumnDragStart(e) {
        draggingColumnId = Number(e.currentTarget.dataset.colId);
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(draggingColumnId));
    }

    function onColumnDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('drag-over');
        e.dataTransfer.dropEffect = 'move';
    }

    function onColumnDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    function onColumnDrop(e) {
        e.preventDefault();
        const target = e.currentTarget;
        target.classList.remove('drag-over');

        const sourceId = Number(e.dataTransfer.getData('text/plain'));
        const targetId = Number(target.dataset.colId);
        if (!sourceId || sourceId === targetId) return;

        const sourceIndex = columns.findIndex(c => c.id === sourceId);
        const targetIndex = columns.findIndex(c => c.id === targetId);
        if (sourceIndex === -1 || targetIndex === -1) return;

        const [moved] = columns.splice(sourceIndex, 1);
        columns.splice(targetIndex, 0, moved);

        renderColumnManager();
        renderBoard();
        saveColumnOrder();
    }

    function onColumnDragEnd(e) {
        draggingColumnId = null;
        e.currentTarget.classList.remove('dragging');
        document.querySelectorAll('.col-pill').forEach(p => p.classList.remove('drag-over'));
    }

    function showAddColumnModal() {
        const existing = document.getElementById('col-modal-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'col-modal-overlay';
        overlay.className = 'overlay';
        overlay.style.display = 'flex';
        overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };

        overlay.innerHTML = `
            <div class="modal" onclick="event.stopPropagation()" style="max-width:340px">
                <span class="section-label">New Column</span>
                <div style="margin-top:14px">
                    <div class="field-label" style="margin-bottom:6px">Column Name</div>
                    <input type="text" id="col-name-inp" placeholder="e.g. Review, QA, Blocked…" maxlength="50" autofocus>
                </div>
                <div style="margin-top:12px">
                    <div class="field-label" style="margin-bottom:6px">Accent Color</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap" id="col-color-row">
                        ${['#666666','#e8c84a','#4ae8a3','#4a9ee8','#e8734a','#e84a4a','#7e4ae8','#e84a9e','#a3e84a','#4ae8e8']
                          .map((c, i) => `
                            <label style="cursor:pointer">
                                <input type="radio" name="col-color" value="${c}"
                                       style="position:absolute;opacity:0;width:0;height:0"
                                       ${i === 0 ? 'checked' : ''}>
                                <span class="col-color-dot" style="background:${c};width:22px;height:22px;border-radius:50%;display:inline-block;border:3px solid transparent;transition:border-color .12s;box-sizing:border-box"
                                      data-color="${c}"></span>
                            </label>`).join('')}
                    </div>
                </div>
                <div class="modal-footer" style="margin-top:16px">
                    <button class="btn-primary" type="button" id="col-create-btn">Add Column</button>
                    <button class="btn-ghost" type="button" onclick="document.getElementById('col-modal-overlay').remove()">Cancel</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Highlight selected color swatch
        overlay.querySelectorAll('input[name="col-color"]').forEach(radio => {
            radio.addEventListener('change', () => {
                overlay.querySelectorAll('.col-color-dot').forEach(dot => {
                    dot.style.borderColor = dot.dataset.color === radio.value ? 'white' : 'transparent';
                });
            });
        });
        // Init first selection
        const first = overlay.querySelector('.col-color-dot');
        if (first) first.style.borderColor = 'white';

        document.getElementById('col-create-btn').addEventListener('click', submitAddColumn);
        document.getElementById('col-name-inp').addEventListener('keydown', e => {
            if (e.key === 'Enter') submitAddColumn();
        });
    }

    async function submitAddColumn() {
        const nameEl  = document.getElementById('col-name-inp');
        const colorEl = document.querySelector('input[name="col-color"]:checked');
        const name    = nameEl ? nameEl.value.trim() : '';
        const color   = colorEl ? colorEl.value : '#888888';

        if (!name) { showToast('Column name is required'); return; }

        try {
            const col = await apiFetch(`${API_ROOT}/create_column.php`, {
                method: 'POST',
                body: JSON.stringify({ board_id: currentBoardId, name, color }),
            });
            columns.push(col);
            document.getElementById('col-modal-overlay')?.remove();
            renderColumnManager();
            renderBoard();
            notify(`Column "${col.name}" added`);
        } catch (e) {
            showToast(e.message || 'Failed to add column');
        }
    }

    // Delete a workflow column and migrate its tasks to the first remaining column.
    // After the server confirms the move, we reload tasks from the API to ensure
    // our local status values stay in sync with whatever the server picked as fallback.
    async function deleteColumn(colId, colName) {
        if (!confirm(`Delete column "${colName}"? Tasks in it will move to the first remaining column.`)) return;

        // Find the status_key for this column before removing it from local state.
        // The display name (colName) cannot be used for status comparisons.
        const colToDelete = columns.find(c => c.id == colId);

        try {
            const res = await apiFetch(`${API_ROOT}/delete_column.php`, {
                method: 'POST',
                body: JSON.stringify({ id: colId }),
            });
            columns = columns.filter(c => c.id != colId);

            // Update local task statuses: move tasks that were in the deleted
            // column to the fallback status returned by the server.
            if (colToDelete && res.tasks_moved_to) {
                tasks.forEach(t => {
                    if (t.status === colToDelete.status_key) {
                        t.status = res.tasks_moved_to;
                    }
                });
            } else {
                // If we can't update locally, reload from server to stay consistent.
                await loadTasks();
            }

            renderColumnManager();
            renderBoard();
            notify(`Column "${colName}" deleted`);
        } catch (e) {
            showToast(e.message || 'Failed to delete column');
        }
    }

    // ── Assignee Select ────────────────────────────────────────────────────────
    function populateAssigneeSelect() {
        const sel = document.getElementById('m-assignee');
        if (!sel) return;

        sel.innerHTML =
            '<option value="">Unassigned</option>' +
            users.map(u => `<option value="${u.id}">${esc(u.display_name)}</option>`).join('');
    }

    // ── Search ─────────────────────────────────────────────────────────────────
    function bindSearch() {
        const inp = document.getElementById('search-input');
        if (!inp) return;

        inp.addEventListener('input', () => {
            searchVal = inp.value;
            renderBoard();
        });
    }

    // ── Theme ──────────────────────────────────────────────────────────────────
    const ALL_THEME_CLASSES = ['light-mode','midnight-mode','forest-mode','rose-mode'];

    function applyTheme(id) {
        const body = document.body;
        body.classList.remove(...ALL_THEME_CLASSES);
        if (id === 'light')    body.classList.add('light-mode');
        if (id === 'midnight') body.classList.add('midnight-mode');
        if (id === 'forest')   body.classList.add('forest-mode');
        if (id === 'rose')     body.classList.add('rose-mode');
        localStorage.setItem('theme', id);
    }

    function applySavedTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(savedTheme);
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.textContent = savedTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
    }

    function bindThemeToggle() {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            const current = localStorage.getItem('theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            toggle.textContent = next === 'dark' ? 'Light Mode' : 'Dark Mode';
        });
    }

    // ── Keyboard shortcuts ─────────────────────────────────────────────────────
    function bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            const active = document.activeElement;
            const tag = active?.tagName?.toLowerCase();
            const isTyping =
                tag === 'input' ||
                tag === 'textarea' ||
                tag === 'select' ||
                active?.isContentEditable;

            const modalOpen = document.getElementById('modal-overlay')?.style.display === 'flex';
            const sidebarOpen = document.getElementById('sidebar-overlay')?.style.display === 'flex';
            const input = document.getElementById('comment-input');
            if (!input) return;

            if (e.key === 'Enter' && !e.shiftKey && document.activeElement === input) {
                e.preventDefault();
                addComment();
            }

            if (e.key === 'Escape') {
                if (modalOpen) {
                    closeAdd();
                    e.preventDefault();
                    return;
                }

                if (sidebarOpen) {
                    closeSidebar();
                    e.preventDefault();
                    return;
                }
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                document.getElementById('search-input')?.focus();
                return;
            }

            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                document.getElementById('search-input')?.focus();
                return;
            }

            if (e.key.toLowerCase() === 'n' && !isTyping) {
                e.preventDefault();
                openAdd(columns[0]?.status_key || 'todo');
                return;
            }

            if (e.key.toLowerCase() === 't' && !isTyping) {
                e.preventDefault();
                document.getElementById('theme-toggle')?.click();
            }
        });
    }

    // ── Notifications ──────────────────────────────────────────────────────────
    function notify(msg) {
        const n = { msg, ts: new Date(), read: false };
        notifications.unshift(n);

        if (notifications.length > 30) {
            notifications.pop();
        }

        updateNotifBadge();
        showToast(msg);
    }

    function updateNotifBadge() {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;

        const unread = notifications.filter(n => !n.read).length;
        badge.textContent = unread;
        badge.style.display = unread ? 'block' : 'none';
    }

    function bindNotifPanel() {
        const btn = document.getElementById('notif-btn');
        const panel = document.getElementById('notif-panel');
        const markAllRead = document.getElementById('mark-all-read');

        if (!btn || !panel) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = panel.style.display === 'block';

            panel.style.display = open ? 'none' : 'block';

            if (!open) {
                notifications = notifications.map(n => ({ ...n, read: true }));
                updateNotifBadge();
                renderNotifList();
            }
        });

        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !panel.contains(e.target)) {
                panel.style.display = 'none';
            }
        });

        if (markAllRead) {
            markAllRead.addEventListener('click', () => {
                notifications = notifications.map(n => ({ ...n, read: true }));
                updateNotifBadge();
                renderNotifList();
            });
        }
    }

    function renderNotifList() {
        const el = document.getElementById('notif-list');
        if (!el) return;

        if (!notifications.length) {
            el.innerHTML = `<div class="notif-empty">No notifications yet</div>`;
            return;
        }

        el.innerHTML = notifications.map(n => `
            <div class="notif-item ${n.read ? '' : 'unread'}">
                <div class="notif-dot" style="background:${n.read ? '#2a2a2a' : '#e8734a'}"></div>
                <div>
                    <div class="notif-msg">${esc(n.msg)}</div>
                    <div class="notif-time">${n.ts.toLocaleTimeString()}</div>
                </div>
            </div>
        `).join('');
    }

    // ── Toasts ─────────────────────────────────────────────────────────────────
    function showToast(msg) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const el = document.createElement('div');
        el.className = 'toast';
        el.textContent = msg;
        container.appendChild(el);

        setTimeout(() => {
            el.remove();
        }, 3500);
    }

    function showUndoToast(msg, onUndo) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const el = document.createElement('div');
        el.className = 'toast undo-toast';

        const text = document.createElement('span');
        text.textContent = msg;

        const btn = document.createElement('button');
        btn.className = 'toast-undo-btn';
        btn.type = 'button';
        btn.textContent = 'Undo';
        btn.addEventListener('click', () => {
            onUndo();
            el.remove();
        });

        el.appendChild(text);
        el.appendChild(btn);
        container.appendChild(el);

        setTimeout(() => {
            if (el.parentNode) {
                el.remove();
            }
        }, 5000);
    }

    // ── Utilities ──────────────────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function taskAge(createdAt) {
        // SQLite returns "YYYY-MM-DD HH:MM:SS" (space separator). The space is
        // not valid ISO 8601, so Safari parses it as Invalid Date → NaN → "-1d ago".
        // Replacing the space with "T" gives a universally-parseable string.
        const iso = typeof createdAt === 'string' ? createdAt.replace(' ', 'T') : createdAt;
        const date = new Date(iso).getTime();
        if (Number.isNaN(date)) return '';

        const ms = Date.now() - date;
        if (ms < 0) return 'just now';               // clock skew guard
        const mins  = Math.floor(ms / 60000);
        const hours = Math.floor(ms / 3600000);
        const days  = Math.floor(ms / 86400000);
        if (mins  <  1) return 'just now';
        if (mins  < 60) return `${mins}m ago`;
        if (hours < 24) return `${hours}h ago`;
        return `${days}d ago`;
    }

    // ── Live polling ───────────────────────────────────────────────────────────
    // Fetch the current board's tasks silently. If the serialised task list has
    // changed since the last fetch, re-render the board so collaborators' edits
    // appear automatically. We avoid a full re-render when nothing changed so
    // an active drag-and-drop or open sidebar is not disrupted needlessly.
    async function pollTasks() {
        if (!currentBoardId) return;
        try {
            const fresh = await apiFetch(
                `${API_ROOT}/get_tasks.php?board_id=${encodeURIComponent(currentBoardId)}`
            );
            const json = JSON.stringify(fresh);
            if (json !== _lastTasksJson) {
                _lastTasksJson = json;
                tasks = fresh;
                renderBoard();
            }
        } catch (e) {
            // Silent — network hiccup; will retry next poll cycle.
            console.warn('pollTasks:', e);
        }
    }

    // ── bfcache recovery ───────────────────────────────────────────────────────
    // Called when the browser restores this page from the back/forward cache.
    // The DOM is intact but all in-memory state (tasks, boards, columns) may be
    // stale.  Re-fetch everything and re-render so the board is up-to-date.
    async function reloadBoardData() {
        try {
            await loadBoards();
            await Promise.all([loadColumns(), loadTasks()]);
            renderBoardList();
            renderCurrentBoardName();
            renderTeamAvatars();
            renderColumnManager();
        } catch (e) {
            console.error('reloadBoardData:', e);
        }
    }

    // ── Public API ─────────────────────────────────────────────────────────────
    return {
        init,
        onDragOver,
        onDragLeave,
        onDrop,
        openSidebar,
        closeSidebar,
        setSidebarField,
        saveTask,
        deleteTask,
        openAdd,
        closeAdd,
        createTask,
        switchBoard,
        showDeleteBoardModal,
        deleteBoard,
        addComment,
        startEditComment,
        cancelEditComment,
        setEditingCommentBody,
        saveEditedComment,
        deleteComment,
        triggerAttachmentPicker,
        uploadAttachment,
        deleteAttachment,
        deleteColumn,
        moveColumn,
        pollTasks,
        reloadBoardData,
    };

})();

// ── Bootstrap ──────────────────────────────────────────────────────────────────
// We need two entry points:
//  1. Normal page load  → DOMContentLoaded fires, board.init() runs.
//  2. bfcache restore   → the browser revives a frozen snapshot; DOMContentLoaded
//     does NOT fire again. We catch this with the `pageshow` event whose
//     `persisted` flag is true when the page came from the back/forward cache.
//     Mobile Safari, Chrome Android, and Firefox all use bfcache aggressively.

let _initialized = false;

document.addEventListener('DOMContentLoaded', () => {
    _initialized = true;
    board.init().then(startLivePolling);
});

window.addEventListener('pageshow', (e) => {
    if (e.persisted && _initialized) {
        // Page was restored from bfcache — force a full data reload.
        board.reloadBoardData();
    }
});

// ── Live polling ───────────────────────────────────────────────────────────────
// Poll every 1 s while the tab is visible. When the tab is hidden (user
// switches apps on mobile) we pause to save battery/data, and trigger an
// immediate reload the moment the tab becomes visible again.

const POLL_INTERVAL_MS = 1_000;
let _pollTimer = null;

function startLivePolling() {
    schedulePoll();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            // Tab just became visible — fetch immediately, then resume timer.
            runPoll();
        } else {
            // Tab hidden — stop the timer to avoid wasted requests.
            clearTimeout(_pollTimer);
            _pollTimer = null;
        }
    });
}

function schedulePoll() {
    clearTimeout(_pollTimer);
    _pollTimer = setTimeout(runPoll, POLL_INTERVAL_MS);
}

async function runPoll() {
    await board.pollTasks();
    schedulePoll();
}
