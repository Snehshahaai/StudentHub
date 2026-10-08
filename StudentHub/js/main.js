/**
 * StudentHub - Dynamic UI Engine & Interactive Features
 * Features:
 * - Light/Dark Theme Switcher with LocalStorage persistence
 * - Animated Mobile Hamburger Menu Drawer
 * - Dynamic Collapsible FAQ Accordion
 * - Modal Popup Manager (triggerable by data-modal-target or JS)
 * - Custom Image & Content Slider / Carousel
 * - Dynamic Floating Notification Banner / Toast System
 * - Visual FX: scroll reveal, scroll progress, card spotlight, button ripple
 * - Server form submission (PHP) with success/error messages
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initHamburgerMenu();
    initFaqAccordion();
    initModals();
    initSliders();
    initNotificationSystem();
    initDemoInteractions();
    initVisualFx();
    initFlashMessage();
});

/* ==========================================================================
   1. LIGHT / DARK THEME SWITCHER
   ========================================================================== */
function initTheme() {
    // Dark neon theme is the default look; a saved choice still wins
    const savedTheme = localStorage.getItem('sh_theme') || 'dark';
    
    setTheme(savedTheme);

    // Attach click listener to all theme toggle buttons on the page
    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            
            showNotification(
                `Switched to ${newTheme.toUpperCase()} theme mode!`, 
                'info', 
                2500
            );
        });
    });
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('sh_theme', theme);

    // Update toggle button visuals
    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
        const icon = btn.querySelector('i');
        const text = btn.querySelector('.theme-text');
        
        if (theme === 'dark') {
            if (icon) icon.className = 'fas fa-sun text-warning';
            if (text) text.textContent = 'Light';
            btn.setAttribute('title', 'Switch to Light Mode');
            btn.setAttribute('aria-label', 'Switch to Light Mode');
        } else {
            if (icon) icon.className = 'fas fa-moon text-primary';
            if (text) text.textContent = 'Dark';
            btn.setAttribute('title', 'Switch to Dark Mode');
            btn.setAttribute('aria-label', 'Switch to Dark Mode');
        }
    });
}

/* ==========================================================================
   2. HAMBURGER MENU & RESPONSIVE NAV
   ========================================================================== */
function initHamburgerMenu() {
    const hamburgerBtns = document.querySelectorAll('.sh-hamburger-btn');
    
    hamburgerBtns.forEach(btn => {
        const targetId = btn.getAttribute('data-nav-target') || 'shNavMenu';
        const navMenu = document.getElementById(targetId);
        
        if (!navMenu) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = navMenu.classList.contains('show') || btn.classList.contains('active');
            
            if (isOpen) {
                closeMobileMenu(btn, navMenu);
            } else {
                openMobileMenu(btn, navMenu);
            }
        });
    });

    // Close mobile menu on clicking backdrop or external link
    document.addEventListener('click', (e) => {
        const activeNavs = document.querySelectorAll('.sh-nav-menu.show');
        activeNavs.forEach(navMenu => {
            const btn = document.querySelector(`[data-nav-target="${navMenu.id}"]`);
            if (!navMenu.contains(e.target) && btn && !btn.contains(e.target)) {
                closeMobileMenu(btn, navMenu);
            }
        });
    });

    // Close menu when resizing back to desktop width
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            document.querySelectorAll('.sh-nav-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.sh-hamburger-btn').forEach(btn => {
                btn.classList.remove('active');
            });
        }
    });
}

function openMobileMenu(btn, navMenu) {
    btn.classList.add('active');
    navMenu.classList.add('show');
    btn.setAttribute('aria-expanded', 'true');
}

function closeMobileMenu(btn, navMenu) {
    btn.classList.remove('active');
    navMenu.classList.remove('show');
    btn.setAttribute('aria-expanded', 'false');
}

/* ==========================================================================
   3. COLLAPSIBLE FAQ ACCORDION
   ========================================================================== */
function initFaqAccordion() {
    const accordionContainers = document.querySelectorAll('.faq-accordion');

    accordionContainers.forEach(container => {
        // Delegated so it also works for FAQ items rendered later from JSON
        const toggle = header => {
            const item = header.closest('.faq-item');
            const content = item && item.querySelector('.faq-content');
            if (!content) return;

            const isActive = item.classList.contains('active');

            // If container has single-open attribute, collapse other items
            if (container.hasAttribute('data-single-open') && !isActive) {
                container.querySelectorAll('.faq-item.active').forEach(otherItem => {
                    otherItem.classList.remove('active');
                    const otherContent = otherItem.querySelector('.faq-content');
                    if (otherContent) otherContent.style.maxHeight = null;
                });
            }

            // Toggle current item
            if (isActive) {
                item.classList.remove('active');
                content.style.maxHeight = null;
            } else {
                item.classList.add('active');
                content.style.maxHeight = content.scrollHeight + 'px';
            }
        };

        container.addEventListener('click', e => {
            const header = e.target.closest('.faq-header');
            if (header) toggle(header);
        });

        container.addEventListener('keydown', e => {
            const header = e.target.closest('.faq-header');
            if (header && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                toggle(header);
            }
        });

        // Initialize any default open item
        const defaultOpen = container.querySelector('.faq-item.active');
        if (defaultOpen) {
            const content = defaultOpen.querySelector('.faq-content');
            if (content) content.style.maxHeight = content.scrollHeight + 'px';
        }
    });

    // Expand All / Collapse All buttons
    const expandAllBtn = document.getElementById('faqExpandAll');
    const collapseAllBtn = document.getElementById('faqCollapseAll');

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.add('active');
                const content = item.querySelector('.faq-content');
                if (content) content.style.maxHeight = content.scrollHeight + 'px';
            });
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
                const content = item.querySelector('.faq-content');
                if (content) content.style.maxHeight = null;
            });
        });
    }
}

/* ==========================================================================
   4. MODAL POPUP MANAGER
   ========================================================================== */
function initModals() {
    // Triggers with data-modal-target="modalId"
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = trigger.getAttribute('data-modal-target');
            openModal(modalId);
        });
    });

    // Close buttons with data-modal-close or .sh-modal-close
    document.querySelectorAll('[data-modal-close], .sh-modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = closeBtn.closest('.sh-modal-overlay');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Close on overlay backdrop click
    document.querySelectorAll('.sh-modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // Close on Escape key press
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.sh-modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn(`Modal with ID "${modalId}" not found.`);
        return;
    }
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Focus first interactive element inside modal
    const focusable = modal.querySelector('button, input, select, textarea, a[href]');
    if (focusable) focusable.focus();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/* ==========================================================================
   5. IMAGE / CONTENT SLIDER (CAROUSEL)
   ========================================================================== */
function initSliders() {
    const sliders = document.querySelectorAll('.sh-slider');

    sliders.forEach(slider => {
        const track = slider.querySelector('.sh-slider-track');
        const slides = slider.querySelectorAll('.sh-slide');
        const nextBtn = slider.querySelector('.sh-slider-next');
        const prevBtn = slider.querySelector('.sh-slider-prev');
        const dotsContainer = slider.querySelector('.sh-slider-dots');

        if (!track || slides.length === 0) return;

        let currentIndex = 0;
        let autoPlayTimer = null;
        const autoPlaySpeed = parseInt(slider.getAttribute('data-autoplay'), 10) || 4000;

        // Build dots if dots container exists
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            slides.forEach((_, idx) => {
                const dot = document.createElement('button');
                dot.className = `sh-slider-dot ${idx === 0 ? 'active' : ''}`;
                dot.setAttribute('aria-label', `Go to slide ${idx + 1}`);
                dot.addEventListener('click', () => goToSlide(idx));
                dotsContainer.appendChild(dot);
            });
        }

        function updateSlider() {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            
            // Update active slide class
            slides.forEach((slide, idx) => {
                slide.classList.toggle('active', idx === currentIndex);
            });

            // Update dots
            if (dotsContainer) {
                const dots = dotsContainer.querySelectorAll('.sh-slider-dot');
                dots.forEach((dot, idx) => {
                    dot.classList.toggle('active', idx === currentIndex);
                });
            }
        }

        function goToSlide(index) {
            if (index < 0) {
                currentIndex = slides.length - 1;
            } else if (index >= slides.length) {
                currentIndex = 0;
            } else {
                currentIndex = index;
            }
            updateSlider();
            resetAutoPlay();
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                goToSlide(currentIndex + 1);
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                goToSlide(currentIndex - 1);
            });
        }

        // Auto play logic
        function startAutoPlay() {
            if (slider.hasAttribute('data-autoplay')) {
                autoPlayTimer = setInterval(() => {
                    goToSlide(currentIndex + 1);
                }, autoPlaySpeed);
            }
        }

        function stopAutoPlay() {
            if (autoPlayTimer) {
                clearInterval(autoPlayTimer);
                autoPlayTimer = null;
            }
        }

        function resetAutoPlay() {
            stopAutoPlay();
            startAutoPlay();
        }

        // Pause autoplay on mouse enter / resume on leave
        slider.addEventListener('mouseenter', stopAutoPlay);
        slider.addEventListener('mouseleave', startAutoPlay);

        // Touch & Swipe gestures support
        let startX = 0;
        let dist = 0;

        slider.addEventListener('touchstart', (e) => {
            startX = e.changedTouches[0].clientX;
            stopAutoPlay();
        }, { passive: true });

        slider.addEventListener('touchend', (e) => {
            dist = e.changedTouches[0].clientX - startX;
            if (Math.abs(dist) > 50) {
                if (dist < 0) {
                    goToSlide(currentIndex + 1);
                } else {
                    goToSlide(currentIndex - 1);
                }
            } else {
                startAutoPlay();
            }
        }, { passive: true });

        // Initial setup
        updateSlider();
        startAutoPlay();
    });
}

/* ==========================================================================
   6. NOTIFICATION BANNER / TOAST SYSTEM
   ========================================================================== */
function initNotificationSystem() {
    let container = document.getElementById('shToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'shToastContainer';
        container.className = 'sh-toast-container';
        document.body.appendChild(container);
    }
}

/**
 * Show dynamic notification toast banner
 * @param {string} message - Text or HTML message to display
 * @param {string} type - 'success' | 'info' | 'warning' | 'danger'
 * @param {number} duration - Auto close timeout in ms (default 4000ms)
 */
function showNotification(message, type = 'info', duration = 4000) {
    let container = document.getElementById('shToastContainer');
    if (!container) {
        initNotificationSystem();
        container = document.getElementById('shToastContainer');
    }

    const iconMap = {
        success: 'fas fa-check-circle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-exclamation-triangle',
        danger: 'fas fa-exclamation-circle'
    };

    const toast = document.createElement('div');
    toast.className = `sh-toast sh-toast-${type}`;
    
    toast.innerHTML = `
        <div class="sh-toast-icon">
            <i class="${iconMap[type] || iconMap.info}"></i>
        </div>
        <div class="sh-toast-body">
            <p>${message}</p>
        </div>
        <button type="button" class="sh-toast-close" aria-label="Dismiss notification">
            <i class="fas fa-times"></i>
        </button>
        <div class="sh-toast-progress" style="animation-duration: ${duration}ms;"></div>
    `;

    container.appendChild(toast);

    // Trigger enter animation
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Close button event
    const closeBtn = toast.querySelector('.sh-toast-close');
    let timer = setTimeout(() => dismissToast(toast), duration);

    closeBtn.addEventListener('click', () => {
        clearTimeout(timer);
        dismissToast(toast);
    });
}

function dismissToast(toast) {
    toast.classList.remove('show');
    toast.classList.add('hide');
    toast.addEventListener('transitionend', () => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    });
}

/* ==========================================================================
   7. DEMO INTERACTIONS & GLOBAL HELPERS
   ========================================================================== */
function initDemoInteractions() {
    // Dynamic toast notification trigger buttons
    document.querySelectorAll('[data-trigger-toast]').forEach(btn => {
        btn.addEventListener('click', () => {
            const msg = btn.getAttribute('data-toast-msg') || 'Action completed successfully!';
            const type = btn.getAttribute('data-toast-type') || 'success';
            showNotification(msg, type, 3500);
        });
    });
}

/* ==========================================================================
   8. VISUAL FX (SCROLL REVEAL, PROGRESS BAR, SPOTLIGHT, RIPPLE)
   ========================================================================== */
function initVisualFx() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Scroll progress bar + navbar shrink
    const progress = document.createElement('div');
    progress.className = 'sh-scroll-progress';
    document.body.appendChild(progress);
    const navbar = document.querySelector('.navbar');

    const onScroll = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.transform = `scaleX(${max > 0 ? window.scrollY / max : 0})`;
        if (navbar) navbar.classList.toggle('sh-scrolled', window.scrollY > 20);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Scroll reveal: fade cards, headings and FAQ items in as they enter view
    if (!reduceMotion && 'IntersectionObserver' in window) {
        const targets = document.querySelectorAll(
            'section:not(.hero) h2, section:not(.hero) .card, .faq-item, .sh-slider, .table-responsive, .list-group-item'
        );
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('sh-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        targets.forEach(el => {
            // Stagger siblings that sit in the same row/group
            const siblings = el.parentElement ? Array.from(el.parentElement.parentElement?.children || []) : [];
            const index = Math.max(0, siblings.indexOf(el.parentElement));
            el.style.setProperty('--reveal-delay', `${Math.min(index, 5) * 0.08}s`);
            el.classList.add('sh-reveal');
            observer.observe(el);
        });
    }

    // Spotlight glow that follows the mouse across cards
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('pointermove', e => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${e.clientX - rect.left}px`);
            card.style.setProperty('--my', `${e.clientY - rect.top}px`);
        });
    });

    // Ripple burst on button clicks
    document.addEventListener('pointerdown', e => {
        const btn = e.target.closest('.btn');
        if (!btn || reduceMotion) return;
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const ripple = document.createElement('span');
        ripple.className = 'sh-ripple';
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${e.clientX - rect.left - size / 2}px`;
        ripple.style.top = `${e.clientY - rect.top - size / 2}px`;
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
    });
}

/* ==========================================================================
   9. SERVER FORM SUBMISSION (PHP)
   ========================================================================== */
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[ch]);
}

/**
 * POST a form to its PHP action and return the JSON reply:
 * { success: boolean, message: string, errors: { fieldName: message } }
 */
async function submitForm(form) {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' }
    });

    try {
        return await response.json();
    } catch {
        // A static server (e.g. Live Server) can't run PHP and returns HTML or the source
        throw new Error(`The server did not process the form (HTTP ${response.status}). ` +
            'Run the site with PHP: php -S localhost:8000 router.php');
    }
}

// Disable a submit button and show a spinner while a request is running
function setButtonLoading(button, loading, label = 'Submitting...') {
    if (!button) return;
    if (loading) {
        button.dataset.label = button.innerHTML;
        button.innerHTML = `<i class="fas fa-circle-notch fa-spin me-2"></i>${escapeHtml(label)}`;
    } else if (button.dataset.label) {
        button.innerHTML = button.dataset.label;
    }
    button.disabled = loading;
}

// Without JavaScript the PHP handler redirects back with ?status=&message=
function initFlashMessage() {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');
    const message = params.get('message');
    if (!status || !message) return;

    showNotification(escapeHtml(message), status === 'success' ? 'success' : 'danger', 6000);
    history.replaceState(null, '', window.location.pathname);
}

// Expose public API globally
window.StudentHub = {
    setTheme,
    openModal,
    closeModal,
    showNotification,
    escapeHtml,
    submitForm,
    setButtonLoading
};
