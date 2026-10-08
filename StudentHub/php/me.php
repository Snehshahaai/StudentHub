<?php
/**
 * StudentHub - Current user (JSON)
 * Used by the protected pages to fill in the user's details and to keep the
 * session alive while the page is in use. Every call counts as activity.
 * Answers 401 when nobody is logged in (or the session timed out).
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

try {
    $user = current_user();
    if (!$user) {
        $expired = session_end_reason() === 'expired';
        send_json(401, [
            'success' => false,
            'expired' => $expired,
            'message' => $expired ? 'Your session expired due to inactivity. Please log in again.'
                                  : 'Please log in to continue.',
        ]);
    }

    $payload = [
        'success'   => true,
        'role'      => $user['role'],
        'dashboard' => DASHBOARDS[$user['role']],
        'session'   => [
            'idle_timeout' => SESSION_IDLE_TIMEOUT,
            'seconds_left' => session_seconds_left(),
        ],
    ];

    $payload[$user['type'] === 'student' ? 'student' : 'staff'] = $user['type'] === 'student'
        ? student_details($user)
        : staff_details($user);
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub me] ' . $e->getMessage());
    send_json(500, ['success' => false, 'message' => 'The database is unavailable right now.']);
}

send_json(200, $payload);

/* ---------- Details per role ---------- */

function student_details(array $user): array
{
    $student = $user['record'];
    $id = $user['id'];

    $attendance = db_one(
        'SELECT SUM(lectures_attended) AS attended, SUM(total_conducted) AS conducted
           FROM v_attendance_summary WHERE student_id = ?',
        [$id]
    );

    // Assignments for the student's department + semester, not submitted, deadline not passed
    $pendingTasks = db_value(
        'SELECT COUNT(*)
           FROM students st
           JOIN courses c   ON c.course_id = st.course_id
           JOIN subjects s  ON s.department_id = c.department_id AND s.semester = st.semester
           JOIN assignments a ON a.subject_id = s.subject_id
           LEFT JOIN assignment_submissions sub
                  ON sub.assignment_id = a.assignment_id AND sub.student_id = st.student_id
          WHERE st.student_id = ? AND sub.submission_id IS NULL AND a.deadline >= NOW()',
        [$id]
    );

    $materials = db_value(
        'SELECT COUNT(*)
           FROM students st
           JOIN courses c  ON c.course_id = st.course_id
           JOIN subjects s ON s.department_id = c.department_id AND s.semester = st.semester
           JOIN study_materials m ON m.subject_id = s.subject_id AND m.is_approved
          WHERE st.student_id = ?',
        [$id]
    );

    $conducted = (int) ($attendance['conducted'] ?? 0);

    return [
        'enrollment_no' => $student['enrollment_no'],
        'username'      => $student['username'],
        'full_name'     => $student['full_name'],
        'first_name'    => explode(' ', $student['full_name'])[0],
        'email'         => $student['email'],
        'mobile'        => $student['mobile'],
        'gender'        => $student['gender'],
        'address'       => $student['address'],
        'course'        => $student['course_name'],
        'department'    => $student['department'],
        'year'          => (int) $student['year_of_study'],
        'semester'      => (int) $student['semester'],
        'cgpa'          => $student['current_cgpa'] !== null ? number_format((float) $student['current_cgpa'], 2) : null,
        'attendance'    => $conducted ? round($attendance['attended'] * 100 / $conducted, 1) : null,
        'pending_tasks' => (int) $pendingTasks,
        'materials'     => (int) $materials,
    ];
}

function staff_details(array $user): array
{
    $staff = $user['record'];
    $stats = db_one('SELECT * FROM v_admin_stats');

    return [
        'full_name'         => $staff['full_name'],
        'first_name'        => explode(' ', $staff['full_name'])[0],
        'email'             => $staff['email'],
        'designation'       => $staff['designation'],
        'department'        => $staff['department'],
        'role'              => $user['role'],
        'total_students'    => (int) $stats['total_students'],
        'pending_approvals' => (int) $stats['pending_approvals'],
        'active_faculty'    => (int) $stats['active_faculty'],
        'submissions_today' => (int) $stats['submissions_today'],
        'open_tickets'      => (int) $stats['open_tickets'],
    ];
}
