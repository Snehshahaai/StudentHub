/**
 * StudentHub - Contact Form
 * Quick client-side checks, then submits to php/contact.php which validates
 * again, sanitizes and stores the message. Shows the server's reply inline.
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contactForm');
    if (!form) return;

    const fields = {
        name: form.querySelector('#contactName'),
        email: form.querySelector('#contactEmail'),
        subject: form.querySelector('#contactSubject'),
        message: form.querySelector('#contactMessage')
    };
    const resultBox = document.getElementById('contactResult');
    const counter = document.getElementById('contactCounter');

    const validators = {
        name: v => !v ? 'Please enter your name.'
            : /^(?=.{2,50}$)[A-Za-z]+(?:[ .'-][A-Za-z]+)*\.?$/.test(v) ? '' : 'Name must be 2-50 letters.',
        email: v => !v ? 'Please enter your email address.'
            : /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(v) ? '' : 'Enter a valid email address.',
        subject: v => v ? '' : 'Please choose a subject.',
        message: v => !v ? 'Please write your message.'
            : v.length < 10 ? 'Your message is too short (minimum 10 characters).' : ''
    };

    function setFieldState(input, message) {
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        input.classList.toggle('is-invalid', Boolean(message));
        input.classList.toggle('is-valid', !message);
        if (feedback) {
            feedback.textContent = message;
            feedback.classList.toggle('d-block', Boolean(message));
        }
    }

    function validateField(name) {
        const message = validators[name](fields[name].value.trim());
        setFieldState(fields[name], message);
        return !message;
    }

    function showResult(success, message) {
        resultBox.className = `alert alert-${success ? 'success' : 'danger'}`;
        resultBox.innerHTML = `<i class="fas ${success ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>` +
            StudentHub.escapeHtml(message);
    }

    Object.keys(fields).forEach(name => {
        const input = fields[name];
        input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'blur', () => validateField(name));
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) validateField(name);
        });
    });

    const updateCounter = () => { counter.textContent = `${fields.message.value.length} / 1000`; };
    fields.message.addEventListener('input', updateCounter);

    form.addEventListener('submit', async event => {
        event.preventDefault();
        resultBox.classList.add('d-none');

        const valid = Object.keys(fields).map(validateField).every(Boolean);
        if (!valid) {
            form.querySelector('.is-invalid')?.focus();
            StudentHub.showNotification('Please fix the highlighted fields.', 'danger');
            return;
        }

        const submitBtn = form.querySelector('[type="submit"]');
        StudentHub.setButtonLoading(submitBtn, true, 'Sending...');

        try {
            const result = await StudentHub.submitForm(form);

            if (result.success) {
                form.reset();
                updateCounter();
                Object.values(fields).forEach(input => input.classList.remove('is-valid'));
            } else {
                Object.entries(result.errors || {}).forEach(([name, message]) => {
                    if (fields[name]) setFieldState(fields[name], message);
                });
                form.querySelector('.is-invalid')?.focus();
            }

            showResult(result.success, result.message);
            StudentHub.showNotification(StudentHub.escapeHtml(result.message), result.success ? 'success' : 'danger', 5000);
        } catch (error) {
            showResult(false, error.message);
        } finally {
            StudentHub.setButtonLoading(submitBtn, false);
        }
    });
});
