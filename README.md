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
This creates the SQLite database, 6 demo users, and 15 seed tasks.

⚠️ You only need to do this once. If you run it again it is safe — users use
INSERT OR IGNORE and tasks are only seeded if the table is empty.

---

### Step 5 — Log in

Go to:

```
http://localhost:8000/login.php
```

Demo accounts (all use password: **password**):

| Username | Display name |
| -------- | ------------ |
| rachel   | Rachel       |
| keegan   | Keegan       |
| nithin   | Nithin       |
| charlie  | Charlie      |
| sid      | Sid          |
| david    | David        |

---

## What You Can Do

| Feature              | How                                                                            |
| -------------------- | ------------------------------------------------------------------------------ |
| **View board**       | Tasks appear in To Do / In Progress / Done columns                             |
| **Drag & drop**      | Grab a card and drop it into another column                                    |
| **Open task detail** | Click any card — sidebar slides in from the right                              |
| **Edit task**        | Change title, description, priority, status, assignee, and tags → Save Changes |
| **Delete task**      | Open sidebar → Delete button (shows a confirm dialog)                          |
| **Create task**      | Click **+ NEW TASK** in the header, or **+ Add task** under any column         |
| **Search**           | Type in the search box — filters by title or tag in real time                  |
| **Notifications**    | Bell icon (top right) — every action logs an entry with a timestamp            |
| **Sprint progress**  | Bar auto-updates as tasks move to Done                                         |
| **Switch user**      | Log out (→ icon next to your avatar) and log in as someone else                |

### Coming soon

| Feature                | Status      |
| ---------------------- | ----------- |
| Role-based permissions | In progress |
| Sprint model           | In progress |
| Story points           | Planned     |

---

## Project Structure

```
agile-board/
├── public/                  ← document root (served by PHP)
│   ├── index.php            ← main board (requires login)
│   ├── login.php            ← login form
│   ├── logout.php           ← destroys session, redirects
│   ├── setup.php            ← one-time DB setup
│   ├── api/
│   │   ├── get_tasks.php    ← GET  → JSON array of tasks
│   │   ├── get_users.php    ← GET  → JSON array of users
│   │   ├── create_task.php  ← POST → creates task, returns new row
│   │   ├── update_task.php  ← POST → partial/full update, returns row
│   │   └── delete_task.php  ← POST → deletes task, returns {success}
│   └── assets/
│       ├── css/style.css    ← all styling (dark industrial theme)
│       └── js/board.js      ← all board logic (drag/drop, fetch, UI)
├── src/
│   ├── db.php               ← PDO SQLite connection helper
│   └── auth.php             ← session login / requireLogin()
└── data/
    └── agile.db             ← SQLite database (auto-created by setup.php)
```

---

## Troubleshooting

**"Permission denied" on data/ folder** _(macOS/Linux)_

```bash
chmod 755 data
```

**"Permission denied" on data/ folder** _(Windows)_

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
```

Windows:

```cmd
del data\agile.db
```

Then visit http://localhost:8000/setup.php again.

**`php` is not recognised on Windows**
Make sure `C:\php` is in your PATH (see Step 1) and that you opened a **new** Command Prompt after editing it.

**Session not persisting between pages**
Make sure you're using http://localhost:8000 (not 127.0.0.1) — PHP sessions are
tied to the domain. Either works as long as you're consistent.

---

## API Reference

All API endpoints live in `public/api/`. They require an active session (will
return a redirect to login.php if not authenticated).

### GET /api/get_tasks.php

Returns all tasks joined with assignee info.

### GET /api/get_users.php

Returns all users (id, display_name, avatar, color).

### POST /api/create_task.php

Body (JSON):

```json
{
  "title": "My task",
  "description": "Optional",
  "status": "todo | inprogress | done",
  "priority": "low | mid | high | crit",
  "assigned_to": 1,
  "tags": "frontend,backend"
}
```

### POST /api/update_task.php

Body (JSON) — only include fields you want to change:

```json
{
  "id": 3,
  "status": "done"
}
```

### POST /api/delete_task.php

```json
{ "id": 3 }
```
