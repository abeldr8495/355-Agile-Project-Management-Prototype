/**
 * Agile Board — board.js
 * Vanilla JS: fetch-based API calls, HTML5 drag-and-drop, sidebar, modal, toasts.
 */
const board = (() => {

    // ── State ──────────────────────────────────────────────────────────────────
    let tasks      = [];
    let users      = [];
    let draggingId = null;
    let sidebarId  = null;
    let addStatus  = 'todo';
    let searchVal  = '';
    let notifications = [];

    const PRIORITIES = [
        { id: 'low',  label: 'Low',      color: '#4ae8a3' },
        { id: 'mid',  label: 'Mid',      color: '#e8c84a' },
        { id: 'high', label: 'High',     color: '#e8734a' },
        { id: 'crit', label: 'Critical', color: '#e84a4a' },
    ];
    const COLUMNS = [
        { id: 'todo',       label: 'To Do',       accent: '#555' },
        { id: 'inprogress', label: 'In Progress',  accent: '#e8c84a' },
        { id: 'done',       label: 'Done',         accent: '#4ae8a3' },
    ];

    // Determine dynamic API root for deployments under subfolders
    const API_ROOT = `${window.location.pathname.replace(/\/[^/]*$/, '')}/api`;

    // ── Init ───────────────────────────────────────────────────────────────────
    // Initialize app state on DOM ready
    // - Fetch users and tasks in parallel
    // - Render static UI elements (avatars, bindings)
    // - Hook global new task button
    async function init() {
        await Promise.all([loadUsers(), loadTasks()]);
        renderTeamAvatars();
        bindSearch();
        bindNotifPanel();
        document.getElementById('new-task-btn').addEventListener('click', () => openAdd('todo'));
    }

    // ── API helpers ────────────────────────────────────────────────────────────
    // Shared fetch wrapper for JSON API endpoints
    async function apiFetch(url, opts = {}) {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json' },
            ...opts,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // Load user directory for assignee lists
    async function loadUsers() {
        try {
            users = await apiFetch(`${API_ROOT}/get_users.php`);
            populateAssigneeSelect();
        } catch (e) { console.error('loadUsers:', e); }
    }

    // Load all tasks and render board columns
    async function loadTasks() {
        try {
            tasks = await apiFetch(`${API_ROOT}/get_tasks.php`);
            console.debug('loadTasks:', tasks.length, 'tasks loaded');
            renderBoard();
        } catch (e) { console.error('loadTasks:', e); }
    }

    // ── Rendering ─────────────────────────────────────────────────────────────
    // ── Rendering ─────────────────────────────────────────────────────────────
    // Rebuild board columns from current task state and search filter
    function renderBoard() {
        const query = searchVal.toLowerCase();
        const visible = tasks.filter(t =>
            !query ||
            t.title.toLowerCase().includes(query) ||
            (t.tags || '').toLowerCase().includes(query)
        );

        COLUMNS.forEach(col => {
            const list  = document.getElementById(`list-${col.id}`);
            const count = document.getElementById(`count-${col.id}`);
            const colTasks = visible.filter(t => t.status === col.id);
            count.textContent = colTasks.length;
            list.innerHTML = '';
            colTasks.forEach(t => list.appendChild(makeCard(t)));
        });

        updateProgress();
    }

    // Create DOM element for a single task card, including drag handles, metadata badges, and click handler
    function makeCard(task) {
        const user = users.find(u => u.id == task.assigned_to);
        const pri  = PRIORITIES.find(p => p.id === task.priority);
        const tags = (task.tags || '').split(',').map(t => t.trim()).filter(Boolean);
        const ageStr = taskAge(task.created_at);

        const card = document.createElement('div');
        card.className = 'task-card';
        card.dataset.id = task.id;
        card.draggable = true;

        card.innerHTML = `
            <div class="card-top">
                <div class="card-title">${esc(task.title)}</div>
                ${user
                    ? `<div class="card-avatar" title="${esc(user.display_name)}"
                           style="background:${user.color}22;border-color:${user.color};color:${user.color}">
                           ${esc(user.avatar)}
                       </div>`
                    : `<div class="card-avatar" style="background:#1a1a1a;border-color:#2a2a2a"></div>`}
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
        card.addEventListener('dragend',   onDragEnd);
        card.addEventListener('click',     () => openSidebar(task.id));

        return card;
    }

    // Render compact team avatars in header from users list
    function renderTeamAvatars() {
        const el = document.getElementById('team-avatars');
        el.innerHTML = users.map((u, i) =>
            `<div class="team-avatar" title="${esc(u.display_name)}"
                  style="margin-left:${i ? '-6px' : '0'};background:${u.color}22;border:1.5px solid ${u.color};color:${u.color}">
                ${esc(u.avatar)}
            </div>`
        ).join('');
    }

    // Update sprint progress meter and percentage label based on done tasks
    function updateProgress() {
        const total = tasks.length;
        const done  = tasks.filter(t => String(t.status).trim().toLowerCase() === 'done').length;
        const pct   = total ? Math.round((done / total) * 100) : 0;
        console.debug('updateProgress:', { total, done, pct, tasks: tasks.map(t => ({ id: t.id, status: t.status })) });
        document.getElementById('sprint-pct').textContent   = `${pct}%`;
        document.getElementById('progress-fill').style.width = `${pct}%`;
    }

    // ── Drag & Drop ────────────────────────────────────────────────────────────
    // ── Drag & Drop ────────────────────────────────────────────────────────────
    // Begin dragging a task card
    function onDragStart(e, id) {
        draggingId = id;
        e.dataTransfer.effectAllowed = 'move';
        e.currentTarget.classList.add('dragging');
    }

    // Clean-up after drag operation finishes
    function onDragEnd(e) {
        draggingId = null;
        document.querySelectorAll('.task-card').forEach(c => c.classList.remove('dragging'));
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));
    }

    function onDragOver(e, status) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));
        document.getElementById(`list-${status}`).classList.add('drag-over');
    }

    // Remove drag-over highlight when card exits column
    function onDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    // Handle drop event to update task status and save to backend
    async function onDrop(e, status) {
        e.preventDefault();
        document.querySelectorAll('.task-list').forEach(l => l.classList.remove('drag-over'));
        if (!draggingId) return;

        const task = tasks.find(t => t.id == draggingId);
        if (!task || task.status === status) { draggingId = null; return; }

        const oldStatus = task.status;
        task.status = status;  // Update local state immediately
        console.debug('onDrop: task', task.id, 'status changed from', oldStatus, 'to', status);
        renderBoard();  // Re-render with new status

        const colLabel = COLUMNS.find(c => c.id === status).label;
        try {
            await apiFetch(`${API_ROOT}/update_task.php`, {
                method: 'POST',
                body: JSON.stringify({ id: draggingId, status }),
            });
            notify(`"${task.title}" moved to ${colLabel}`);
            if (sidebarId == task.id) renderSidebarFields(task);
        } catch (e) {
            console.error('onDrop API error:', e);
            showToast('Failed to move task');
            // Revert local change on failure
            task.status = oldStatus;
            renderBoard();
        }
        draggingId = null;
    }

    // ── Sidebar ────────────────────────────────────────────────────────────────
    // ── Sidebar / task detail ─────────────────────────────────────────────────
    // Populate and open sidebar to edit a task
    function openSidebar(id) {
        sidebarId = id;
        const task = tasks.find(t => t.id == id);
        if (!task) return;

        document.getElementById('s-title').value = task.title;
        document.getElementById('s-desc').value  = task.description || '';
        document.getElementById('s-tags').value  =
            (task.tags || '').split(',').map(t => t.trim()).filter(Boolean).join(', ');

        renderSidebarFields(task);
        document.getElementById('sidebar-overlay').style.display = 'flex';
    }

    // Render sidebar controls for priority/status/assignee based on task state
    function renderSidebarFields(task) {
        // Priority pills
        const pg = document.getElementById('s-priority-group');
        pg.innerHTML = PRIORITIES.map(p => {
            const active = task.priority === p.id;
            return `<button class="pill-btn" onclick="board.setSidebarField('priority','${p.id}')"
                style="border-color:${active ? p.color : ''};background:${active ? p.color + '22' : ''};color:${active ? p.color : ''}">
                ${p.label}</button>`;
        }).join('');

        // Status pills
        const sg = document.getElementById('s-status-group');
        sg.innerHTML = COLUMNS.map(c => {
            const active = task.status === c.id;
            return `<button class="pill-btn" onclick="board.setSidebarField('status','${c.id}')"
                style="border-color:${active ? c.accent : ''};background:${active ? c.accent + '18' : ''};color:${active ? c.accent : ''}">
                ${c.label}</button>`;
        }).join('');

        // Assignee pills
        const ag = document.getElementById('s-assignee-group');
        const noneActive = !task.assigned_to;
        ag.innerHTML = `<button class="pill-btn" onclick="board.setSidebarField('assigned_to',null)"
            style="border-color:${noneActive ? '#aaa' : ''};color:${noneActive ? '#ccc' : ''}">
            None</button>`;
        ag.innerHTML += users.map(u => {
            const active = task.assigned_to == u.id;
            return `<button class="pill-btn" onclick="board.setSidebarField('assigned_to',${u.id})"
                style="border-color:${active ? u.color : ''};background:${active ? u.color + '18' : ''};color:${active ? u.color : ''}">
                ${esc(u.display_name)}</button>`;
        }).join('');
    }

    // Update local task object when sidebar pill is toggled, no server update yet
    function setSidebarField(field, value) {
        const task = tasks.find(t => t.id == sidebarId);
        if (!task) return;
        task[field] = value;
        renderSidebarFields(task);
    }

    // Hide sidebar overlay
    function closeSidebar() {
        sidebarId = null;
        document.getElementById('sidebar-overlay').style.display = 'none';
    }

    // Save task details from sidebar to backend API and refresh UI
    async function saveTask() {
        const task = tasks.find(t => t.id == sidebarId);
        if (!task) return;

        const title = document.getElementById('s-title').value.trim();
        if (!title) { alert('Title is required.'); return; }

        const tagsRaw = document.getElementById('s-tags').value;
        const tags    = tagsRaw.split(',').map(t => t.trim()).filter(Boolean).join(',');

        const payload = {
            id:          task.id,
            title,
            description: document.getElementById('s-desc').value,
            status:      task.status,
            priority:    task.priority,
            assigned_to: task.assigned_to || null,
            tags,
        };

        try {
            const updated = await apiFetch(`${API_ROOT}/update_task.php`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const idx = tasks.findIndex(t => t.id == sidebarId);
            tasks[idx] = { ...tasks[idx], ...updated };
            renderBoard();
            notify(`Updated "${title}"`);
            closeSidebar();
        } catch (e) {
            showToast('Failed to save task');
        }
    }

    // Delete task via API from sidebar action and remove it from local state/UI
    async function deleteTask() {
        if (!sidebarId) return;
        const task = tasks.find(t => t.id == sidebarId);
        if (!confirm(`Delete "${task?.title}"?`)) return;

        try {
            await apiFetch(`${API_ROOT}/delete_task.php`, {
                method: 'POST',
                body: JSON.stringify({ id: sidebarId }),
            });
            tasks = tasks.filter(t => t.id != sidebarId);
            renderBoard();
            notify(`Deleted "${task?.title}"`);
            closeSidebar();
        } catch (e) {
            showToast('Failed to delete task');
        }
    }

    // ── Add task modal ─────────────────────────────────────────────────────────
    // ── Add task modal ─────────────────────────────────────────────────────────
    // Open new task modal with default status preselected
    function openAdd(status) {
        addStatus = status;
        document.getElementById('m-title').value    = '';
        document.getElementById('m-desc').value     = '';
        document.getElementById('m-tags').value     = '';
        document.getElementById('m-priority').value = 'mid';
        document.getElementById('m-assignee').value = '';
        document.getElementById('modal-overlay').style.display = 'flex';
        setTimeout(() => document.getElementById('m-title').focus(), 50);
    }

    function closeAdd() {
        document.getElementById('modal-overlay').style.display = 'none';
    }

    // Create a new task through API and inject into current board state
    async function createTask() {
        const title = document.getElementById('m-title').value.trim();
        if (!title) { alert('Title is required.'); return; }

        const tagsRaw = document.getElementById('m-tags').value;
        const tags    = tagsRaw.split(',').map(t => t.trim()).filter(Boolean).join(',');
        const uid     = document.getElementById('m-assignee').value;

        const payload = {
            title,
            description: document.getElementById('m-desc').value,
            status:      addStatus,
            priority:    document.getElementById('m-priority').value,
            assigned_to: uid ? parseInt(uid) : null,
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
            showToast('Failed to create task');
        }
    }

    // ── Assignee select in modal ───────────────────────────────────────────────
    // Populate assignee dropdown in add task modal from users list
    function populateAssigneeSelect() {
        const sel = document.getElementById('m-assignee');
        sel.innerHTML = '<option value="">Unassigned</option>' +
            users.map(u => `<option value="${u.id}">${esc(u.display_name)}</option>`).join('');
    }

    // ── Search ─────────────────────────────────────────────────────────────────
    // ── Search ─────────────────────────────────────────────────────────────────
    // Bind search input to live filtering of task cards
    function bindSearch() {
        const inp = document.getElementById('search-input');
        inp.addEventListener('input', () => {
            searchVal = inp.value;
            renderBoard();
        });
    }

    // ── Notifications ──────────────────────────────────────────────────────────
    // ── Notifications ──────────────────────────────────────────────────────────
    // Add an in-memory notification and show toast (non-persisted, user-facing)
    function notify(msg) {
        const n = { msg, ts: new Date(), read: false };
        notifications.unshift(n);
        if (notifications.length > 30) notifications.pop();
        updateNotifBadge();
        showToast(msg);
    }

    // Update notification badge count in header
    function updateNotifBadge() {
        const badge = document.getElementById('notif-badge');
        const unread = notifications.filter(n => !n.read).length;
        badge.textContent = unread;
        badge.style.display = unread ? 'block' : 'none';
    }

    function bindNotifPanel() {
        const btn   = document.getElementById('notif-btn');
        const panel = document.getElementById('notif-panel');

        btn.addEventListener('click', e => {
            e.stopPropagation();
            const open = panel.style.display === 'block';
            panel.style.display = open ? 'none' : 'block';
            if (!open) {
                notifications = notifications.map(n => ({ ...n, read: true }));
                updateNotifBadge();
                renderNotifList();
            }
        });

        document.addEventListener('click', e => {
            if (!btn.contains(e.target) && !panel.contains(e.target)) {
                panel.style.display = 'none';
            }
        });

        document.getElementById('mark-all-read').addEventListener('click', () => {
            notifications = notifications.map(n => ({ ...n, read: true }));
            updateNotifBadge();
            renderNotifList();
        });
    }

    // Populate notification dropdown list UI
    function renderNotifList() {
        const el = document.getElementById('notif-list');
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
            </div>`).join('');
    }

    // ── Toast ──────────────────────────────────────────────────────────────────
    // ── Toast ──────────────────────────────────────────────────────────────────
    // Display transient toast messages at bottom-right
    function showToast(msg) {
        const container = document.getElementById('toast-container');
        const el = document.createElement('div');
        el.className = 'toast';
        el.textContent = msg;
        container.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    // ── Utilities ──────────────────────────────────────────────────────────────
    // ── Utilities ──────────────────────────────────────────────────────────────
    // Basic HTML escaping for display values
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Convert task created_at date to human-friendly age label
    function taskAge(createdAt) {
        const ms   = Date.now() - new Date(createdAt).getTime();
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
    };

})();

// Start on DOM ready
document.addEventListener('DOMContentLoaded', board.init);
