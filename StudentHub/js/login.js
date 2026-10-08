/**
 * StudentHub - Login Form
 * Basic client checks, then posts to php/login.php. On success PHP has
 * started the session, so we move on to the dashboard.
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const fields = {
        login: form.querySelector('#loginId'),
        password: form.querySelector('#loginPassword')
    };

    function setFieldState(input, message) {
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        input.classList.toggle('is-invalid', Boolean(message));
        if (feedback) feedback.textContent = message;
    }

    function validate() {
        const login = fields.login.value.trim();
        const valid = login.includes('@')
            ? /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(login)
            : /^[a-z][a-z0-9_.]{3,19}$/i.test(login);
        setFieldState(fields.login,
            !login ? 'Please enter your email address or username.'
                : valid ? '' : 'Enter a valid email address or username.');
        setFieldState(fields.password, fields.password.value ? '' : 'Please enter your password.');
        return !form.querySelector('.is-invalid');
    }

    Object.values(fields).forEach(input => input.addEventListener('input', () => {
        if (input.classList.contains('is-invalid')) setFieldState(input, '');
    }));

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!validate()) {
            form.querySelector('.is-invalid')?.focus();
            return;
        }

        const submitBtn = form.querySelector('[type="submit"]');
        StudentHub.setButtonLoading(submitBtn, true, 'Logging in...');

        try {
            const result = await StudentHub.submitForm(form);

            if (result.success) {
                StudentHub.showNotification(StudentHub.escapeHtml(result.message), 'success', 2500);
                setTimeout(() => window.location.href = 'student-dashboard.html', 1200);
                return;     // keep the button disabled while redirecting
            }

            Object.entries(result.errors || {}).forEach(([name, message]) => {
                if (fields[name]) setFieldState(fields[name], message);
            });
            fields.password.value = '';
            (form.querySelector('.is-invalid') || fields.password).focus();
            StudentHub.showNotification(StudentHub.escapeHtml(result.message), 'danger', 5000);
        } catch (error) {
            StudentHub.showNotification(StudentHub.escapeHtml(error.message), 'danger', 7000);
        }
        StudentHub.setButtonLoading(submitBtn, false);
    });
});
