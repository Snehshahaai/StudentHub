# StudentHub – Test checklist

Run `bash scripts/verify.sh` first (no database needed). It checks that every PHP/JS file parses, every local
link/script/stylesheet/image exists, internal PHP files and `database/` are blocked, and that protected pages and
the JSON API refuse anonymous users.

Then test by hand on the deployed site (see `docs/deployment.md`). Tick each row.

## Navigation
| # | Check | Expected |
|---|---|---|
| N1 | Every link in the navbar and footer of every public page | No 404; the active page is highlighted |
| N2 | Logged in as **student** | Menu links to the student dashboard; admin pages redirect with a message |
| N3 | Logged in as **faculty** | Menu: Dashboard, Students only; `admin-dashboard.php` and Events redirect back |
| N4 | Logged in as **admin** | Menu: Admin Console, Students, Events, Live Events |

## Responsiveness (browser dev tools: 360 px, 768 px, 1280 px)
| # | Check | Expected |
|---|---|---|
| R1 | Home, login, register, contact | No horizontal scroll; hamburger menu opens and closes at 360 px |
| R2 | Admin tables (students, events, dashboard) | Scroll inside the card, not the page |
| R3 | Dark / light toggle | Choice survives a reload |

## Authentication
| # | Check | Expected |
|---|---|---|
| A1 | Register with a taken username/email | Field error, no account created |
| A2 | Login with a wrong password 5 times | Account locked for 15 minutes |
| A3 | Login, then Logout, then press Back | Redirected to login; page is not shown from cache |
| A4 | Idle for 30 minutes (or run with `SESSION_IDLE_TIMEOUT=60`) | Warning, then redirect to login with "session expired" |
| A5 | Remember me | Still logged in after closing and reopening the browser |

## Database operations
| # | Check | Expected |
|---|---|---|
| D1 | Students: add, edit, delete, search, filter, approve | Row changes in phpMyAdmin; success message |
| D2 | Events: add with poster, edit, replace poster, delete | Poster file appears / disappears in `uploads/events/` |
| D3 | Dashboard numbers | Match `SELECT COUNT(*)` on `students`, `events`, `event_registrations` |
| D4 | Audit log | Each write above adds a row to `audit_logs` |

## AJAX / Fetch
| # | Check | Expected |
|---|---|---|
| J1 | Live Events: list, search while typing, filters, pagination | Table updates, page does not reload |
| J2 | Add event with invalid data | Errors shown beside the fields, modal stays open |
| J3 | Add, edit, delete a valid event | Toast, table refreshes; browser Network tab shows `fetch` calls with JSON |
| J4 | Delete the last row of page 2 | Moves back to page 1 |
| J5 | Registration page "already taken?" check | Message appears while typing |

## Security
| # | Check | Expected |
|---|---|---|
| S1 | Open `/php/lib.php`, `/database/schema.sql`, `/php/views/admin-layout.php` | 403 |
| S2 | Open `/uploads/events/anything.php` | 403 |
| S3 | `<script>alert(1)</script>` in an event title or student name | Shown as text, never runs |
| S4 | `' OR 1=1 --` in login and search boxes | Treated as plain text (prepared statements) |
| S5 | Replay a POST/PUT/DELETE to the events API without `X-CSRF-Token` | 419 |
| S6 | Upload `shell.php` renamed to `.jpg` as a poster | Rejected (type read from file contents) |
| S7 | Student calls `/php/api/events-api.php` | 403 |
