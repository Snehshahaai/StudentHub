<?php
/**
 * StudentHub - Contact / Support Form Handler
 * Validates and sanitizes the contact form, then saves the message as a
 * support ticket in the MySQL `contact_messages` table.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';

const RETURN_TO = '../pages/contact.html';

const SUBJECTS = [
    'General Inquiry',
    'Technical Portal Bug',
    'Attendance Discrepancy',
    'Assignment Portal Issue',
];

require_post(RETURN_TO);

if (is_spam()) {
    respond(true, 'Message sent!', [], RETURN_TO);
}

/* ---------- Sanitize ---------- */
$input = [
    'name'    => clean_text($_POST['name'] ?? ''),
    'email'   => clean_email($_POST['email'] ?? ''),
    'subject' => clean_choice($_POST['subject'] ?? '', SUBJECTS),
    'message' => clean_text($_POST['message'] ?? '', multiline: true),
];

/* ---------- Validate ---------- */
$errors = [];

if ($input['name'] === '') {
    $errors['name'] = 'Please enter your name.';
} elseif (!preg_match("/^(?=.{2,50}$)[A-Za-z]+(?:[ .'-][A-Za-z]+)*\.?$/", $input['name'])) {
    $errors['name'] = "Name must be 2-50 letters (spaces, dots, apostrophes and hyphens allowed).";
}

if ($input['email'] === '') {
    $errors['email'] = 'Please enter your email address.';
} elseif (text_length($input['email']) > 100 || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address (e.g. student@example.com).';
}

if ($input['subject'] === '') {
    $errors['subject'] = 'Please choose a subject from the list.';
}

$length = text_length($input['message']);
if ($length === 0) {
    $errors['message'] = 'Please write your message.';
} elseif ($length < 10) {
    $errors['message'] = 'Your message is too short (minimum 10 characters).';
} elseif ($length > 1000) {
    $errors['message'] = "Your message is too long ({$length}/1000 characters).";
}

if ($errors) {
    respond(false, 'Please fix the highlighted fields.', $errors, RETURN_TO);
}

/* ---------- Store in MySQL ---------- */
$ticket = new_id('MSG');

try {
    // Link the ticket to the account when a logged-in student writes in
    $user = current_user();
    $studentId = ($user && $user['type'] === 'student') ? $user['id'] : null;

    db_insert(
        'INSERT INTO contact_messages (ticket_no, student_id, name, email, subject, message, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $ticket,
            $studentId,
            $input['name'],
            $input['email'],
            $input['subject'],
            $input['message'],
            client_ip(),
        ]
    );
} catch (mysqli_sql_exception $e) {
    respond_db_error($e, 'contact', RETURN_TO);
}

respond(true, "Message sent! Your ticket number is {$ticket}. Our support team will reply within 24 hours.", [], RETURN_TO);
