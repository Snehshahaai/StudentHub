/**
 * StudentHub - Student Registration Form Validation
 * Validates every field with Regular Expressions and shows inline errors,
 * checks live whether the username / email is already taken, then submits
 * to php/register.php (which validates everything again on the server).
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    if (!form) return;

    /* ==========================================================================
       REGULAR EXPRESSIONS
       ========================================================================== */
    const patterns = {
        // Letters only, words separated by single spaces, 3-50 characters
        name: /^(?=.{3,50}$)[A-Za-z]+(?: [A-Za-z]+)*$/,
        // 4-20 chars, starts with a letter, letters/digits/_ with single dots between parts
        username: /^(?=.{4,20}$)[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)*$/,
        // local-part@domain.tld
        email: /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/,
        // Indian mobile number: 10 digits starting with 6, 7, 8 or 9
        mobile: /^[6-9]\d{9}$/,
        // Min 8 chars with at least one uppercase, lowercase, digit and special character
        password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,}$/,
        // Value must not be empty
        selected: /^.+$/
    };

    const RESERVED_USERNAMES = ['admin', 'administrator', 'root', 'system', 'support', 'studenthub', 'faculty', 'null', 'undefined'];

    // Values the server reported as already registered
    const taken = { username: new Set(), email: new Set() };
    const TAKEN_MESSAGES = {
        username: 'This username is already taken. Please choose another one.',
        email: 'This email is already registered. Try logging in instead.'
    };

    const fields = {
        fullName: form.querySelector('#fullName'),
        username: form.querySelector('#username'),
        email: form.querySelector('#email'),
        mobile: form.querySelector('#mobile'),
        password: form.querySelector('#password'),
        confirmPassword: form.querySelector('#confirmPassword'),
        course: form.querySelector('#course'),
        year: form.querySelector('#year'),
        terms: form.querySelector('#termsCheck')
    };
    const genderInputs = form.querySelectorAll('input[name="gender"]');
    const genderError = form.querySelector('#genderError');

    /* ==========================================================================
       FIELD VALIDATORS (return an error message, or '' when valid)
       ========================================================================== */
    const validators = {
        fullName(value) {
            if (!value) return 'Full name is required.';
            if (!patterns.name.test(value)) return 'Name must be 3-50 letters and spaces only.';
            return '';
        },
        username(value) {
            if (!value) return 'Username is required.';
            if (!patterns.username.test(value)) {
                return 'Username must be 4-20 characters: start with a letter, then letters, numbers, _ or single dots.';
            }
            if (RESERVED_USERNAMES.includes(value)) return 'This username is reserved. Please choose another one.';
            if (taken.username.has(value)) return TAKEN_MESSAGES.username;
            return '';
        },
        email(value) {
            if (!value) return 'Email address is required.';
            if (!patterns.email.test(value)) return 'Enter a valid email address (e.g. student@university.edu).';
            if (taken.email.has(value.toLowerCase())) return TAKEN_MESSAGES.email;
            return '';
        },
        mobile(value) {
            if (!value) return 'Mobile number is required.';
            if (!patterns.mobile.test(value)) return 'Enter a valid 10-digit mobile number starting with 6-9.';
            return '';
        },
        password(value) {
            if (!value) return 'Password is required.';
            if (!patterns.password.test(value)) {
                return 'Password needs 8+ characters, an uppercase, a lowercase, a number and a special character (no spaces).';
            }
            return '';
        },
        confirmPassword(value) {
            if (!value) return 'Please confirm your password.';
            if (value !== fields.password.value) return 'Passwords do not match.';
            return '';
        },
        course(value) {
            return patterns.selected.test(value) ? '' : 'Please select your course.';
        },
        year(value) {
            return patterns.selected.test(value) ? '' : 'Please select your year.';
        },
        terms(_, input) {
            return input.checked ? '' : 'You must accept the Terms of Service to register.';
        }
    };

    /* ==========================================================================
       UI HELPERS
       ========================================================================== */
    function setFieldState(input, message) {
        const feedback = input.closest('.form-check, [class*="col-"]').querySelector('.invalid-feedback');
        input.classList.toggle('is-invalid', Boolean(message));
        input.classList.toggle('is-valid', !message && input.type !== 'checkbox');
        if (feedback) feedback.textContent = message;
        setValidMessage(input, '');
    }

    // Green text under a field, e.g. "Username is available."
    function setValidMessage(input, message) {
        const feedback = input.closest('[class*="col-"]')?.querySelector('.valid-feedback');
        if (feedback) feedback.textContent = message;
    }

    function validateField(key) {
        const input = fields[key];
        const value = input.type === 'checkbox' ? '' : input.value.trim();
        const message = validators[key](value, input);
        setFieldState(input, message);
        return !message;
    }

    function validateGender() {
        const checked = form.querySelector('input[name="gender"]:checked');
        const message = checked ? '' : 'Please select your gender.';
        genderInputs.forEach(radio => radio.classList.toggle('is-invalid', Boolean(message)));
        genderError.textContent = message;
        genderError.classList.toggle('d-block', Boolean(message));
        return !message;
    }

    /* ==========================================================================
       LIVE VALIDATION
       ========================================================================== */
    // Allow digits only in the mobile field
    fields.mobile.addEventListener('input', () => {
        fields.mobile.value = fields.mobile.value.replace(/\D/g, '');
    });

    Object.keys(fields).forEach(key => {
        const input = fields[key];
        const eventName = input.tagName === 'SELECT' || input.type === 'checkbox' ? 'change' : 'blur';
        input.addEventListener(eventName, () => validateField(key));
        // Re-check while typing once a field has already been flagged
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) validateField(key);
        });
    });

    // Usernames are lowercase without spaces, shown that way while typing
    fields.username.addEventListener('input', () => {
        const cleaned = fields.username.value.toLowerCase().replace(/\s/g, '');
        if (cleaned !== fields.username.value) fields.username.value = cleaned;
    });

    /**
     * Ask the server whether a username / email is free (php/check-availability.php).
     * Only runs once the format is valid; the server checks again on submit anyway.
     */
    async function checkAvailability(key) {
        const input = fields[key];
        const value = input.value.trim().toLowerCase();
        if (!validateField(key)) return;

        try {
            const params = new URLSearchParams({ field: key, value });
            const response = await fetch(`../php/check-availability.php?${params}`, {
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();

            // Ignore the answer if the user has typed something else meanwhile
            if (input.value.trim().toLowerCase() !== value || result.available === null) return;

            if (result.taken) {
                taken[key].add(value);
                setFieldState(input, result.message);
            } else if (result.available) {
                setValidMessage(input, result.message);
            } else {
                setFieldState(input, result.message);
            }
        } catch {
            // PHP not running: nothing to show, submit will report the problem
        }
    }

    let usernameTimer;
    fields.username.addEventListener('input', () => {
        clearTimeout(usernameTimer);
        setValidMessage(fields.username, '');
        usernameTimer = setTimeout(() => {
            if (fields.username.value.trim().length >= 4) checkAvailability('username');
        }, 500);
    });
    fields.username.addEventListener('blur', () => checkAvailability('username'));
    fields.email.addEventListener('blur', () => checkAvailability('email'));

    // Keep confirm password in sync when the password changes
    fields.password.addEventListener('input', () => {
        if (fields.confirmPassword.value) validateField('confirmPassword');
    });

    genderInputs.forEach(radio => radio.addEventListener('change', validateGender));

    /* ==========================================================================
       SUBMIT
       ========================================================================== */
    function showServerErrors(errors) {
        Object.entries(errors || {}).forEach(([name, message]) => {
            if (name === 'gender') {
                genderInputs.forEach(radio => radio.classList.add('is-invalid'));
                genderError.textContent = message;
                genderError.classList.add('d-block');
            } else if (fields[name]) {
                // Remember duplicates so the same value is blocked before resubmitting
                if (taken[name]) taken[name].add(fields[name].value.trim().toLowerCase());
                setFieldState(fields[name], message);
            }
        });
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const results = Object.keys(fields).map(validateField);
        results.push(validateGender());

        if (results.includes(false)) {
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
            StudentHub.showNotification('Please fix the highlighted fields.', 'danger');
            return;
        }

        // Client checks passed: PHP validates again, sanitizes and stores the record
        const submitBtn = form.querySelector('[type="submit"]');
        StudentHub.setButtonLoading(submitBtn, true, 'Registering...');

        try {
            const result = await StudentHub.submitForm(form);

            if (!result.success) {
                showServerErrors(result.errors);
                StudentHub.showNotification(StudentHub.escapeHtml(result.message), 'danger', 5000);
                return;
            }

            StudentHub.showNotification(StudentHub.escapeHtml(result.message) + ' Redirecting to login...', 'success', 4000);
            form.reset();
            form.querySelectorAll('.is-valid').forEach(el => el.classList.remove('is-valid'));
            setTimeout(() => window.location.href = 'login.html', 3000);
        } catch (error) {
            StudentHub.showNotification(StudentHub.escapeHtml(error.message), 'danger', 7000);
        } finally {
            StudentHub.setButtonLoading(submitBtn, false);
        }
    });
});
