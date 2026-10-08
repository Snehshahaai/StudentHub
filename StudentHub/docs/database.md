# StudentHub Database

MySQL / MariaDB database `studenthub`. 21 tables and 4 views cover every page in the portal.

## Setup

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Import the scripts in order (phpMyAdmin → Import, or the terminal):
   ```
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/schema.sql
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/seed.sql
   ```
   ⚠️ `schema.sql` starts with `DROP DATABASE IF EXISTS studenthub`, so re-importing it wipes all data.
3. Open `php/db-test.php` in the browser to check the connection.

**Upgrading an existing database** (created before usernames were added): run each file in `database/migrations/` once, in order. They keep all existing data.
```
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/migrations/001_add_student_username.sql
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/migrations/002_remember_tokens_for_faculty.sql
```

Connection settings are in `php/config.php`. The defaults are XAMPP's: `root` with no password on `127.0.0.1:3306`.

Demo logins: student `sneh.shah` / `Student@123`, admin `admin@university.edu` / `Admin@123`, faculty `a.mehta@university.edu` / `Faculty@123`.

## PHP endpoints

Run the site with `php -S localhost:8000 router.php` from `StudentHub/` (or through XAMPP's Apache).

| File | Method | What it does | Tables |
|---|---|---|---|
| `php/register.php` | POST | Validates the form, rejects a taken username/email/mobile, hashes the password with `password_hash()`, creates the student with an enrollment no. like `2026IT205` | `students`, `audit_logs` |
| `php/check-availability.php` | GET | Live "already taken?" check for username / email while typing | `students` |
| `php/login.php` | POST | Students (email or username) and faculty/admins (email): `password_verify()`, new session id, role-based redirect, optional remember-me cookie, lockout after 5 failed tries in 15 min | `students`, `faculty`, `remember_tokens`, `audit_logs` |
| `php/logout.php` | POST | Ends the session and deletes the remember-me token | `remember_tokens` |
| `php/me.php` | GET | Logged-in user's details + seconds left in the session as JSON (401 if logged out or timed out). Also keeps the session alive | views + `assignments`, `study_materials` |
| `php/contact.php` | POST | Saves a support ticket, linked to the student when logged in | `contact_messages` |

Shared code: `php/db.php` (connection + prepared statements), `php/lib.php` (responses, sanitizing), `php/auth.php` (sessions, roles, timeouts, remember-me), `php/guard.php` (page protection).

### Protected pages

| Page | Allowed roles | Others are sent to |
|---|---|---|
| `pages/student-dashboard.php`, `pages/profile.php` | student | login page, or the admin dashboard for staff |
| `pages/admin-dashboard.php` | faculty, admin | login page, or the student dashboard for students |
| `pages/admin-students.php`, `pages/admin-student-view.php` | faculty, admin (view & search) | as above |
| `pages/admin-student-form.php` | admin only (add & edit) | as above |

### Student management module

`pages/admin-students.php` lists students with **search** (name, username, email, enrollment no., mobile), **filters** (course, year, status, gender), sorting and pagination. Admins can **add**, **edit**, **approve** and **delete**; faculty can only view. All queries live in `php/students.php` and run as MySQLi prepared statements. Every change is protected by a CSRF token, is written to `audit_logs`, and shows a success or failure message on the next page.

Deleting a student also deletes their attendance, submissions, results and event registrations (`ON DELETE CASCADE`); the confirmation dialog says so.

Sessions end after **30 minutes** without activity and always after **8 hours**. Logging in with "Remember login state" signs you back in automatically for 30 days. To try the timeout quickly:
```
SESSION_IDLE_TIMEOUT=60 php -S localhost:8000 router.php
```

## Tables by page

| Page / feature | Tables |
|---|---|
| Register, Login, Profile | `students`, `courses`, `departments`, `password_resets`, `remember_tokens` |
| Admin dashboard | `faculty`, `students.status` / `approved_by`, `audit_logs`, view `v_admin_stats` |
| Student dashboard | `class_schedule` (today's schedule), `semester_results` (CGPA), view `v_student_profile` |
| Attendance | `subjects`, `lectures`, `attendance`, view `v_attendance_summary` |
| Assignments | `assignments`, `assignment_submissions` |
| Materials | `study_materials` |
| Notices | `notices`, `notice_subscriptions` |
| Events (notices page + home carousel) | `events`, `event_registrations`, view `v_event_seats` |
| FAQ | `faqs` |
| Contact | `contact_messages` |

## ER diagram

```mermaid
erDiagram
    departments ||--o{ courses : offers
    departments ||--o{ subjects : teaches
    departments ||--o{ faculty : employs
    courses ||--o{ students : enrolls

    students ||--o{ event_registrations : makes
    events ||--o{ event_registrations : has

    subjects ||--o{ class_schedule : "timetabled in"
    subjects ||--o{ lectures : "conducted as"
    lectures ||--o{ attendance : records
    students ||--o{ attendance : has
    students ||--o{ semester_results : gets

    subjects ||--o{ assignments : sets
    assignments ||--o{ assignment_submissions : receives
    students ||--o{ assignment_submissions : submits

    subjects ||--o{ study_materials : contains
    faculty ||--o{ notices : posts
    students ||--o{ notice_subscriptions : subscribes
    students ||--o{ contact_messages : sends
    students ||--o{ password_resets : requests
    students ||--o{ remember_tokens : keeps

    students {
        int student_id PK
        varchar enrollment_no UK "2026CS108"
        varchar full_name
        varchar email UK
        char mobile UK
        varchar password_hash
        enum gender
        tinyint course_id FK
        tinyint year_of_study
        tinyint semester
        varchar address
        enum status "pending / active / ..."
    }
    events {
        int event_id PK
        varchar title
        enum event_type
        date event_date
        time start_time
        varchar venue
        varchar organizer
        smallint seats
        boolean is_featured
    }
    event_registrations {
        int registration_id PK
        int event_id FK
        int student_id FK
        varchar team_name
        enum status
        timestamp registered_at
    }
```

## Design notes

- **Passwords and reset codes** are only stored as hashes (`password_hash()`).
- **Attendance** has one row per student per lecture. Percentages are calculated by `v_attendance_summary`, never stored, so they can't get out of sync.
- **Unique keys** block duplicate emails, mobile numbers, enrollment numbers and double event registrations. **CHECK constraints** validate the mobile format, years, semesters, GPAs and message length.
- **Foreign keys**: deleting a student removes their own records (`CASCADE`). Deleting a faculty account keeps the content they posted (`SET NULL`).
- `notices` and `faqs` have `FULLTEXT` indexes for fast search.
