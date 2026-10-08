/**
 * StudentHub - Student Registration Form Validation
 * Validates every field with Regular Expressions and shows inline errors.
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
        // local-part@domain.tld
        email: /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/,
        // Indian mobile number: 10 digits starting with 6, 7, 8 or 9
        mobile: /^[6-9]\d{9}$/,
        // Min 8 chars with at least one uppercase, lowercase, digit and special character
        password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,}$/,
        // Value must not be empty
        selected: /^.+$/
    };

    const fields = {
        fullName: form.querySelector('#fullName'),
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
        email(value) {
            if (!value) return 'Email address is required.';
            if (!patterns.email.test(value)) return 'Enter a valid email address (e.g. student@university.edu).';
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
