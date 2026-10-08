<?php
/**
 * StudentHub - Student records (data layer for the student management module)
 * Validation, search/filter, create, read, update and delete for `students`.
 * Every query goes through php/db.php, i.e. MySQLi prepared statements with
 * bound parameters; only fixed, whitelisted SQL fragments are concatenated.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const STUDENT_STATUSES = ['active', 'pending', 'inactive', 'suspended'];
const STUDENT_GENDERS  = ['Male', 'Female', 'Other'];
const STUDENT_PER_PAGE = [10, 25, 50];

// Sort options shown in the list => ORDER BY clause (never built from user text)
const STUDENT_SORTS = [
    'newest'     => ['Newest first',      'st.created_at DESC, st.student_id DESC'],
    'oldest'     => ['Oldest first',      'st.created_at ASC, st.student_id ASC'],
    'name_asc'   => ['Name (A-Z)',        'st.full_name ASC'],
    'name_desc'  => ['Name (Z-A)',        'st.full_name DESC'],
    'enrollment' => ['Enrollment number', 'st.enrollment_no ASC'],
];

/* ==========================================================================
   LOOKUPS
   ========================================================================== */

/** Courses for the dropdowns: code => [id, name, duration, dept code]. */
function student_courses(): array
{
    $courses = [];
    foreach (db_all(
        'SELECT c.course_id, c.code, c.name, c.duration_years, d.code AS dept_code
           FROM courses c JOIN departments d ON d.department_id = c.department_id
          ORDER BY c.course_id'
    ) as $row) {
        $courses[$row['code']] = $row;
    }
    return $courses;
}

/** One student with course and department names, or null. */
function student_find(int $id): ?array
{
    return db_one(
        'SELECT st.*, c.code AS course_code, c.name AS course_name, d.name AS department
           FROM students st
           JOIN courses c     ON c.course_id = st.course_id
           JOIN departments d ON d.department_id = c.department_id
          WHERE st.student_id = ?',
        [$id]
    );
}

/* ==========================================================================
   SEARCH & FILTER
   ========================================================================== */

/** Read and whitelist the list page's query string. */
function student_filters(array $query, array $courses): array
{
    $text = fn(string $key): string => is_string($query[$key] ?? null) ? clean_text($query[$key]) : '';

    $year    = (int) $text('year');
    $perPage = (int) $text('per_page');

    return [
        'q'        => mb_substr($text('q'), 0, 60),
        'course'   => array_key_exists($text('course'), $courses) ? $text('course') : '',
        'year'     => $year >= 1 && $year <= 6 ? $year : 0,
        'status'   => in_array($text('status'), STUDENT_STATUSES, true) ? $text('status') : '',
        'gender'   => in_array($text('gender'), STUDENT_GENDERS, true) ? $text('gender') : '',
        'sort'     => array_key_exists($text('sort'), STUDENT_SORTS) ? $text('sort') : 'newest',
        'per_page' => in_array($perPage, STUDENT_PER_PAGE, true) ? $perPage : STUDENT_PER_PAGE[0],
        'page'     => max(1, (int) $text('page')),
    ];
}

/**
 * Search + filter + sort + paginate.
 * Returns ['rows' => [...], 'total' => int, 'pages' => int, 'page' => int].
 */
function student_search(array $f): array
{
    $where  = [];
    $params = [];

    if ($f['q'] !== '') {
        // One box searches name, username, email, enrollment no. and mobile
        $like = '%' . like_escape($f['q']) . '%';
        $where[] = '(st.full_name LIKE ? OR st.username LIKE ? OR st.email LIKE ?
                     OR st.enrollment_no LIKE ? OR st.mobile LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($f['course'] !== '') {
        $where[]  = 'c.code = ?';
        $params[] = $f['course'];
    }
    if ($f['year'] > 0) {
        $where[]  = 'st.year_of_study = ?';
        $params[] = $f['year'];
    }
    if ($f['status'] !== '') {
        $where[]  = 'st.status = ?';
        $params[] = $f['status'];
    }
    if ($f['gender'] !== '') {
        $where[]  = 'st.gender = ?';
        $params[] = $f['gender'];
    }

    $from = 'FROM students st
             JOIN courses c     ON c.course_id = st.course_id
             JOIN departments d ON d.department_id = c.department_id '
          . ($where ? 'WHERE ' . implode(' AND ', $where) : '');

    $total = (int) db_value("SELECT COUNT(*) {$from}", $params);
    $pages = max(1, (int) ceil($total / $f['per_page']));
    $page  = min($f['page'], $pages);

    $rows = db_all(
        "SELECT st.student_id, st.enrollment_no, st.username, st.full_name, st.email, st.mobile,
                st.gender, st.year_of_study, st.semester, st.status, st.created_at,
                c.code AS course_code, d.short_name AS department
         {$from}
         ORDER BY " . STUDENT_SORTS[$f['sort']][1] . '
         LIMIT ? OFFSET ?',
        [...$params, $f['per_page'], ($page - 1) * $f['per_page']]
    );

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Counts for the status filter chips: status => number of students. */
function student_status_counts(): array
{
    $counts = array_fill_keys(STUDENT_STATUSES, 0);
    foreach (db_all('SELECT status, COUNT(*) AS n FROM students GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['n'];
    }
    return $counts;
}

/* ==========================================================================
   VALIDATION
   ========================================================================== */

/** Sanitize the add/edit form. */
function student_input(array $post): array
{
    return [
        'full_name'     => clean_text($post['full_name'] ?? ''),
        'username'      => clean_username($post['username'] ?? ''),
        'email'         => clean_email($post['email'] ?? ''),
        'mobile'        => clean_text($post['mobile'] ?? ''),
        'gender'        => clean_choice($post['gender'] ?? '', STUDENT_GENDERS),
        'course'        => clean_text($post['course'] ?? ''),
        'year_of_study' => (int) clean_text($post['year_of_study'] ?? ''),
        'semester'      => (int) clean_text($post['semester'] ?? ''),
        'address'       => clean_text($post['address'] ?? '', multiline: true),
        'status'        => clean_choice($post['status'] ?? '', STUDENT_STATUSES),
    ];
}

/**
 * Validate the form. $existing is the current row when editing (null when adding).
 * Returns field => message (empty array when everything is valid).
 */
function student_validate(array $in, string $password, string $confirm, array $courses, ?array $existing): array
{
    $errors = [];

    if ($m = name_error($in['full_name'])) {
        $errors['full_name'] = $m;
    }
    // An unchanged username is kept even if it predates the current rules
    if (!($existing && $in['username'] === $existing['username']) && ($m = username_error($in['username']))) {
        $errors['username'] = $m;
    }
    if ($m = email_error($in['email'])) {
        $errors['email'] = $m;
    }
    if ($m = mobile_error($in['mobile'])) {
        $errors['mobile'] = $m;
    }
    if ($in['gender'] === '') {
        $errors['gender'] = 'Please select a gender.';
    }

    $course = $courses[$in['course']] ?? null;
    if (!$course) {
        $errors['course'] = 'Please select a valid course.';
    } elseif ($in['year_of_study'] < 1 || $in['year_of_study'] > (int) $course['duration_years']) {
        $errors['year_of_study'] = "Year must be between 1 and {$course['duration_years']} for this course.";
    } elseif (!in_array($in['semester'], [$in['year_of_study'] * 2 - 1, $in['year_of_study'] * 2], true)) {
        $first = $in['year_of_study'] * 2 - 1;
        $errors['semester'] = "Year {$in['year_of_study']} means semester {$first} or " . ($first + 1) . '.';
    }

    if (text_length($in['address']) > 255) {
        $errors['address'] = 'Address must be 255 characters or fewer.';
    }
    if ($in['status'] === '') {
        $errors['status'] = 'Please select a valid status.';
    }

    // Password: required when adding, optional when editing (blank = keep current)
    if (!$existing || $password !== '' || $confirm !== '') {
        if ($m = password_error($password)) {
            $errors['password'] = $m;
        } elseif (!hash_equals($password, $confirm)) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    // Duplicates (ignoring this student's own row when editing)
    $taken = db_one(
        'SELECT COALESCE(SUM(username = ?), 0) AS username,
                COALESCE(SUM(email = ?), 0)    AS email,
                COALESCE(SUM(mobile = ?), 0)   AS mobile
           FROM students
          WHERE (username = ? OR email = ? OR mobile = ?) AND student_id <> ?',
        [$in['username'], $in['email'], $in['mobile'],
         $in['username'], $in['email'], $in['mobile'], $existing ? (int) $existing['student_id'] : 0]
    );
    foreach (['username' => 'This username is already taken.', 'email' => 'This email is already registered.',
              'mobile' => 'This mobile number is already registered.'] as $field => $message) {
        if ((int) $taken[$field] > 0 && !isset($errors[$field])) {
            $errors[$field] = $message;
        }
    }

    return $errors;
}

/* ==========================================================================
   CREATE / UPDATE / DELETE
   ========================================================================== */

/**
 * Next enrollment number for a department this year, e.g. 2026CS110.
 * Call inside a transaction: FOR UPDATE stops two inserts getting the same number.
 */
function next_enrollment_no(string $deptCode): string
{
    $prefix = date('Y') . $deptCode;
    $last = db_value(
        'SELECT MAX(CAST(SUBSTRING(enrollment_no, ?) AS UNSIGNED))
           FROM students WHERE enrollment_no LIKE ? FOR UPDATE',
        [strlen($prefix) + 1, $prefix . '%']
    );
    return $prefix . (max(100, (int) $last) + 1);
}

/** Insert a student; returns [id, enrollment_no]. */
function student_create(array $in, string $password, array $course, int $byFacultyId): array
{
    return db_transaction(function () use ($in, $password, $course, $byFacultyId) {
        $enrollmentNo = next_enrollment_no($course['dept_code']);

        $id = db_insert(
            'INSERT INTO students
                (enrollment_no, username, full_name, email, mobile, password_hash, gender,
                 course_id, year_of_study, semester, address, status,
                 approved_by, approved_at, terms_accepted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $enrollmentNo, $in['username'], $in['full_name'], $in['email'], $in['mobile'],
                password_hash($password, PASSWORD_DEFAULT), $in['gender'],
                (int) $course['course_id'], $in['year_of_study'], $in['semester'],
                $in['address'] !== '' ? $in['address'] : null, $in['status'],
                $in['status'] === 'active' ? $byFacultyId : null,
                $in['status'] === 'active' ? date('Y-m-d H:i:s') : null,
            ]
        );
        return [$id, $enrollmentNo];
    });
}

/** Update a student; a blank $password keeps the current one. Returns changed field names. */
function student_update(array $existing, array $in, string $password, array $course, int $byFacultyId): array
{
    $id = (int) $existing['student_id'];

    $changed = [];
    foreach (['full_name', 'username', 'email', 'mobile', 'gender', 'year_of_study', 'semester', 'status'] as $field) {
        if ((string) $existing[$field] !== (string) $in[$field]) {
            $changed[] = $field;
        }
    }
    if ($existing['course_code'] !== $in['course']) {
        $changed[] = 'course';
    }
    if ((string) $existing['address'] !== $in['address']) {
        $changed[] = 'address';
    }
    if ($password !== '') {
        $changed[] = 'password';
    }

    // Becoming active for the first time counts as an approval
    $approving = $in['status'] === 'active' && $existing['approved_at'] === null;

    db_execute(
        'UPDATE students
            SET username = ?, full_name = ?, email = ?, mobile = ?, gender = ?,
                course_id = ?, year_of_study = ?, semester = ?, address = ?, status = ?,
                approved_by = IF(?, ?, approved_by), approved_at = IF(?, NOW(), approved_at),
                password_hash = COALESCE(?, password_hash)
          WHERE student_id = ?',
        [
            $in['username'], $in['full_name'], $in['email'], $in['mobile'], $in['gender'],
            (int) $course['course_id'], $in['year_of_study'], $in['semester'],
            $in['address'] !== '' ? $in['address'] : null, $in['status'],
            $approving, $byFacultyId, $approving,
            $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            $id,
        ]
    );

    // A changed password or a blocked account signs the student out everywhere
    if ($password !== '' || $in['status'] !== 'active') {
        db_execute('DELETE FROM remember_tokens WHERE student_id = ?', [$id]);
    }
    return $changed;
}

/** Approve a pending student. Returns false when they weren't pending. */
function student_approve(int $id, int $byFacultyId): bool
{
    return db_execute(
        "UPDATE students SET status = 'active', approved_by = ?, approved_at = NOW()
          WHERE student_id = ? AND status = 'pending'",
        [$byFacultyId, $id]
    ) === 1;
}

/** What else is removed with this student (shown before deleting). */
function student_related_counts(int $id): array
{
    return db_one(
        'SELECT (SELECT COUNT(*) FROM attendance             WHERE student_id = ?) AS attendance,
                (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = ?) AS submissions,
                (SELECT COUNT(*) FROM semester_results       WHERE student_id = ?) AS results,
                (SELECT COUNT(*) FROM event_registrations    WHERE student_id = ?) AS event_registrations',
        [$id, $id, $id, $id]
    );
}

/** Delete a student (attendance, submissions, results and registrations go too). */
function student_delete(int $id): bool
{
    return db_execute('DELETE FROM students WHERE student_id = ?', [$id]) === 1;
}
