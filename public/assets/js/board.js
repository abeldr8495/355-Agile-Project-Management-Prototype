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
    let draggingId = null;
    let sidebarId = null;
    let addStatus = 'todo';
    let searchVal = '';
    let notifications = [];
    let editingCommentId = null;
    let editingCommentBody = '';

    // Undo-delete state (tasks)
    let pendingDelete = null;
    let pendingDeleteTimer = null;

    // Undo-delete state (boards)
    let pendingBoardDelete = null;
    let pendingBoardDeleteTimer = null;

    const PRIORITIES = [
        { id: 'low',  label: 'Low',      color: '#4ae8a3' },
        { id: 'mid',  label: 'Mid',      color: '#e8c84a' },
        { id: 'high', label: 'High',     color: '#e8734a' },
        { id: 'crit', label: 'Critical', color: '#e84a4a' },
    ];

    const COLUMNS = [
        { id: 'todo',       label: 'To Do',       accent: '#555' },
        { id: 'inprogress', label: 'In Progress', accent: '#e8c84a' },
        { id: 'done',       label: 'Done',        accent: '#4ae8a3' },
    ];

    // Determine dynamic API root for deployments under subfolders
    const API_ROOT = `${window.location.pathname.replace(/\/[^/]*$/, '')}/api`;

    // ── Init ───────────────────────────────────────────────────────────────────
    async function init() {
        applySavedTheme();

        await Promise.all([loadUsers(), loadBoards()]);
        await loadTasks();

        renderBoardList();
        renderCurrentBoardName();
        renderTeamAvatars();

        bindSearch();
        bindNotifPanel();
        bindKeyboardShortcuts();
        bindThemeToggle();
        bindBoardActions();

        const newTaskBtn = document.getElementById('new-task-btn');
        if (newTaskBtn) {
            newTaskBtn.addEventListener('click', () => openAdd('todo'));
        }
    }

    // ── API helpers ────────────────────────────────────────────────────────────
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

    async function loadUsers() {
        try {
            users = await apiFetch(`${API_ROOT}/get_users.php`);
            populateAssigneeSelect();
        } catch (e) {
            console.error('loadUsers:', e);
        }
    }

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

    // ── Boards ─────────────────────────────────────────────────────────────────
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

                <button
                    class="board-delete-btn"
                    type="button"
                    onclick="board.deleteBoard(${Number(b.id)})"
                    title="Delete board"
                    aria-label="Delete board ${esc(b.name)}">
                    ×
                </button>
            </div>
        `).join('');
    }

    function renderCurrentBoardName() {
        const el = document.getElementById('current-board-name');
        if (!el) return;

        const current = boards.find(b => String(b.id) === String(currentBoardId));
        el.textContent = current ? current.name : 'No Board';
    }

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
        await loadTasks();
    }

    function bindBoardActions() {
        const newBoardBtn = document.getElementById('new-board-btn');
        if (!newBoardBtn) return;

        newBoardBtn.addEventListener('click', async () => {
            const name = prompt('Enter a name for the new board:');

            if (name === null) return;

            const trimmed = name.trim();
            if (!trimmed) {
                showToast('Board name is required');
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

                renderBoardList();
                renderCurrentBoardName();
                await loadTasks();

                showToast(`Created board "${data.name}"`);
            } catch (e) {
                console.error('createBoard:', e);
                showToast(e.message || 'Failed to create board');
            }
        });
    }

    function clearPendingBoardDelete() {
        if (pendingBoardDeleteTimer) {
            clearTimeout(pendingBoardDeleteTimer);
            pendingBoardDeleteTimer = null;
        }
        pendingBoardDelete = null;
    }

    async function finalizeBoardDelete(snapshot = pendingBoardDelete) {
        if (!snapshot) return;

        try {
            await apiFetch(`${API_ROOT}/delete_board.php`, {
                method: 'POST',
                body: JSON.stringify({ id: snapshot.board.id }),
            });

            showToast(`Deleted "${snapshot.board.name}" permanently`);
        } catch (e) {
            console.error('finalizeBoardDelete:', e);

            const alreadyExists = boards.some(b => b.id == snapshot.board.id);
            if (!alreadyExists) {
                boards.splice(snapshot.originalIndex, 0, snapshot.board);
            }

            if (!currentBoardId && snapshot.previousBoardId) {
                currentBoardId = snapshot.previousBoardId;
                localStorage.setItem('currentBoardId', String(currentBoardId));
            }

            renderBoardList();
            renderCurrentBoardName();
            await loadTasks();

            showToast(e.message || 'Failed to delete board permanently');
        }
    }

    async function undoBoardDelete() {
        if (!pendingBoardDelete) return;

        const { board, originalIndex, previousBoardId } = pendingBoardDelete;
        const alreadyExists = boards.some(b => b.id == board.id);

        if (!alreadyExists) {
            boards.splice(originalIndex, 0, board);
        }

        currentBoardId = previousBoardId || board.id;
        localStorage.setItem('currentBoardId', String(currentBoardId));

        renderBoardList();
        renderCurrentBoardName();
        await loadTasks();

        showToast(`Restored "${board.name}"`);
        clearPendingBoardDelete();
    }

    async function deleteBoard(boardId) {
        const boardToDelete = boards.find(b => b.id == boardId);
        if (!boardToDelete) return;

        if (boards.length <= 1) {
            showToast('Cannot delete the last board');
            return;
        }

        if (!confirm(`Delete board "${boardToDelete.name}" and all its tasks?`)) return;

        // Finalize any previous pending board delete first
        if (pendingBoardDelete) {
            const previous = pendingBoardDelete;
            await finalizeBoardDelete(previous);
            clearPendingBoardDelete();
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

        renderBoardList();
        renderCurrentBoardName();
        await loadTasks();

        pendingBoardDelete = {
            board: boardToDelete,
            originalIndex,
            previousBoardId,
        };

        showUndoToast(`Deleted "${boardToDelete.name}"`, () => {
            undoBoardDelete();
        });

        pendingBoardDeleteTimer = setTimeout(async () => {
            const snapshot = pendingBoardDelete;
            if (!snapshot) return;

            await finalizeBoardDelete(snapshot);
            clearPendingBoardDelete();
        }, 5000);
    }

    // ── Rendering ──────────────────────────────────────────────────────────────
    function renderBoard() {
        const query = searchVal.toLowerCase();

        const visible = tasks.filter(t =>
            !query ||
            String(t.title || '').toLowerCase().includes(query) ||
            String(t.tags || '').toLowerCase().includes(query) ||
            String(t.description || '').toLowerCase().includes(query)
        );

        COLUMNS.forEach(col => {
            const list = document.getElementById(`list-${col.id}`);
            const count = document.getElementById(`count-${col.id}`);
            if (!list || !count) return;

            const colTasks = visible.filter(t => t.status === col.id);
            count.textContent = colTasks.length;
            list.innerHTML = '';
            colTasks.forEach(t => list.appendChild(makeCard(t)));
        });

        updateProgress(visible);
    }

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

    function updateProgress(taskSet = tasks) {
        const total = taskSet.length;
        const done = taskSet.filter(t => String(t.status).trim().toLowerCase() === 'done').length;
        const pct = total ? Math.round((done / total) * 100) : 0;

        const sprintPct = document.getElementById('sprint-pct');
        const progressFill = document.getElementById('progress-fill');

        if (sprintPct) sprintPct.textContent = `${pct}%`;
        if (progressFill) progressFill.style.width = `${pct}%`;
    }

    // ── Drag & Drop ────────────────────────────────────────────────────────────
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

        const colLabel = COLUMNS.find(c => c.id === status)?.label || status;

        try {
            await apiFetch(`${API_ROOT}/update_task.php`, {
                method: 'POST',
                body: JSON.stringify({ id: draggingId, status }),
            });

            notify(`"${task.title}" moved to ${colLabel}`);

            if (sidebarId == task.id) {
                renderSidebarFields(task);
                loadComments(task.id);

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

        renderSidebarFields(task);
        loadComments(task.id);

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
            sg.innerHTML = COLUMNS.map(c => {
                const active = task.status === c.id;
                return `<button class="pill-btn" type="button" onclick="board.setSidebarField('status','${c.id}')"
                    style="border-color:${active ? c.accent : ''};background:${active ? c.accent + '18' : ''};color:${active ? c.accent : ''}">
                    ${c.label}</button>`;
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

    const overlay = document.getElementById('sidebar-overlay');
    const commentsList = document.getElementById('comments-list');
    const commentInput = document.getElementById('comment-input');

    if (commentsList) commentsList.innerHTML = `<div class="comments-empty">No comments yet</div>`;
    if (commentInput) commentInput.value = '';

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

        const payload = {
            id: task.id,
            title,
            description: descEl ? descEl.value : '',
            status: task.status,
            priority: task.priority,
            assigned_to: task.assigned_to || null,
            tags,
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
    const date = new Date(createdAt);
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

        const title = titleEl ? titleEl.value.trim() : '';
        if (!title) {
            alert('Title is required.');
            return;
        }

        const tagsRaw = tagsEl ? tagsEl.value : '';
        const tags = tagsRaw.split(',').map(t => t.trim()).filter(Boolean).join(',');
        const uid = assigneeEl ? assigneeEl.value : '';

        const payload = {
            board_id: currentBoardId,
            title,
            description: descEl ? descEl.value : '',
            status: addStatus,
            priority: priorityEl ? priorityEl.value : 'mid',
            assigned_to: uid ? parseInt(uid, 10) : null,
            tags,
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
    function applySavedTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        const body = document.body;
        const toggle = document.getElementById('theme-toggle');

        if (savedTheme === 'light') {
            body.classList.add('light-mode');
            if (toggle) toggle.textContent = 'Dark Mode';
        } else {
            body.classList.remove('light-mode');
            if (toggle) toggle.textContent = 'Light Mode';
        }
    }

    function bindThemeToggle() {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            const body = document.body;
            const isLight = body.classList.toggle('light-mode');

            localStorage.setItem('theme', isLight ? 'light' : 'dark');
            toggle.textContent = isLight ? 'Dark Mode' : 'Light Mode';
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
                openAdd('todo');
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
        const date = new Date(createdAt).getTime();
        if (Number.isNaN(date)) return '';

        const ms = Date.now() - date;
        const days = Math.floor(ms / 86400000);
        return days === 0 ? 'today' : `${days}d ago`;
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
        deleteBoard,
        addComment,
        startEditComment,
        cancelEditComment,
        setEditingCommentBody,
        saveEditedComment,
        deleteComment,
    };

})();

// Start on DOM ready
document.addEventListener('DOMContentLoaded', board.init);