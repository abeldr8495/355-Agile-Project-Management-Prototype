# Agile Board

A lightweight Kanban board built with PHP, SQLite, and vanilla JS — no frameworks, no database server.

---

## Quick Start (5 minutes)

> **Jump to your OS:** [macOS](#macos) · [Windows](#windows)

---

### macOS

#### Step 1 — Check PHP is installed

Open Terminal and run:

```bash
php -v
```

You should see PHP 8.x. macOS ships with PHP but it may be outdated.
If you get "command not found", install it:

```bash
brew install php
```

(Install Homebrew first if needed: https://brew.sh)

#### Step 2 — Navigate to this folder

```bash
cd /path/to/your/project
```

#### Step 3 — Run the PHP dev server

```bash
php -S localhost:8000 -t public
```

Leave this terminal running. You should see:

```
PHP 8.x.x Development Server (http://localhost:8000) started
```

Then skip to [Step 4](#step-4--set-up-the-database-run-once).

---

### Windows

#### Step 1 — Install PHP

1. Go to https://windows.php.net/download and download the latest **PHP 8.x Non Thread Safe** zip.
2. Extract it to `C:\php`.
3. Add `C:\php` to your system PATH:
   - Search **"Environment Variables"** in the Start menu → **Edit the system environment variables**
   - Under **System variables**, select **Path** → **Edit** → **New** → type `C:\php` → OK all dialogs.
4. Open a new **Command Prompt** and confirm it works:

```cmd
php -v
```

You should see PHP 8.x.

> **Alternative:** If you have [Chocolatey](https://chocolatey.org) installed, you can skip the above and just run:
>
> ```cmd
> choco install php
> ```

#### Step 2 — Navigate to this folder

Open Command Prompt or PowerShell:

```cmd
cd C:\path\to\your\project
```

#### Step 3 — Run the PHP dev server

```cmd
php -S localhost:8000 -t public
```

Leave this terminal running. You should see:

```
PHP 8.x.x Development Server (http://localhost:8000) started
```

---

### Step 4 — Set up the database (run once)

Open your browser and go to:

```
http://localhost:8000/setup.php
```

You will see: **✓ Setup complete**
This creates the SQLite database, demo users, one starter board, default columns, and seed tasks.

⚠️ You only need to do this once. If you run it again it is safe — users use
INSERT OR IGNORE and tasks are only seeded if the table is empty.

---

### Step 5 — Log in

Go to:

```
http://localhost:8000/login.php
```

Demo accounts and roles:

| Username  | Password      | Role      | Notes |
| --------- | ------------- | --------- | ----- |
| sysadmin  | sysadmin123   | sysadmin  | System administrator; can manage admin roles and cannot be demoted |
| david     | password      | admin     | Board/column/task management; can reset member passwords |
| rachel    | password      | member    | Regular member account |
| keegan    | password      | member    | Regular member account |
| nithin    | password      | member    | Regular member account |
| charlie   | password      | member    | Regular member account |
| sid       | password      | member    | Regular member account |

New users can also self-register at `http://localhost:8000/register.php`.

---

## What You Can Do

| Feature                  | How                                                                                |
| ------------------------ | ---------------------------------------------------------------------------------- |
| **View board**           | Tasks appear in columns (To Do / In Progress / Done by default)                    |
| **Drag & drop tasks**    | Grab a card and drop it into another column                                        |
| **Open task detail**     | Click any card — sidebar slides in from the right                                  |
| **Edit task**            | Change title, description, priority, status, assignee, story points, and tags → Save Changes |
| **Delete task**          | Open sidebar → Delete button (shows a 5-second undo window)                        |
| **Create task**          | Click **+ NEW TASK** in the header, or **+ Add task** under any column             |
| **Story points**         | Set per-task in the sidebar; totals show in the sprint progress bar                |
| **Attach files**         | Open a task → use **Add File** in the sidebar (up to 10 MB per file)              |
| **Download attachments** | Click **Download** next to any attachment in the sidebar                           |
| **Comments**             | Open a task → type in the comment box; authors can edit or delete their own comments |
| **Search**               | Type in the search box (or press `/`) — filters by title, description, or tag     |
| **Keyboard shortcuts**   | `n` = new task · `/` or `Ctrl+K` = search · `Esc` = close modal/sidebar · `t` = toggle theme |
| **Notifications**        | Bell icon (top right) — every action logs an entry with a timestamp                |
| **Sprint progress**      | Bar and story-point counters reflect all Done-column tasks                         |
| **Switch user**          | Log out (→ icon next to your avatar) and log in as someone else                   |
| **Account settings**     | Click the gear icon → change display name, avatar, accent colour, password, theme  |

### Admin features (admin / sysadmin role)

| Feature                  | How                                                                                |
| ------------------------ | ---------------------------------------------------------------------------------- |
| **Create board**         | Boards sidebar → **+ Board** button                                                |
| **Delete board**         | Click × next to a board name (cannot delete the last board)                       |
| **Add column**           | Column toolbar above board → **+ Column** (choose name and accent colour)          |
| **Delete column**        | Click × on a column pill (tasks migrate to the first remaining column)             |
| **Reorder columns**      | Drag column pills left/right in the toolbar                                        |
| **Admin panel**          | Navigate to `admin.php` — manage users, reset passwords, promote/demote roles      |


## Project Structure

```
agile-board/
├── public/                      ← document root (served by PHP dev server)
│   ├── index.php                ← main Kanban board (requires login)
│   ├── login.php                ← login form; idle/expired session notices
│   ├── logout.php               ← destroys session, redirects to login
│   ├── logout_beacon.php        ← navigator.sendBeacon() target for tab-close logout
│   ├── register.php             ← self-service account creation
│   ├── settings.php             ← account settings (profile, password, theme)
│   ├── admin.php                ← user management panel (admin/sysadmin only)
│   ├── setup.php                ← one-time DB bootstrap (idempotent)
│   ├── download_attachment.php  ← authenticated file download endpoint
│   ├── api/
│   │   ├── get_boards.php        ← GET  → JSON array of boards
│   │   ├── create_board.php      ← POST → creates board + default columns
│   │   ├── delete_board.php      ← POST → deletes board (cascades all data)
│   │   ├── get_columns.php       ← GET  → ordered columns for a board
│   │   ├── create_column.php     ← POST → adds a column to a board
│   │   ├── delete_column.php     ← POST → removes column, migrates tasks
│   │   ├── reorder_columns.php   ← POST → persists drag/drop column order
│   │   ├── get_tasks.php         ← GET  → tasks + assignee info for a board
│   │   ├── create_task.php       ← POST → creates task, returns new row
│   │   ├── update_task.php       ← POST → partial/full update, returns row
│   │   ├── delete_task.php       ← POST → deletes task + cleans up files
│   │   ├── get_comments.php      ← GET  → comment thread for a task
│   │   ├── create_comment.php    ← POST → adds comment as current user
│   │   ├── update_comment.php    ← POST → edits comment (author only)
│   │   ├── delete_comment.php    ← POST → deletes comment (author only)
│   │   ├── get_attachments.php   ← GET  → attachment list for a task
│   │   ├── upload_attachment.php ← POST → stores file, records metadata
│   │   ├── delete_attachment.php ← POST → removes file (uploader or admin)
│   │   └── get_users.php         ← GET  → user directory for assignee selects
│   └── assets/
│       ├── css/style.css         ← all styling + five CSS-variable themes
│       └── js/board.js           ← all board logic (drag/drop, fetch, UI, polling)
├── src/
│   ├── db.php                   ← PDO SQLite connection; uploads dir helper
│   └── auth.php                 ← session management, login, requireLogin/Admin/Sysadmin
└── data/
    ├── agile.db                 ← SQLite database (auto-created by setup.php)
    └── uploads/                 ← locally stored attachment files
```

---

## How The App Fits Together

This project is split into three layers:

1. `public/*.php` — Page controllers. They enforce auth, load a little server-side state, and render the HTML shell for each screen (board, login, settings, admin panel).

2. `public/api/*.php` — JSON endpoints called by the frontend with `fetch()`. They load boards, tasks, columns, comments, and attachments, and persist edits from drag/drop, the sidebar, and modal forms.

3. `public/assets/js/board.js` — The entire client-side app. It owns the current board, loaded tasks, columns, sidebar/modal visibility, notifications, and toast messages. All client state lives here; the PHP pages just render the HTML shell.

### Request flow example

When someone opens the main board:

1. `public/index.php` checks the session and renders the HTML shell.
2. `board.js` boots on `DOMContentLoaded`.
3. The script fetches users, boards, columns, and tasks in parallel from `public/api/`.
4. The board UI is rendered dynamically from the API data.
5. A 1-second polling loop begins: if the serialised task list changes, the board re-renders automatically so collaborators' edits appear without a page refresh. Polling pauses when the tab is hidden and resumes on tab focus.

When someone edits a task:

1. The sidebar updates the in-memory task object in `board.js`.
2. Clicking Save sends the payload to `public/api/update_task.php`.
3. The API updates SQLite and returns the fresh row.
4. `board.js` merges the returned row back into local state and re-renders.

When a task is deleted:

1. The task is removed from local state and the board re-renders immediately.
2. A 5-second undo toast appears. Clicking **Undo** re-inserts the task locally.
3. After 5 seconds (or on the next delete), `delete_task.php` is called to make the deletion permanent.

### Data model

| Table           | Purpose |
| --------------- | ------- |
| `users`         | Login credentials, display metadata, role |
| `boards`        | Top-level board containers |
| `board_columns` | Per-board workflow columns (name, status_key, color, position) |
| `tasks`         | Cards on the board; belong to a board; `status` values must match a column's `status_key` |
| `comments`      | Threaded comments on tasks; editable and deletable by their author only |
| `attachments`   | File metadata stored in SQLite; actual files live under `data/uploads/` |

### Roles

| Role       | Abilities |
| ---------- | --------- |
| `member`   | Create/edit tasks, add/edit/delete own comments, upload/delete own attachments |
| `admin`    | All member abilities + board/column management, user administration, delete any attachment |
| `sysadmin` | All admin abilities + promote/demote other admins; cannot be demoted via the UI |

The `sysadmin` role is enforced by username in `src/auth.php`, so that account stays privileged even if the database value drifts.

### Session security

- Sessions use `httponly` cookies that expire when the browser closes (`lifetime = 0`).
- Idle timeout: 30 minutes of inactivity triggers automatic logout.
- Absolute timeout: sessions expire after 2 hours regardless of activity.
- On tab/window close, `navigator.sendBeacon()` fires `logout_beacon.php` to clean up the server session (best-effort; the idle timeout is the authoritative guard).
- `session_regenerate_id(true)` is called on every successful login.

> **Note:** The session cookie's `secure` flag is set to `false` by default so the
> app works over plain `http://localhost`. Set it to `true` in `src/auth.php` before
> deploying to any environment served over HTTPS.

---

## API Reference

All endpoints live in `public/api/`. They require an active session (redirect to
`login.php` when unauthenticated). All request bodies are JSON unless noted.

### Boards

#### `GET /api/get_boards.php`
Returns all boards ordered by creation date.

#### `POST /api/create_board.php` *(admin)*
```json
{ "name": "Sprint 2" }
```
Creates the board and its three default columns. Returns the new board row.

#### `POST /api/delete_board.php` *(admin)*
```json
{ "id": 2 }
```
Cascades to all columns, tasks, comments, and files. Cannot delete the last board.

---

### Columns

#### `GET /api/get_columns.php`
```
?board_id=1
```

#### `POST /api/create_column.php` *(admin)*
```json
{ "board_id": 1, "name": "Review", "color": "#4a9ee8" }
```

#### `POST /api/delete_column.php` *(admin)*
```json
{ "id": 4 }
```
Moves tasks from the deleted column into the first remaining column. Returns `tasks_moved_to`.

#### `POST /api/reorder_columns.php` *(admin)*
```json
{ "order": [3, 1, 2] }
```

---

### Tasks

#### `GET /api/get_tasks.php`
```
?board_id=1
```

#### `POST /api/create_task.php`
```json
{
  "board_id": 1,
  "title": "My task",
  "description": "Optional",
  "status": "todo",
  "priority": "low | mid | high | crit",
  "assigned_to": 1,
  "tags": "frontend,backend",
  "story_points": 3
}
```

#### `POST /api/update_task.php`
Send only the fields you want to change:
```json
{ "id": 3, "status": "done" }
```

#### `POST /api/delete_task.php`
```json
{ "id": 3 }
```

---

### Comments

#### `GET /api/get_comments.php`
```
?task_id=3
```

#### `POST /api/create_comment.php`
```json
{ "task_id": 3, "body": "Looks good." }
```
Maximum 5000 characters.

#### `POST /api/update_comment.php` *(author only)*
```json
{ "id": 7, "body": "Updated text." }
```

#### `POST /api/delete_comment.php` *(author only)*
```json
{ "id": 7 }
```

---

### Attachments

#### `GET /api/get_attachments.php`
```
?task_id=3
```

#### `POST /api/upload_attachment.php`
Multipart form data (not JSON):
```
task_id=3
attachment=<file>       (max 10 MB)
```

#### `POST /api/delete_attachment.php` *(uploader or admin)*
```json
{ "id": 12 }
```

#### `GET /download_attachment.php`
```
?id=12
```
Streams the file as a download. Requires authentication.

---

### Users

#### `GET /api/get_users.php`
Returns the user directory used to populate assignee dropdowns.

---

## Troubleshooting

**"Permission denied" on data/ folder** *(macOS/Linux)*

```bash
chmod 755 data
chmod 755 data/uploads
```

**"Permission denied" on data/ folder** *(Windows)*

Right-click the `data` folder → **Properties** → **Security** → ensure your user has **Write** permission.

**Port 8000 already in use**

```bash
php -S localhost:8080 -t public
```

Then visit http://localhost:8080

**Want to reset the database (start fresh)**

macOS/Linux:
```bash
rm data/agile.db
rm -rf data/uploads/*
```

Windows:
```cmd
del data\agile.db
del /q data\uploads\*
```

Then visit http://localhost:8000/setup.php again.

**`php` is not recognised on Windows**
Make sure `C:\php` is in your PATH (see Step 1) and that you opened a **new** Command Prompt after editing it.

**Session keeps acting strange**
Make sure you're using the same host consistently (e.g. `http://localhost:8000`). Sessions expire after 30 minutes of inactivity or 2 hours total.

**Attachment uploads fail**
Ensure PHP can write into `data/` and keep individual files at 10 MB or below.

**Board stays blank after deleting a column**
Make sure you are running the latest code — an earlier version had a bug where the
local task-status comparison used the column display name instead of its `status_key`.
