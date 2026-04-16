<?php
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
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div id="app-shell">

<!-- LEFT SIDEBAR -->
<aside id="boards-sidebar">
    <div class="boards-sidebar-head">
        <div>
            <div class="logo-mark"><span class="accent">▸</span> Agile Board</div>
            <div class="logo-sub">FINAL EDITION</div>
        </div>
        <button class="btn-ghost" id="new-board-btn">+ BOARD</button>
    </div>

    <div class="boards-sidebar-section">
        <span class="section-label">Boards</span>
        <div id="boards-list"></div>
    </div>

    <div class="boards-sidebar-footer">
        <div class="user-avatar"
             style="background:<?= $user['color'] ?>22;border-color:<?= $user['color'] ?>;color:<?= $user['color'] ?>">
            <?= $user['avatar'] ?>
        </div>
        <a href="/logout.php">Logout</a>
    </div>
</aside>

<!-- MAIN -->
<div id="main-panel">

<header id="header">
    <div>
        <span class="section-label">Current Board</span>
        <div id="current-board-name">Board</div>
    </div>

    <input id="search-input" placeholder="Search tasks…">

    <div>
        <button id="theme-toggle">Light Mode</button>
        <button id="new-task-btn">+ NEW TASK</button>
    </div>
</header>

<section class="board-toolbar">
    <div>
        <span>SPRINT PROGRESS</span>
        <span id="sprint-pct">0%</span>
        <div id="progress-fill"></div>
    </div>
    <div id="team-avatars"></div>
</section>

<main id="board">

<div class="col" data-status="todo">
    <span>To Do</span>
    <div id="list-todo"></div>
</div>

<div class="col" data-status="inprogress">
    <span>In Progress</span>
    <div id="list-inprogress"></div>
</div>

<div class="col" data-status="done">
    <span>Done</span>
    <div id="list-done"></div>
</div>

</main>
</div>
</div>

<!-- SIDEBAR -->
<div id="sidebar-overlay" style="display:none">
<div id="sidebar">

<input id="s-title">
<textarea id="s-desc"></textarea>

<div id="comments-list"></div>

<textarea id="comment-input"></textarea>
<button onclick="board.addComment()">Add Comment</button>

<button onclick="board.saveTask()">Save</button>
<button onclick="board.deleteTask()">Delete</button>

</div>
</div>

<script>
const CURRENT_USER = <?= json_encode($user) ?>;
</script>
<script src="/assets/js/board.js"></script>
</body>
</html>