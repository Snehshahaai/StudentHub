/**
 * StudentHub - Logged-in user data & session timeout
 * Used on the protected pages (student dashboard, profile, admin dashboard).
 *
 * - Fills every [data-me="field"] element from php/me.php.
 * - Session timeout: the server ends idle sessions (php/auth.php). While the
 *   user is active on the page we ping me.php now and then to keep the
 *   session alive; when they're idle we warn 60 s before it expires, then send
 *   them to the login page.
 */

document.addEventListener('DOMContentLoaded', async () => {
    const result = await fetchMe();
    if (!result) return;

    fillPage(result);
    startSessionTimer(result.session);
});

// Returns the JSON from php/me.php, or null after redirecting / warning
async function fetchMe() {
    let response;
    try {
        response = await fetch('../php/me.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
    } catch {
        StudentHub.showNotification('Could not reach the server.', 'warning', 5000);
        return null;
    }

    if (response.status === 401) {
        const body = await response.json().catch(() => ({}));
        goToLogin(body.message || 'Please log in to view this page.');
        return null;
    }

    try {
        const result = await response.json();
        if (!result.success) {
            StudentHub.showNotification(StudentHub.escapeHtml(result.message), 'danger', 5000);
            return null;
        }
        return result;
    } catch {
        StudentHub.showNotification('Unexpected server response. Is PHP running?', 'warning', 7000);
        return null;
    }
}

function goToLogin(message) {
    const params = new URLSearchParams({ status: 'error', message });
    window.location.replace(`login.html?${params}`);
}

/* ==========================================================================
   FILL THE PAGE
   ========================================================================== */
function fillPage(result) {
    const values = result.student ? studentValues(result.student) : { ...result.staff };

    document.querySelectorAll('[data-me]').forEach(el => {
        const value = values[el.dataset.me];
        if (value === undefined) return;
        if ('value' in el && el.tagName !== 'BUTTON') {
            el.value = value;
        } else {
            el.textContent = value;        // textContent, so names can never inject HTML
        }
    });

    const note = document.querySelector('[data-me="attendance_note"]');
    if (note && result.student?.attendance !== null && result.student?.attendance < 75) {
        note.classList.replace('text-success', 'text-danger');
    }
}

function studentValues(s) {
    return {
        ...s,
        phone: `+91 ${s.mobile}`,
        address: s.address || '',
        course_semester: `${s.course} | Sem ${s.semester}`,
        attendance: s.attendance === null ? 'N/A' : `${s.attendance}%`,
        attendance_note: s.attendance === null ? 'No lectures recorded yet'
            : s.attendance >= 75 ? 'Above 75% requirement' : 'Below 75% requirement',
        pending_tasks: `${s.pending_tasks} Active`,
        cgpa: s.cgpa ?? 'N/A',
        materials: `${s.materials} File${s.materials === 1 ? '' : 's'}`
    };
}

/* ==========================================================================
   SESSION TIMEOUT
   ========================================================================== */
function startSessionTimer(session) {
    const idleMs = session.idle_timeout * 1000;
    const warnMs = Math.min(60000, idleMs / 2);          // warn 60 s before (or halfway for short tests)
    const pingEveryMs = Math.min(60000, idleMs / 4);     // at most one keep-alive per minute

    let expiresAt = Date.now() + session.seconds_left * 1000;
    let lastPing = Date.now();
    let warned = false;

    // Any real activity keeps the session alive (throttled server ping)
    const onActivity = async () => {
        if (Date.now() - lastPing < pingEveryMs) return;
        lastPing = Date.now();
        const result = await fetchMe();
        if (!result) return;
        expiresAt = Date.now() + result.session.seconds_left * 1000;
        if (warned) {
            warned = false;
            StudentHub.showNotification('You are still signed in.', 'success', 2500);
        }
    };
    ['click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(type =>
        document.addEventListener(type, onActivity, { passive: true }));

    setInterval(() => {
        const left = expiresAt - Date.now();
        if (left <= 0) {
            goToLogin('Your session expired due to inactivity. Please log in again.');
        } else if (left <= warnMs && !warned) {
            warned = true;
            StudentHub.showNotification(
                `Your session will expire in ${Math.ceil(left / 1000)} seconds due to inactivity. ` +
                'Move the mouse or press a key to stay signed in.', 'warning', left);
        }
    }, 1000);
}
