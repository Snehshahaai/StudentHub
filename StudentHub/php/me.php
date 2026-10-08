<?php
/**
 * StudentHub - Current student (JSON)
 * Used by the dashboard and profile pages to show the logged-in student's
 * details. Answers 401 when nobody is logged in.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

try {
    $student = current_student();
    if (!$student) {
        send_json(401, ['success' => false, 'message' => 'Please log in to continue.']);
    }
    $id = (int) $student['student_id'];

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
} catch (mysqli_sql_exception $e) {
    error_log('[StudentHub me] ' . $e->getMessage());
    send_json(500, ['success' => false, 'message' => 'The database is unavailable right now.']);
}

$conducted = (int) ($attendance['conducted'] ?? 0);

send_json(200, [
    'success' => true,
    'student' => [
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
    ],
]);
