<?php
/**
 * StudentHub - Student Registration Handler
 * Validates and sanitizes the registration form, then stores the student
 * in storage/registrations.json (password saved as a secure hash).
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

/* ---------- Store ---------- */
$record = [
    'id'           => new_id('STU'),
    'fullName'     => $input['fullName'],
    'email'        => $input['email'],
    'mobile'       => $input['mobile'],
    'course'       => $input['course'],
    'year'         => (int) $input['year'],
    'gender'       => $input['gender'],
    'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
    'registeredAt' => date('c'),
];

try {
    $duplicate = update_json_store('registrations.json', function (array &$records) use ($record) {
        foreach ($records as $existing) {
            if (($existing['email'] ?? '') === $record['email']) {
                return 'email';
            }
            if (($existing['mobile'] ?? '') === $record['mobile']) {
                return 'mobile';
            }
        }
        $records[] = $record;
        return null;
    });
} catch (Throwable $e) {
    error_log('[StudentHub register] ' . $e->getMessage());
    respond(false, 'Server error: your registration could not be saved. Please try again later.', [], RETURN_TO);
}

if ($duplicate === 'email') {
    respond(false, 'This account already exists.', ['email' => 'This email is already registered. Try logging in instead.'], RETURN_TO);
}
if ($duplicate === 'mobile') {
    respond(false, 'This account already exists.', ['mobile' => 'This mobile number is already registered.'], RETURN_TO);
}

$firstName = explode(' ', $record['fullName'])[0];
respond(true, "Registration successful! Welcome to StudentHub, {$firstName}. Your student ID is {$record['id']}.", [], RETURN_TO);
