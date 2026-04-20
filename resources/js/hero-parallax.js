/**
 * Subtle Apple-style parallax for hero media.
 *
 * Usage in Blade:
 *   <div data-parallax data-parallax-speed="0.2"> ... </div>
 *
 * The element's children translate vertically as the page scrolls,
 * creating the impression that the hero "lags" behind the rest of the page.
 *
 * Disabled entirely when the user prefers reduced motion.
 */

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initParallax() {
    if (prefersReducedMotion()) return;

    const targets = Array.from(document.querySelectorAll('[data-parallax]'));
    if (targets.length === 0) return;

    let ticking = false;

    const update = () => {
        const viewportH = window.innerHeight;
        for (const el of targets) {
            const rect = el.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > viewportH) continue;

            const speed = parseFloat(el.dataset.parallaxSpeed || '0.18');
            const progress = rect.top / viewportH;
            const offset = progress * -120 * speed;
            el.style.transform = `translate3d(0, ${offset}px, 0)`;
        }
        ticking = false;
    };

    const onScroll = () => {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
}

if (typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initParallax);
    } else {
        initParallax();
    }
}
