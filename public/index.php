<?php
// Main board page
require_once '../src/auth.php';
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div id="app-shell">

    <!-- ── LEFT SIDEBAR: BOARDS ── -->
    <aside id="boards-sidebar">
        <div class="boards-sidebar-head">
            <div>
                <div class="logo-mark"><span class="accent">▸</span> Agile Board</div>
                <div class="logo-sub">FINAL EDITION</div>
            </div>
            <button class="btn-ghost" id="new-board-btn" type="button">+ BOARD</button>
        </div>

        <div class="boards-sidebar-section">
            <span class="section-label">Boards</span>
            <div id="boards-list" class="boards-list">
                <!-- Filled by JS -->
            </div>
        </div>

        <div class="boards-sidebar-footer">
            <div class="user-menu">
                <div class="user-avatar" id="current-user-avatar"
                     style="background:<?= htmlspecialchars($user['color']) ?>22;border-color:<?= htmlspecialchars($user['color']) ?>;color:<?= htmlspecialchars($user['color']) ?>">
                    <?= htmlspecialchars($user['avatar']) ?>
                </div>
                <a href="/logout.php" class="logout-btn" title="Sign out">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- ── MAIN PANEL ── -->
    <div id="main-panel">

        <!-- ── HEADER ── -->
        <header id="header">
            <div class="header-left">
                <div class="current-board-wrap">
                    <span class="section-label">Current Board</span>
                    <div id="current-board-name" class="current-board-name">Board</div>
                </div>
            </div>

            <div class="header-center">
                <div class="search-wrap">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="search-input" placeholder="Search tasks…">
                </div>
            </div>

            <div class="header-right">
                <button class="btn-ghost" id="theme-toggle" type="button">Light Mode</button>

                <div class="notif-wrap">
                    <button class="notif-btn" id="notif-btn" title="Notifications" type="button">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-badge" id="notif-badge" style="display:none">0</span>
                    </button>

                    <div class="notif-panel" id="notif-panel" style="display:none">
                        <div class="notif-head">
                            <span class="section-label">Notifications</span>
                            <button class="link-btn" id="mark-all-read" type="button">Mark all read</button>
                        </div>
                        <div class="notif-list" id="notif-list"></div>
                    </div>
                </div>

                <button class="btn-primary" id="new-task-btn" type="button">+ NEW TASK</button>
            </div>
        </header>

        <!-- ── BOARD TOOLBAR ── -->
        <section class="board-toolbar">
            <div class="board-toolbar-left">
                <div class="sprint-progress">
                    <div class="sprint-labels">
                        <span class="sprint-label">SPRINT PROGRESS</span>
                        <span class="sprint-pct" id="sprint-pct">0%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                </div>
            </div>

            <div class="board-toolbar-right">
                <div class="team-avatars" id="team-avatars"><!-- filled by JS --></div>
            </div>
        </section>

        <!-- ── BOARD ── -->
        <main id="board">
            <div class="col" id="col-todo" data-status="todo">
                <div class="col-header">
                    <div class="col-dot" style="background:#555"></div>
                    <span class="col-title">To Do</span>
                    <span class="col-count" id="count-todo">0</span>
                </div>
                <div class="task-list" id="list-todo"
                     ondragover="board.onDragOver(event,'todo')"
                     ondragleave="board.onDragLeave(event)"
                     ondrop="board.onDrop(event,'todo')"></div>
                <button class="add-task-btn" type="button" onclick="board.openAdd('todo')">+ Add task</button>
            </div>

            <div class="col" id="col-inprogress" data-status="inprogress">
                <div class="col-header">
                    <div class="col-dot" style="background:#e8c84a"></div>
                    <span class="col-title">In Progress</span>
                    <span class="col-count" id="count-inprogress">0</span>
                </div>
                <div class="task-list" id="list-inprogress"
                     ondragover="board.onDragOver(event,'inprogress')"
                     ondragleave="board.onDragLeave(event)"
                     ondrop="board.onDrop(event,'inprogress')"></div>
                <button class="add-task-btn" type="button" onclick="board.openAdd('inprogress')">+ Add task</button>
            </div>

            <div class="col" id="col-done" data-status="done">
                <div class="col-header">
                    <div class="col-dot" style="background:#4ae8a3"></div>
                    <span class="col-title">Done</span>
                    <span class="col-count" id="count-done">0</span>
                </div>
                <div class="task-list" id="list-done"
                     ondragover="board.onDragOver(event,'done')"
                     ondragleave="board.onDragLeave(event)"
                     ondrop="board.onDrop(event,'done')"></div>
                <button class="add-task-btn" type="button" onclick="board.openAdd('done')">+ Add task</button>
            </div>
        </main>
    </div>
</div>

<!-- ── SIDEBAR (task detail) ── -->
<div id="sidebar-overlay" class="overlay" style="display:none" onclick="board.closeSidebar()">
    <div id="sidebar" class="sidebar" onclick="event.stopPropagation()">
        <div class="sidebar-head">
            <span class="section-label">Task Detail</span>
            <button class="close-btn" type="button" onclick="board.closeSidebar()">✕</button>
        </div>

        <input type="text" id="s-title" placeholder="Task title">

        <textarea id="s-desc" rows="4" placeholder="Description…"></textarea>

        <div>
            <span class="field-label">Priority</span>
            <div class="priority-group" id="s-priority-group"></div>
        </div>

        <div>
            <span class="field-label">Status</span>
            <div class="status-group" id="s-status-group"></div>
        </div>

        <div>
            <span class="field-label">Assignee</span>
            <div class="assignee-group" id="s-assignee-group"></div>
        </div>

        <div>
            <span class="field-label">Tags <span style="color:#3a3a3a;font-size:9px">(comma-separated)</span></span>
            <input type="text" id="s-tags" placeholder="frontend, backend, design…">
        </div>

        <div class="comments-section">
            <span class="field-label">Comments</span>

            <div id="comments-list" class="comments-list">
                <div class="comments-empty">No comments yet</div>
            </div>

            <div class="comment-compose">
                <textarea id="comment-input" rows="3" placeholder="Write a comment..."></textarea>
                <button class="btn-primary" type="button" onclick="board.addComment()">Add Comment</button>
            </div>
        </div>

        <div class="sidebar-footer">
            <button class="btn-primary" id="s-save-btn" type="button" onclick="board.saveTask()">Save Changes</button>
            <button class="btn-ghost" id="s-delete-btn" type="button" onclick="board.deleteTask()">Delete</button>
        </div>
    </div>
</div>

<!-- ── ADD TASK MODAL ── -->
<div id="modal-overlay" class="overlay" style="display:none" onclick="board.closeAdd()">
    <div class="modal" onclick="event.stopPropagation()">
        <span class="section-label">New Task</span>

        <input type="text" id="m-title" placeholder="Task title *">

        <textarea id="m-desc" rows="3" placeholder="Description (optional)"></textarea>

        <div style="display:flex;gap:10px">
            <div style="flex:1">
                <span class="field-label">Priority</span>
                <select id="m-priority">
                    <option value="low">Low</option>
                    <option value="mid" selected>Mid</option>
                    <option value="high">High</option>
                    <option value="crit">Critical</option>
                </select>
            </div>
            <div style="flex:1">
                <span class="field-label">Assign to</span>
                <select id="m-assignee"><option value="">Unassigned</option></select>
            </div>
        </div>

        <input type="text" id="m-tags" placeholder="Tags: frontend, backend…">

        <div class="modal-footer">
            <button class="btn-primary" type="button" onclick="board.createTask()">Create Task</button>
            <button class="btn-ghost" type="button" onclick="board.closeAdd()">Cancel</button>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div id="toast-container"></div>

<!-- ── DATA passed from PHP ── -->
<script>
    const CURRENT_USER = <?= json_encode($user) ?>;
</script>
<script src="/assets/js/board.js"></script>
</body>
</html>