/**
 * StudentHub - Data List Engine
 * Loads records from an external JSON file with the Fetch API and renders
 * them with live search, filtering, sorting and pagination.
 *
 * Usage:
 *   new DataList({
 *       source: '../data/notices.json',
 *       container: '#noticeList',
 *       search: '#noticeSearch',            // text input (optional)
 *       searchFields: ['title', 'summary'],
 *       filter: '#noticeFilter',            // <select>, value matched against filterField (optional)
 *       filterField: 'category',
 *       sort: '#noticeSort',                // <select>, values are keys of `sorters` (optional)
 *       sorters: { 'date-desc': (a, b) => ... },
 *       pageSize: 6,
 *       pageSizeSelect: '#noticePageSize',  // <select> (optional)
 *       pagination: '#noticePagination',
 *       summary: '#noticeSummary',          // "Showing 1-6 of 14" text (optional)
 *       renderItem: (item, helpers) => '<div>...</div>',
 *       afterRender: (container, items) => {}
 *   });
 */

class DataList {
    constructor(config) {
        this.config = config;
        this.items = [];
        this.page = 1;
        this.pageSize = config.pageSize || 6;

        const $ = sel => (sel ? document.querySelector(sel) : null);
        this.el = {
            container: $(config.container),
            search: $(config.search),
            filter: $(config.filter),
            sort: $(config.sort),
            pageSize: $(config.pageSizeSelect),
            pagination: $(config.pagination),
            summary: $(config.summary)
        };

        if (!this.el.container) return;
        this.bindControls();
        this.load();
    }

    /* ---------- Data loading (Fetch API) ---------- */
    async load() {
        this.renderStatus('loading');
        try {
            const response = await fetch(this.config.source);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} while loading ${this.config.source}`);
            }
            const data = await response.json();
            if (!Array.isArray(data)) throw new Error('Expected a JSON array');

            this.items = data;
            this.populateFilterOptions();
            this.render();
        } catch (error) {
            console.error('[DataList]', error);
            this.renderStatus('error', error);
        }
    }

    /* ---------- Controls ---------- */
    bindControls() {
        const { search, filter, sort, pageSize, pagination } = this.el;
        const reset = () => { this.page = 1; this.render(); };

        if (search) {
            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(reset, 200); // debounce typing
            });
        }
        if (filter) filter.addEventListener('change', reset);
        if (sort) sort.addEventListener('change', reset);
        if (pageSize) {
            pageSize.value = String(this.pageSize);
            pageSize.addEventListener('change', () => {
                this.pageSize = parseInt(pageSize.value, 10) || this.pageSize;
                reset();
            });
        }

        if (pagination) {
            pagination.addEventListener('click', e => {
                const btn = e.target.closest('[data-page]');
                if (!btn || btn.closest('.disabled')) return;
                e.preventDefault();
                this.page = parseInt(btn.dataset.page, 10);
                this.render();
                this.el.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    // Fill the filter <select> with every distinct value found in the data
    populateFilterOptions() {
        const { filter } = this.el;
        const field = this.config.filterField;
        if (!filter || !field || filter.dataset.static !== undefined) return;

        const values = [...new Set(this.items.map(item => item[field]))].sort();
        values.forEach(value => {
            const count = this.items.filter(item => item[field] === value).length;
            filter.insertAdjacentHTML('beforeend',
                `<option value="${escapeHtml(value)}">${escapeHtml(value)} (${count})</option>`);
        });
    }

    /* ---------- Pipeline: filter -> search -> sort -> paginate ---------- */
    getQuery() {
        return this.el.search ? this.el.search.value.trim().toLowerCase() : '';
    }

    getVisibleItems() {
        const { filterField, searchFields = [], sorters = {} } = this.config;
        const filterValue = this.el.filter ? this.el.filter.value : 'all';
        const query = this.getQuery();

        let result = this.items;

        if (filterField && filterValue && filterValue !== 'all') {
            result = result.filter(item => String(item[filterField]) === filterValue);
        }

        if (query) {
            result = result.filter(item =>
                searchFields.some(field => String(item[field] ?? '').toLowerCase().includes(query)));
        }

        const sorter = this.el.sort ? sorters[this.el.sort.value] : null;
        if (sorter) result = [...result].sort(sorter);

        return result;
    }

    render() {
        const visible = this.getVisibleItems();
        const totalPages = Math.max(1, Math.ceil(visible.length / this.pageSize));
        this.page = Math.min(Math.max(1, this.page), totalPages);

        const start = (this.page - 1) * this.pageSize;
        const pageItems = visible.slice(start, start + this.pageSize);

        if (visible.length === 0) {
            this.renderStatus('empty');
        } else {
            const helpers = { escape: escapeHtml, highlight: text => highlight(text, this.getQuery()), formatDate };
            this.el.container.innerHTML = pageItems.map(item => this.config.renderItem(item, helpers)).join('');
        }

        this.renderSummary(visible.length, start, pageItems.length);
        this.renderPagination(totalPages);

        if (this.config.afterRender) this.config.afterRender(this.el.container, pageItems);
    }

    renderSummary(total, start, shown) {
        if (!this.el.summary) return;
        this.el.summary.textContent = total === 0
            ? `No results out of ${this.items.length}`
            : `Showing ${start + 1}–${start + shown} of ${total}` +
              (total !== this.items.length ? ` (filtered from ${this.items.length})` : '');
    }

    renderPagination(totalPages) {
        const nav = this.el.pagination;
        if (!nav) return;
        if (totalPages <= 1) { nav.innerHTML = ''; return; }

        const item = (label, page, { disabled = false, active = false, aria = '' } = {}) => `
            <li class="page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}">
                <a class="page-link" href="#" data-page="${page}"${aria ? ` aria-label="${aria}"` : ''}${active ? ' aria-current="page"' : ''}>${label}</a>
            </li>`;
        const ellipsis = '<li class="page-item disabled"><span class="page-link">…</span></li>';

        let html = item('<i class="fas fa-chevron-left"></i>', this.page - 1, { disabled: this.page === 1, aria: 'Previous page' });
        pageWindow(this.page, totalPages).forEach(p => {
            html += p === null ? ellipsis : item(p, p, { active: p === this.page });
        });
        html += item('<i class="fas fa-chevron-right"></i>', this.page + 1, { disabled: this.page === totalPages, aria: 'Next page' });

        nav.innerHTML = `<ul class="pagination sh-pagination justify-content-center flex-wrap mb-0">${html}</ul>`;
    }

    renderStatus(state, error) {
        const box = (icon, title, text) => `
            <div class="col-12">
                <div class="sh-data-status">
                    <i class="${icon}"></i>
                    <h5>${title}</h5>
                    <p class="text-muted mb-0">${text}</p>
                </div>
            </div>`;

        if (state === 'loading') {
            this.el.container.innerHTML = box('fas fa-circle-notch fa-spin', 'Loading…', `Fetching ${escapeHtml(this.config.source)}`);
        } else if (state === 'empty') {
            this.el.container.innerHTML = box('fas fa-search', 'No matches found', 'Try a different keyword or clear the filters.');
        } else {
            const hint = location.protocol === 'file:'
                ? 'Browsers block fetch() on file:// pages. Open the site through a local server (e.g. VS Code Live Server or <code>python3 -m http.server</code>).'
                : escapeHtml(error ? error.message : 'Unknown error');
            this.el.container.innerHTML = box('fas fa-triangle-exclamation text-danger', 'Could not load data', hint);
            if (this.el.summary) this.el.summary.textContent = '';
            if (this.el.pagination) this.el.pagination.innerHTML = '';
        }
    }
}

/* ---------- Helpers (escapeHtml comes from main.js) ---------- */

// Page numbers to show, with null where an ellipsis goes: 1 … 4 5 6 … 12
function pageWindow(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [1];
    const from = Math.max(2, current - 1);
    const to = Math.min(total - 1, current + 1);
    if (from > 2) pages.push(null);
    for (let p = from; p <= to; p++) pages.push(p);
    if (to < total - 1) pages.push(null);
    pages.push(total);
    return pages;
}

// Escape text, then wrap matches of the search query in <mark>
function highlight(text, query) {
    const safe = escapeHtml(text);
    if (!query) return safe;
    const pattern = escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(new RegExp(`(${pattern})`, 'gi'), '<mark>$1</mark>');
}

function formatDate(iso) {
    const date = new Date(`${iso}T00:00:00`);
    return isNaN(date) ? iso : date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/* ==========================================================================
   PAGE SETUPS
   ========================================================================== */
const byDate = (a, b) => a.date.localeCompare(b.date);
const byTitle = (a, b) => a.title.localeCompare(b.title);

document.addEventListener('DOMContentLoaded', () => {
    initNoticeList();
    initEventList();
    initFaqList();
});

/* ---------- Notices ---------- */
function initNoticeList() {
    const priorityRank = { high: 0, medium: 1, low: 2 };
    const categoryStyle = {
        Exam:      { color: 'danger',    icon: 'fa-file-alt' },
        Placement: { color: 'warning',   icon: 'fa-briefcase' },
        Event:     { color: 'success',   icon: 'fa-calendar-alt' },
        Academic:  { color: 'info',      icon: 'fa-graduation-cap' },
        Holiday:   { color: 'secondary', icon: 'fa-umbrella-beach' }
    };
    let byId = {};

    new DataList({
        source: '../data/notices.json',
        container: '#noticeList',
        search: '#noticeSearch',
        searchFields: ['title', 'summary', 'details', 'postedBy', 'category'],
        filter: '#noticeFilter',
        filterField: 'category',
        sort: '#noticeSort',
        sorters: {
            'date-desc': (a, b) => byDate(b, a),
            'date-asc': byDate,
            'priority': (a, b) => priorityRank[a.priority] - priorityRank[b.priority] || byDate(b, a),
            'title-asc': byTitle
        },
        pageSize: 6,
        pageSizeSelect: '#noticePageSize',
        pagination: '#noticePagination',
        summary: '#noticeSummary',
        renderItem: (n, { escape, highlight, formatDate }) => {
            byId[n.id] = n;
            const style = categoryStyle[n.category] || { color: 'primary', icon: 'fa-bullhorn' };
            const urgent = n.priority === 'high'
                ? '<span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle me-1"></i>Urgent</span>' : '';
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 p-3 shadow-sm border-top border-4 border-${style.color}">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <span class="badge bg-${style.color}${style.color === 'warning' ? ' text-dark' : ''}">
                                    <i class="fas ${style.icon} me-1"></i>${escape(n.category)}
                                </span>${urgent}
                            </div>
                            <h5 class="card-title fw-bold">${highlight(n.title)}</h5>
                            <p class="text-muted small mb-2">
                                <i class="far fa-clock me-1"></i>${formatDate(n.date)}
                                <span class="mx-1">·</span><i class="fas fa-user-tie me-1"></i>${escape(n.postedBy)}
                            </p>
                            <p class="card-text">${highlight(n.summary)}</p>
                            <button class="btn btn-outline-primary btn-sm mt-auto align-self-start" data-notice-id="${n.id}">
                                Read Full Details
                            </button>
                        </div>
                    </div>
                </div>`;
        },
        afterRender: container => {
            container.querySelectorAll('[data-notice-id]').forEach(btn => {
                btn.addEventListener('click', () => openNoticeModal(byId[btn.dataset.noticeId]));
            });
        }
    });

    function openNoticeModal(notice) {
        const modal = document.getElementById('noticeDetailModal');
        if (!modal || !notice) return;
        modal.querySelector('[data-field="title"]').textContent = notice.title;
        modal.querySelector('[data-field="meta"]').textContent =
            `${notice.category} · ${formatDate(notice.date)} · ${notice.postedBy}`;
        modal.querySelector('[data-field="summary"]').textContent = notice.summary;
        modal.querySelector('[data-field="details"]').textContent = notice.details;
        window.StudentHub.openModal('noticeDetailModal');
    }
}

/* ---------- Events ---------- */
function initEventList() {
    const typeIcon = {
        Technical: 'fa-laptop-code', Cultural: 'fa-music', Sports: 'fa-trophy',
        Workshop: 'fa-tools', Seminar: 'fa-chalkboard-teacher'
    };
    const today = new Date().toISOString().slice(0, 10);

    new DataList({
        source: '../data/events.json',
        container: '#eventList',
        search: '#eventSearch',
        searchFields: ['title', 'description', 'venue', 'organizer', 'type'],
        filter: '#eventFilter',
        filterField: 'type',
        sort: '#eventSort',
        sorters: {
            // Upcoming events soonest-first, completed ones pushed to the end
            'upcoming': (a, b) => (a.date < today) - (b.date < today) || byDate(a, b),
            'date-asc': byDate,
            'date-desc': (a, b) => byDate(b, a),
            'seats-desc': (a, b) => b.seats - a.seats,
            'title-asc': byTitle
        },
        pageSize: 4,
        pagination: '#eventPagination',
        summary: '#eventSummary',
        renderItem: (ev, { escape, highlight, formatDate }) => {
            const [day, month] = formatDate(ev.date).split(' ');
            const past = ev.date < today;
            return `
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm sh-event-card${past ? ' sh-event-past' : ''}">
                        <div class="card-body d-flex gap-3">
                            <div class="sh-event-date">
                                <span class="day">${day}</span>
                                <span class="month">${month}</span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                    <h5 class="card-title fw-bold mb-0">${highlight(ev.title)}</h5>
                                    <span class="badge bg-primary text-nowrap">
                                        <i class="fas ${typeIcon[ev.type] || 'fa-star'} me-1"></i>${escape(ev.type)}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">
                                    <i class="far fa-clock me-1"></i>${escape(ev.time)}
                                    <span class="mx-1">·</span><i class="fas fa-map-marker-alt me-1"></i>${highlight(ev.venue)}
                                </p>
                                <p class="card-text small mb-2">${highlight(ev.description)}</p>
                                <p class="small mb-0 text-muted">
                                    <i class="fas fa-users me-1"></i>${ev.seats} seats
                                    <span class="mx-1">·</span>${highlight(ev.organizer)}
                                    ${past ? '<span class="badge bg-secondary ms-1">Completed</span>' : ''}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>`;
        }
    });
}

/* ---------- FAQs ---------- */
function initFaqList() {
    new DataList({
        source: '../data/faqs.json',
        container: '#faqList',
        search: '#faqSearch',
        searchFields: ['question', 'answer', 'category'],
        filter: '#faqFilter',
        filterField: 'category',
        sort: '#faqSort',
        sorters: {
            'default': (a, b) => a.id - b.id,
            'question-asc': (a, b) => a.question.localeCompare(b.question),
            'category-asc': (a, b) => a.category.localeCompare(b.category) || a.id - b.id
        },
        pageSize: 5,
        pagination: '#faqPagination',
        summary: '#faqSummary',
        renderItem: (f, { escape, highlight }) => `
            <div class="faq-item">
                <div class="faq-header" role="button" tabindex="0">
                    <h5><i class="fas ${escape(f.icon || 'fa-question-circle')} text-primary me-2"></i>${highlight(f.question)}</h5>
                    <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-content">
                    <div class="faq-content-inner">
                        <span class="badge bg-surface border text-muted mb-2">${escape(f.category)}</span>
                        <p class="mb-2">${highlight(f.answer)}</p>
                        ${f.link ? `<a href="${escape(f.link.href)}" class="small">${escape(f.link.label)} <i class="fas fa-arrow-right ms-1"></i></a>` : ''}
                    </div>
                </div>
            </div>`,
        afterRender: container => {
            // While searching, open every match so the highlighted answer is visible
            if (document.getElementById('faqSearch')?.value.trim()) {
                container.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.add('active');
                    const content = item.querySelector('.faq-content');
                    content.style.maxHeight = content.scrollHeight + 'px';
                });
            }
        }
    });
}
