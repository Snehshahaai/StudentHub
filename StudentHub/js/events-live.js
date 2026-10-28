/**
 * StudentHub - Live Events (pages/admin-events-live.php)
 * Lists, adds, updates and deletes events through the JSON API with the
 * Fetch API. The table is re-rendered from the API's answer; no page reloads.
 */
(() => {
    'use strict';

    const { url: API, csrf: CSRF } = window.EVENTS_API;
    const $ = id => document.getElementById(id);

    const els = {
        body: $('eventsBody'), summary: $('eventsSummary'), pager: $('eventsPager'),
        filters: $('filterForm'), perPage: $('per_page'),
        form: $('eventForm'), formAlert: $('formAlert'), saveBtn: $('saveBtn'), modalTitle: $('eventModalTitle'),
        deleteText: $('deleteText'), confirmDelete: $('confirmDeleteBtn'),
    };
    const eventModal  = new bootstrap.Modal($('eventModal'));
    const deleteModal = new bootstrap.Modal($('deleteModal'));
    const STATUS_COLORS = { scheduled: 'success', cancelled: 'danger', completed: 'secondary' };
    const TEXT_FIELDS = ['title', 'event_type', 'description', 'event_date', 'start_time', 'end_time', 'venue',
        'organizer', 'seats', 'status', 'registration_deadline', 'max_team_size'];

    let page = 1;
    let editingId = null;     // null = adding
    let deletingEvent = null;
    let listRequest = 0;      // ignore answers of outdated list requests

    const esc = value => String(value ?? '').replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const notify = (message, type = 'success') => showNotification(esc(message), type);

    /* ---------- Fetch helper ---------- */

    /** Call the API; resolves with the JSON answer, rejects with { status, message, errors }. */
    async function api(method, { id, query, body } = {}) {
        const params = new URLSearchParams(query || {});
        if (id) params.set('id', id);
        const headers = { Accept: 'application/json' };
        if (method !== 'GET') headers['X-CSRF-Token'] = CSRF;
        if (body) headers['Content-Type'] = 'application/json';

        let response;
        try {
            response = await fetch(`${API}${params.size ? '?' + params : ''}`, {
                method, headers, credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
            });
        } catch {
            throw { status: 0, message: 'Could not reach the server. Check your connection and try again.', errors: {} };
        }
        let data = null;
        try { data = await response.json(); } catch { /* not JSON */ }
        if (!response.ok || !data?.success) {
            if (response.status === 401) {
                window.location.href = 'login.html';
            }
            throw { status: response.status, message: data?.message || 'Something went wrong. Please try again.', errors: data?.errors || {} };
        }
        return data;
    }

    /* ---------- List ---------- */

    const filterValues = () => {
        const data = Object.fromEntries(new FormData(els.filters));
        return { ...data, per_page: els.perPage.value, page };
    };

    async function loadEvents() {
        const request = ++listRequest;
        els.summary.textContent = 'Loading events…';
        try {
            const data = await api('GET', { query: filterValues() });
            if (request !== listRequest) return;
            page = data.page;
            renderRows(data.events);
            renderSummary(data);
            renderPager(data);
        } catch (err) {
            if (request !== listRequest) return;
            els.body.innerHTML = '';
            els.summary.textContent = err.message;
            notify(err.message, 'danger');
        }
    }

    const isFiltered = () => ['q', 'type', 'status', 'when'].some(k => els.filters.elements[k].value !== '');

    function renderRows(events) {
        if (!events.length) {
            els.body.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-5">
                <i class="fas fa-calendar-times fs-3 d-block mb-2"></i>
                ${isFiltered() ? 'No events match these filters.' : 'No events yet.'}</td></tr>`;
            return;
        }
        const today = new Date().toLocaleDateString('en-CA');
        els.body.innerHTML = events.map(ev => {
            const date = new Date(`${ev.event_date}T${ev.start_time}`);
            const full = ev.registered >= ev.seats;
            return `<tr data-id="${ev.event_id}">
                <td>
                    <a href="admin-event-view.php?id=${ev.event_id}" class="fw-bold">${esc(ev.title)}</a>
                    ${ev.is_featured ? '<span class="badge bg-primary ms-1">Featured</span>' : ''}
                    <div class="small text-muted">${esc(ev.event_type)} · ${esc(ev.organizer)}</div>
                </td>
                <td class="text-nowrap">${date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                    <div class="small text-muted">${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}${ev.event_date < today ? ' · Past' : ''}</div></td>
                <td class="d-none d-md-table-cell small">${esc(ev.venue)}</td>
                <td class="text-nowrap">${ev.registered} / ${ev.seats}${full ? '<div class="small text-danger">Full</div>' : ''}</td>
                <td><span class="badge bg-${STATUS_COLORS[ev.status] || 'secondary'}">${esc(ev.status.charAt(0).toUpperCase() + ev.status.slice(1))}</span></td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="edit" title="Edit" aria-label="Edit ${esc(ev.title)}"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-title="${esc(ev.title)}" data-registered="${ev.registered}" title="Delete" aria-label="Delete ${esc(ev.title)}"><i class="fas fa-trash-alt"></i></button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderSummary({ total, page: p, per_page: perPage, events }) {
        if (!total) {
            els.summary.textContent = `No events found${isFiltered() ? ' matching your search' : ''}.`;
            return;
        }
        const first = (p - 1) * perPage + 1;
        els.summary.textContent = `Showing ${first}–${first + events.length - 1} of ${total} event${total === 1 ? '' : 's'}${isFiltered() ? ' matching your search' : ''}`;
    }

    function renderPager({ page: p, pages }) {
        if (pages <= 1) { els.pager.innerHTML = ''; return; }
        const item = (target, label, { active = false, disabled = false, aria = '' } = {}) =>
            `<li class="page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}">
                <a class="page-link" href="#" data-page="${target}"${aria ? ` aria-label="${aria}"` : ''}${active ? ' aria-current="page"' : ''}>${label}</a></li>`;
        let html = item(p - 1, '<i class="fas fa-chevron-left"></i>', { disabled: p === 1, aria: 'Previous page' });
        for (let n = 1; n <= pages; n++) html += item(n, n, { active: n === p });
        html += item(p + 1, '<i class="fas fa-chevron-right"></i>', { disabled: p === pages, aria: 'Next page' });
        els.pager.innerHTML = html;
    }

    /* ---------- Filters + pager + row buttons ---------- */

    let searchTimer;
    els.filters.addEventListener('submit', e => e.preventDefault());
    els.filters.addEventListener('input', e => {
        page = 1;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadEvents, e.target.type === 'search' ? 300 : 0);
    });
    els.perPage.addEventListener('change', () => { page = 1; loadEvents(); });

    els.pager.addEventListener('click', e => {
        const link = e.target.closest('a[data-page]');
        if (!link) return;
        e.preventDefault();
        if (link.parentElement.classList.contains('disabled')) return;
        page = Number(link.dataset.page);
        loadEvents();
    });

    els.body.addEventListener('click', async e => {
        const button = e.target.closest('button[data-action]');
        if (!button) return;
        const id = Number(button.closest('tr').dataset.id);

        if (button.dataset.action === 'edit') {
            button.disabled = true;
            try {
                openForm((await api('GET', { id })).event);
            } catch (err) {
                notify(err.message, 'danger');
                if (err.status === 404) loadEvents();
            } finally {
                button.disabled = false;
            }
        } else {
            const n = Number(button.dataset.registered);
            deletingEvent = { id, title: button.dataset.title };
            els.deleteText.innerHTML = `Delete <strong>${esc(button.dataset.title)}</strong>?` +
                (n ? ` ${n} student registration${n === 1 ? '' : 's'} will be deleted too.` : '') + ' This cannot be undone.';
            deleteModal.show();
        }
    });

    /* ---------- Delete ---------- */

    els.confirmDelete.addEventListener('click', async () => {
        if (!deletingEvent) return;
        els.confirmDelete.disabled = true;
        try {
            const data = await api('DELETE', { id: deletingEvent.id });
            deleteModal.hide();
            notify(data.message);
            // Stay on the same page, or step back when its last row was just removed
            if (els.body.querySelectorAll('tr[data-id]').length === 1 && page > 1) page--;
            loadEvents();
        } catch (err) {
            deleteModal.hide();
            notify(err.message, 'danger');
            if (err.status === 404) loadEvents();
        } finally {
            els.confirmDelete.disabled = false;
            deletingEvent = null;
        }
    });

    /* ---------- Add / edit form ---------- */

    const teamBox = $('f_is_team_event'), teamSize = $('f_max_team_size');
    teamBox.addEventListener('change', () => {
        teamSize.disabled = !teamBox.checked;
        if (!teamBox.checked) teamSize.value = '';
    });

    function clearErrors() {
        els.formAlert.classList.add('d-none');
        els.form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function openForm(ev = null) {
        clearErrors();
        els.form.reset();
        editingId = ev ? ev.event_id : null;
        els.modalTitle.textContent = ev ? 'Edit Event' : 'Add Event';
        $('posterNote').classList.toggle('d-none', !ev);

        const v = ev || { start_time: '10:00', seats: 100, status: 'scheduled', is_team_event: false, is_featured: false };
        TEXT_FIELDS.forEach(name => { els.form.elements[name].value = v[name] ?? ''; });
        teamBox.checked = v.is_team_event;
        $('f_is_featured').checked = v.is_featured;
        teamSize.disabled = !v.is_team_event;
        eventModal.show();
    }

    function showErrors(err) {
        const fields = Object.entries(err.errors || {}).filter(([name]) => els.form.elements[name]);
        fields.forEach(([name, message]) => {
            const input = els.form.elements[name];
            input.classList.add('is-invalid');
            const feedback = input.parentElement.querySelector('.invalid-feedback');
            if (feedback) feedback.textContent = message;
        });
        els.formAlert.textContent = err.message;
        els.formAlert.classList.remove('d-none');
        els.form.querySelector('.is-invalid')?.focus();
    }

    els.form.addEventListener('submit', async e => {
        e.preventDefault();
        clearErrors();
        const body = Object.fromEntries(TEXT_FIELDS.map(name => [name, els.form.elements[name].value]));
        body.is_team_event = teamBox.checked;
        body.is_featured = $('f_is_featured').checked;

        els.saveBtn.disabled = true;
        els.saveBtn.textContent = 'Saving…';
        try {
            const data = editingId
                ? await api('PUT', { id: editingId, body })
                : await api('POST', { body });
            eventModal.hide();
            notify(data.message);
            if (!editingId) {                 // show the new event: reset to the first page of everything
                els.filters.reset();
                els.filters.elements.sort.value = 'newest';
                page = 1;
            }
            loadEvents();
        } catch (err) {
            showErrors(err);
            if (err.status === 404) loadEvents();
        } finally {
            els.saveBtn.disabled = false;
            els.saveBtn.textContent = 'Save event';
        }
    });

    $('addEventBtn').addEventListener('click', () => openForm());

    loadEvents();
})();
