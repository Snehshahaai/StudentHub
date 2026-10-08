<?php
/**
 * StudentHub - Student Registration Handler
 * Validates and sanitizes the registration form, then inserts the student
 * into the MySQL `students` table (password saved as a secure hash) with a
 * generated enrollment number such as 2026CS110.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

const RETURN_TO = '../pages/register.html';

const COURSES = ['BTECH-CSE', 'BTECH-IT', 'BTECH-EC', 'BTECH-ME', 'BCA', 'MCA'];
const YEARS   = ['1', '2', '3', '4'];
const GENDERS = ['Male', 'Female', 'Other'];

require_post(RETURN_TO);

// Pretend it worked so bots don't retry, but store nothing
if (is_spam()) {
    respond(true, 'Registration successful!', [], RETURN_TO);
}

/* ---------- Sanitize ---------- */
$input = [
    'fullName' => clean_text($_POST['fullName'] ?? ''),
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

if ($input['fullName'] === '') {
    $errors['fullName'] = 'Full name is required.';
} elseif (!preg_match('/^(?=.{3,50}$)[A-Za-z]+(?: [A-Za-z]+)*$/', $input['fullName'])) {
    $errors['fullName'] = 'Name must be 3-50 letters and spaces only.';
}

if ($input['email'] === '') {
    $errors['email'] = 'Email address is required.';
} elseif (text_length($input['email']) > 100 || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address (e.g. student@university.edu).';
}

if ($input['mobile'] === '') {
    $errors['mobile'] = 'Mobile number is required.';
} elseif (!preg_match('/^[6-9]\d{9}$/', $input['mobile'])) {
    $errors['mobile'] = 'Enter a valid 10-digit mobile number starting with 6-9.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) > 72 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,}$/', $password)) {
    $errors['password'] = 'Password needs 8-72 characters, an uppercase, a lowercase, a number and a special character (no spaces).';
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
    // Friendly duplicate messages (the UNIQUE keys below are the real guarantee)
    $existing = db_one(
        'SELECT email = ? AS same_email FROM students WHERE email = ? OR mobile = ? LIMIT 1',
        [$input['email'], $input['email'], $input['mobile']]
    );
    if ($existing) {
        respond(false, 'This account already exists.', $existing['same_email']
            ? ['email' => 'This email is already registered. Try logging in instead.']
            : ['mobile' => 'This mobile number is already registered.'], RETURN_TO);
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
        // Enrollment no. = year + department code + next number, e.g. 2026CS110.
        // FOR UPDATE locks the range so two sign-ups can't get the same number.
        $prefix = date('Y') . $course['dept_code'];
        $last = db_value(
            'SELECT MAX(CAST(SUBSTRING(enrollment_no, ?) AS UNSIGNED))
               FROM students WHERE enrollment_no LIKE ? FOR UPDATE',
            [strlen($prefix) + 1, $prefix . '%']
        );
        $enrollmentNo = $prefix . (max(100, (int) $last) + 1);
        $year = (int) $input['year'];

        $id = db_insert(
            'INSERT INTO students
                (enrollment_no, full_name, email, mobile, password_hash, gender,
                 course_id, year_of_study, semester, status, terms_accepted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $enrollmentNo,
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
    if ($e->getCode() === 1062 && str_contains($e->getMessage(), 'uq_students_email')) {
        respond(false, 'This account already exists.', ['email' => 'This email is already registered. Try logging in instead.'], RETURN_TO);
    }
    if ($e->getCode() === 1062 && str_contains($e->getMessage(), 'uq_students_mobile')) {
        respond(false, 'This account already exists.', ['mobile' => 'This mobile number is already registered.'], RETURN_TO);
    }
    respond_db_error($e, 'register', RETURN_TO);
}

$firstName = explode(' ', $input['fullName'])[0];
respond(true, "Registration successful! Welcome to StudentHub, {$firstName}. Your enrollment number is {$student['enrollment_no']}.", [], '../pages/login.html');
