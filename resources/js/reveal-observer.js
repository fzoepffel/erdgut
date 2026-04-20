/**
 * Scroll-reveal observer.
 *
 * Toggles `.is-visible` on every `.reveal` element once it enters the
 * viewport. We use a vanilla IntersectionObserver instead of Alpine's
 * `x-intersect` because reveal elements live outside any `x-data` scope
 * and would otherwise never be initialized.
 *
 * When IntersectionObserver is unavailable, or the user prefers reduced
 * motion, all reveals are shown immediately.
 */

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function showAll() {
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
}

function initRevealObserver() {
    if (typeof window === 'undefined') return;

    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
        showAll();
        return;
    }

    const observer = new IntersectionObserver(
        (entries, obs) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            }
        },
        {
            rootMargin: '0px 0px -10% 0px',
            threshold: 0.05,
        },
    );

    const observe = () => {
        document.querySelectorAll('.reveal:not(.is-visible)').forEach((el) => observer.observe(el));
    };

    observe();

    if (typeof MutationObserver !== 'undefined') {
        const mo = new MutationObserver(() => observe());
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRevealObserver);
    } else {
        initRevealObserver();
    }
}
