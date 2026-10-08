<?php
/**
 * StudentHub - Contact / Support Form Handler
 * Validates and sanitizes the contact form, then appends the message to
 * storage/contacts.csv.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

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

/* ---------- Store ---------- */
$ticket = new_id('MSG');

try {
    append_csv('contacts.csv', [
        'id'          => $ticket,
        'submittedAt' => date('c'),
        'name'        => $input['name'],
        'email'       => $input['email'],
        'subject'     => $input['subject'],
        'message'     => $input['message'],
    ]);
} catch (Throwable $e) {
    error_log('[StudentHub contact] ' . $e->getMessage());
    respond(false, 'Server error: your message could not be saved. Please try again later.', [], RETURN_TO);
}

respond(true, "Message sent! Your ticket number is {$ticket}. Our support team will reply within 24 hours.", [], RETURN_TO);
