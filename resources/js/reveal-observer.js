/**
 * Scroll-reveal observer.
 *
 * Progressive enhancement: the markup ships fully visible. Only once this
 * module runs does it hide `.reveal` elements (class `reveal-pending`) and
 * unhide them again as they enter the viewport. Hiding lives here instead of
 * in the stylesheet on purpose: a slow network, an old device, or a JS error
 * must never leave the page blank, because without JS nothing gets hidden.
 *
 * When the module executes before first paint (fast load) every element is
 * armed and plays its entrance animation. After first paint (slow load, the
 * visitor already sees content) only elements below the viewport are armed,
 * so nothing visible ever blinks out.
 *
 * We use a vanilla IntersectionObserver instead of Alpine's `x-intersect`
 * because reveal elements live outside any `x-data` scope and would
 * otherwise never be initialized. Users who prefer reduced motion get no
 * animation at all.
 */

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initRevealObserver() {
    if (typeof window === 'undefined') return;

    // Nothing is hidden yet, so "no animation" simply means: do nothing.
    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
        return;
    }

    const painted = () =>
        typeof performance === 'undefined' ||
        typeof performance.getEntriesByType !== 'function' ||
        performance.getEntriesByType('paint').length > 0;

    const observer = new IntersectionObserver(
        (entries, obs) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.remove('reveal-pending');
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

    const viewportHeight = () =>
        window.innerHeight || document.documentElement.clientHeight;

    const arm = (el) => {
        if (el.classList.contains('is-visible') || el.classList.contains('reveal-pending')) {
            return;
        }
        // Once something has been painted, only elements the visitor cannot
        // currently see may be hidden for their entrance animation.
        if (!painted() || el.getBoundingClientRect().top > viewportHeight()) {
            el.classList.add('reveal-pending');
            observer.observe(el);
        } else {
            el.classList.add('is-visible');
        }
    };

    const armAll = () => {
        document.querySelectorAll('.reveal:not(.is-visible)').forEach(arm);
    };

    armAll();

    if (typeof MutationObserver !== 'undefined') {
        const mo = new MutationObserver(() => armAll());
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
