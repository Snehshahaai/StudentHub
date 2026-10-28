<?php
/**
 * StudentHub - Admin dashboard data
 * Counts and "latest" lists shown on pages/admin-dashboard.php.
 * Read-only; every query goes through php/db.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** User statistics: students by status, signups this week, staff by role. */
function dashboard_user_stats(): array
{
    return db_one(
        "SELECT (SELECT COUNT(*) FROM students)                                            AS students,
                (SELECT COUNT(*) FROM students WHERE status = 'active')                    AS active,
                (SELECT COUNT(*) FROM students WHERE status = 'pending')                   AS pending,
                (SELECT COUNT(*) FROM students WHERE status = 'inactive')                  AS inactive,
                (SELECT COUNT(*) FROM students WHERE status = 'suspended')                 AS suspended,
                (SELECT COUNT(*) FROM students
                  WHERE created_at >= (NOW() - INTERVAL 7 DAY))                            AS new_this_week,
                (SELECT COUNT(*) FROM faculty WHERE role = 'faculty' AND status = 'active') AS faculty,
                (SELECT COUNT(*) FROM faculty WHERE role = 'admin'   AND status = 'active') AS admins"
    ) ?? [];
}

/** Event counts and total seat sign-ups. */
function dashboard_event_stats(): array
{
    return db_one(
        "SELECT (SELECT COUNT(*) FROM events)                                                       AS total,
                (SELECT COUNT(*) FROM events
                  WHERE status = 'scheduled' AND event_date >= CURRENT_DATE)                        AS upcoming,
                (SELECT COUNT(*) FROM events WHERE status = 'completed')                            AS completed,
                (SELECT COUNT(*) FROM events WHERE status = 'cancelled')                            AS cancelled,
                (SELECT COUNT(*) FROM event_registrations
                  WHERE status IN ('registered', 'attended'))                                       AS registrations"
    ) ?? [];
}

/** Newest student sign-ups. */
function dashboard_latest_students(int $limit = 6): array
{
    return db_all(
        'SELECT st.student_id, st.enrollment_no, st.full_name, st.email, st.status, st.created_at,
                d.short_name AS department
           FROM students st
           JOIN courses c     ON c.course_id = st.course_id
           JOIN departments d ON d.department_id = c.department_id
          ORDER BY st.created_at DESC, st.student_id DESC
          LIMIT ?',
        [$limit]
    );
}

/** Newest event sign-ups. */
function dashboard_latest_event_registrations(int $limit = 6): array
{
    return db_all(
        'SELECT r.registration_id, r.status, r.registered_at, r.team_name,
                st.student_id, st.full_name, st.enrollment_no,
                e.event_id, e.title AS event_title
           FROM event_registrations r
           JOIN students st ON st.student_id = r.student_id
           JOIN events e    ON e.event_id = r.event_id
          ORDER BY r.registered_at DESC, r.registration_id DESC
          LIMIT ?',
        [$limit]
    );
}
