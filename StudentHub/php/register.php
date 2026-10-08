<?php
/**
 * StudentHub - Student Registration Handler
 * Validates and sanitizes the registration form, rejects duplicate
 * usernames / emails / mobile numbers, then inserts the student into the
 * MySQL `students` table with MySQLi prepared statements (see php/db.php).
 * The password is stored only as a password_hash() hash, and an enrollment
 * number such as 2026CS110 is generated.
 */

declare(strict_types=1);

require __DIR__ . '/students.php';

const RETURN_TO = '../pages/register.html';

const COURSES = ['BTECH-CSE', 'BTECH-IT', 'BTECH-EC', 'BTECH-ME', 'BCA', 'MCA'];
const YEARS   = ['1', '2', '3', '4'];
const GENDERS = ['Male', 'Female', 'Other'];

const DUPLICATE_MESSAGES = [
    'username' => 'This username is already taken. Please choose another one.',
    'email'    => 'This email is already registered. Try logging in instead.',
    'mobile'   => 'This mobile number is already registered.',
];

require_post(RETURN_TO);

// Pretend it worked so bots don't retry, but store nothing
if (is_spam()) {
    respond(true, 'Registration successful!', [], RETURN_TO);
}

/* ---------- Sanitize ---------- */
$input = [
    'fullName' => clean_text($_POST['fullName'] ?? ''),
    'username' => clean_username($_POST['username'] ?? ''),
    'email'    => clean_email($_POST['email'] ?? ''),
    'mobile'   => clean_text($_POST['mobile'] ?? ''),
    'course'   => clean_choice($_POST['course'] ?? '', COURSES),
    'year'     => clean_choice($_POST['year'] ?? '', YEARS),
    'gender'   => clean_choice($_POST['gender'] ?? '', GENDERS),
];
// Passwords are never altered, only validated and hashed
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirm  = is_string($_POST['confirmPassword'] ?? null) ? $_POST['confirmPassword'] : '';

/* ---------- Validate (same rules as js/register.js) ---------- */
$errors = [];

if ($message = name_error($input['fullName'])) {
    $errors['fullName'] = $message;
}

if ($message = username_error($input['username'])) {
    $errors['username'] = $message;
}

if ($message = email_error($input['email'])) {
    $errors['email'] = $message;
}

if ($message = mobile_error($input['mobile'])) {
    $errors['mobile'] = $message;
}

if ($message = password_error($password)) {
    $errors['password'] = $message;
}

if ($confirm === '') {
    $errors['confirmPassword'] = 'Please confirm your password.';
} elseif (!hash_equals($password, $confirm)) {
    $errors['confirmPassword'] = 'Passwords do not match.';
}

if ($input['course'] === '') {
    $errors['course'] = 'Please select a valid course.';
}
if ($input['year'] === '') {
    $errors['year'] = 'Please select a valid year.';
}
if ($input['gender'] === '') {
    $errors['gender'] = 'Please select your gender.';
}
if (empty($_POST['terms'])) {
    $errors['terms'] = 'You must accept the Terms of Service to register.';
}

if ($errors) {
    respond(false, 'Please fix the highlighted fields.', $errors, RETURN_TO);
}

/* ---------- Store in MySQL ---------- */
try {
    // Report every taken field at once (the UNIQUE keys are the real guarantee)
    $taken = db_one(
        'SELECT COALESCE(SUM(username = ?), 0) AS username,
                COALESCE(SUM(email = ?), 0)    AS email,
                COALESCE(SUM(mobile = ?), 0)   AS mobile
           FROM students
          WHERE username = ? OR email = ? OR mobile = ?',
        [$input['username'], $input['email'], $input['mobile'],
         $input['username'], $input['email'], $input['mobile']]
    );
    $duplicateErrors = array_intersect_key(DUPLICATE_MESSAGES, array_filter($taken));
    if ($duplicateErrors) {
        respond(false, 'Some of these details are already registered.', $duplicateErrors, RETURN_TO);
    }

    $course = db_one(
        'SELECT c.course_id, d.code AS dept_code
           FROM courses c JOIN departments d ON d.department_id = c.department_id
          WHERE c.code = ?',
        [$input['course']]
    );
    if (!$course) {
        respond(false, 'Please fix the highlighted fields.', ['course' => 'Please select a valid course.'], RETURN_TO);
    }

    $student = db_transaction(function () use ($input, $password, $course) {
        // Enrollment no. = year + department code + next number, e.g. 2026CS110
        $enrollmentNo = next_enrollment_no($course['dept_code']);
        $year = (int) $input['year'];

        $id = db_insert(
            'INSERT INTO students
                (enrollment_no, username, full_name, email, mobile, password_hash, gender,
                 course_id, year_of_study, semester, status, terms_accepted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $enrollmentNo,
                $input['username'],
                $input['fullName'],
                $input['email'],
                $input['mobile'],
                password_hash($password, PASSWORD_DEFAULT),
                $input['gender'],
                (int) $course['course_id'],
                $year,
                $year * 2 - 1,             // first semester of the chosen year
                'active',                  // self-registration is approved automatically
            ]
        );
        audit_log('student', $id, 'student.register', 'students', $id, "Registered {$enrollmentNo}");

        return ['id' => $id, 'enrollment_no' => $enrollmentNo];
    });
} catch (mysqli_sql_exception $e) {
    // Lost a race with an identical sign-up between the check and the insert
    if ($e->getCode() === 1062) {
        foreach (DUPLICATE_MESSAGES as $field => $message) {
            if (str_contains($e->getMessage(), "uq_students_{$field}")) {
                respond(false, 'Some of these details are already registered.', [$field => $message], RETURN_TO);
            }
        }
    }
    respond_db_error($e, 'register', RETURN_TO);
}

$firstName = explode(' ', $input['fullName'])[0];
respond(true, "Registration successful! Welcome to StudentHub, {$firstName}. Your enrollment number is {$student['enrollment_no']}.", [], '../pages/login.html');
