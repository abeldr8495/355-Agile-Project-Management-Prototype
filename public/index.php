<?php
// Main board page
// This view renders the authenticated agile board interface and passes
// current user metadata, permissions, and the application base path to JS.
require_once '../src/auth.php';
requireLogin();
// Load the current authenticated user and determine whether admin controls should be shown.
$user = currentUser();
$isAdmin = in_array($user['role'] ?? '', ['admin', 'sysadmin'], true);
$basePath = appBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap&font-display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(appPath('assets/css/style.css')) ?>">
<script>
// Apply theme before paint to avoid flash
(function(){
    const t = localStorage.getItem('theme') || 'dark';
    if (t === 'light')    document.documentElement.classList.add('light-mode');
    if (t === 'midnight') document.documentElement.classList.add('midnight-mode');
    if (t === 'forest')   document.documentElement.classList.add('forest-mode');
    if (t === 'rose')     document.documentElement.classList.add('rose-mode');
})();
</script>
</head>
<body>

<div id="app-shell">

    <!-- ── MOBILE SIDEBAR OVERLAY ── -->
    <div id="boards-sidebar-overlay" aria-hidden="true"></div>

    <!-- ── LEFT SIDEBAR: BOARDS ── -->
    <aside id="boards-sidebar" role="navigation" aria-label="Boards navigation">
        <div class="boards-sidebar-head">
            <div>
                <div class="logo-mark"><span class="accent">▸</span> Agile Board</div>
                <div class="logo-sub">DEMO</div>
            </div>
            <?php if ($isAdmin): ?>
            <button class="btn-ghost" id="new-board-btn" type="button">+ BOARD</button>
            <?php else: ?>
            <span style="font-size:9px;color:var(--text-5);letter-spacing:.08em">MEMBER</span>
            <?php endif; ?>
        </div>

        <div class="boards-sidebar-section">
            <span class="section-label">Boards</span>
            <div id="boards-list" class="boards-list">
                <!-- Filled by JS -->
            </div>
        </div>

        <div class="boards-sidebar-footer">
            <div class="user-menu">
                <a href="<?= htmlspecialchars(appPath('settings.php')) ?>" id="settings-link" class="user-nav-link" title="Account Settings">
                    <div class="user-avatar" id="current-user-avatar"
                         style="background:<?= htmlspecialchars($user['color']) ?>22;border-color:<?= htmlspecialchars($user['color']) ?>;color:<?= htmlspecialchars($user['color']) ?>;cursor:pointer"
                         title="<?= htmlspecialchars($user['display_name']) ?> — Settings">
                        <?= htmlspecialchars($user['avatar']) ?>
                    </div>
                </a>
                <?php if ($isAdmin): ?>
                <a href="<?= htmlspecialchars(appPath('admin.php')) ?>" id="admin-link" class="settings-link user-nav-link" title="Admin Panel">⚙</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(appPath('logout.php')) ?>" class="logout-btn" title="Sign out">
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
                <!-- Mobile: hamburger to open sidebar drawer -->
                <button id="sidebar-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="false" aria-controls="boards-sidebar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
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

                <!-- Story points summary -->
                <div id="story-points-summary" style="display:flex;align-items:center;gap:10px;margin-left:20px">
                    <span class="story-points" id="sp-done">0 pts done</span>
                    <span class="story-points" id="sp-total">0 pts total</span>
                </div>
            </div>

            <div class="board-toolbar-right">
                <div class="team-avatars" id="team-avatars"><!-- filled by JS --></div>
            </div>
        </section>

        <!-- ── COLUMN MANAGER (admin only, rendered by JS) ── -->
        <div id="column-manager" class="column-manager" style="display:none"></div>

        <!-- ── BOARD (columns rendered dynamically by JS) ── -->
        <main id="board"></main>
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

        <div style="display:flex;gap:12px;align-items:flex-end">
            <div style="flex:1">
                <span class="field-label">Priority</span>
                <div class="priority-group" id="s-priority-group"></div>
            </div>
            <div>
                <span class="field-label">Story Points</span>
                <div class="story-points-wrap">
                    <input type="number" id="s-story-points" min="0" max="100" placeholder="–" style="width:64px;text-align:center">
                    <span class="sp-hint">pts</span>
                </div>
            </div>
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

        <div class="attachments-section">
            <div class="attachments-head">
                <span class="field-label" style="margin-bottom:0">Attachments</span>
                <button class="btn-ghost btn-compact" id="attachment-picker-btn" type="button" onclick="board.triggerAttachmentPicker()">Add File</button>
            </div>
            <input type="file" id="attachment-input" style="display:none" onchange="board.uploadAttachment(this)">
            <div id="attachments-list" class="attachments-list">
                <div class="comments-empty">No attachments yet</div>
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
                <span class="field-label">Story Points</span>
                <select id="m-story-points">
                    <option value="">None</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="5">5</option>
                    <option value="8">8</option>
                    <option value="13">13</option>
                    <option value="21">21</option>
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
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const APP_BASE_PATH = <?= json_encode($basePath) ?>;
</script>
<script src="<?= htmlspecialchars(appPath('assets/js/board.js')) ?>" defer></script>

<!-- ── MOBILE SIDEBAR DRAWER ── -->
<script>
(function() {
    const toggleBtn  = document.getElementById('sidebar-toggle');
    const sidebar    = document.getElementById('boards-sidebar');
    const overlay    = document.getElementById('boards-sidebar-overlay');
    if (!toggleBtn || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('visible');
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
    });

    // Close sidebar automatically when a board nav item is clicked (mobile UX)
    sidebar.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && e.target.closest('.board-nav-item')) {
            setTimeout(closeSidebar, 150);
        }
    });

    // Swipe-to-close (left swipe on sidebar)
    let touchStartX = 0;
    sidebar.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    sidebar.addEventListener('touchend', function(e) {
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (diff > 60) closeSidebar();
    }, { passive: true });
})();
</script>
</body>
</html>
