/**
 * StudentHub - Logged-in student data
 * Asks php/me.php who is logged in and fills every [data-me="field"]
 * element on the page. Not logged in -> back to the login page.
 */

document.addEventListener('DOMContentLoaded', async () => {
    let response;
    try {
        response = await fetch('../php/me.php', { headers: { Accept: 'application/json' } });
    } catch {
        StudentHub.showNotification('Could not reach the server. Showing demo data.', 'warning', 5000);
        return;
    }

    if (response.status === 401) {
        const params = new URLSearchParams({ status: 'error', message: 'Please log in to view this page.' });
        window.location.replace(`login.html?${params}`);
        return;
    }

    let result;
    try {
        result = await response.json();
    } catch {
        // Static server without PHP: leave the sample content in place
        StudentHub.showNotification('PHP is not running, so demo data is shown. Start it with: php -S localhost:8000 router.php', 'warning', 7000);
        return;
    }
    if (!result.success) {
        StudentHub.showNotification(StudentHub.escapeHtml(result.message), 'danger', 5000);
        return;
    }

    const s = result.student;
    const values = {
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
    if (note && s.attendance !== null && s.attendance < 75) {
        note.classList.replace('text-success', 'text-danger');
    }
});
