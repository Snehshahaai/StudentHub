# StudentHub

A student portal built with HTML, CSS, Bootstrap 5, vanilla JavaScript, PHP (MySQLi) and MySQL.
Students register, log in and see their dashboard; faculty look up students; admins manage students and events.

## Features

| Area | What it does |
|---|---|
| Public site | Home with featured-event carousel, notices, FAQ, assignments, attendance, materials, contact form |
| Authentication | Register with live username/email check, login by role, remember-me, 30-minute idle timeout, lockout after 5 failed logins |
| Student | Dashboard (attendance, tasks, CGPA) and profile, filled from the database |
| Admin dashboard | User statistics, student and event counts, latest student and event registrations |
| Student management | Add, view, edit, delete, search, filter, approve (admin); view and search (faculty) |
| Event management | Add, edit, delete, poster upload with validation |
| Live events (AJAX) | The same event operations through a JSON API and the Fetch API, with no page reloads |
| Role-based menus | Admin, faculty and student each see only their own pages |

## Project structure

```
index.html, pages/*.html   public pages
pages/*.php                protected pages (student / faculty / admin)
php/                       form handlers, auth, data layers (students.php, events.php, dashboard.php)
php/api/events-api.php     JSON API used by the Live Events page
php/views/                 shared admin layout and role-based menu
js/  css/  images/         front end assets
database/                  schema.sql, seed.sql, migrations, set-passwords.php
deploy/studenthub.conf     Apache virtual host for XAMPP
docs/                      database, deployment, testing, wireframes
scripts/verify.sh          offline integration checks
router.php                 local `php -S` server rules (mirrors the .htaccess files)
```

## Quick start

```
# 1. start MySQL (XAMPP), then
mysql -u root < database/schema.sql
mysql -u root < database/seed.sql
php database/set-passwords.php        # prints demo passwords once
# 2. run
php -S localhost:8000 router.php      # http://localhost:8000
```

For the Apache virtual host (`http://studenthub.local`) follow **[docs/deployment.md](docs/deployment.md)**.

## Security

- MySQLi prepared statements for every query; `password_hash()` / `password_verify()`
- Sessions: regenerated ids, HttpOnly + SameSite cookies, idle and absolute timeouts
- CSRF token on every state-changing form and on the JSON API (`X-CSRF-Token`)
- Server-side validation and sanitizing; all output escaped (`e()` in PHP, `esc()` in JS)
- Role checks on every protected page and API call
- Poster uploads: size, real file type, extension and dimensions checked, then re-encoded and saved under a random name; `uploads/` can never execute code
- Internal PHP files, `database/` and `php/views/` are blocked from direct access
- No passwords in the repository: demo accounts start locked

## Testing

```
bash scripts/verify.sh
```

Checks syntax, local links/assets, blocked files and anonymous access to protected pages and the API
(no database needed). The manual checklist for navigation, responsiveness, authentication, database, AJAX and
security is in **[docs/testing.md](docs/testing.md)**.

## Documentation

- [docs/database.md](docs/database.md) – tables, endpoints, protected pages, ER diagram
- [docs/deployment.md](docs/deployment.md) – XAMPP / Apache virtual host
- [docs/testing.md](docs/testing.md) – test checklist
- `docs/wireframes/`, `docs/Sitemap.drawio.png` – design artifacts
