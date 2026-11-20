import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Page motion, kept deliberately small:
 *  - [data-enter]   children fade up once on page load (hero, page headers)
 *  - [data-reveal]  fades up when scrolled into view (marketing sections)
 *  - [data-stagger] its direct children cascade in (dashboard cards, lists)
 *  - [data-float]   a slow idle bob (hero floating cards)
 *
 * Elements are only hidden by JS right before animating, so the page is fully
 * readable without JavaScript or with reduced motion.
 */
export function initMotion() {
    if (prefersReducedMotion()) {
        return;
    }

    document.querySelectorAll('[data-enter]').forEach((container) => {
        gsap.from(container.children, {
            y: 8,
            autoAlpha: 0,
            duration: 0.5,
            ease: 'power2.out',
            stagger: 0.06,
            clearProps: 'all',
        });
    });

    document.querySelectorAll('[data-stagger]').forEach((container) => {
        gsap.from(Array.from(container.children).slice(0, 10), {
            y: 10,
            autoAlpha: 0,
            duration: 0.45,
            ease: 'power2.out',
            stagger: 0.04,
            clearProps: 'all',
        });
    });

    ScrollTrigger.batch('[data-reveal]', {
        start: 'top 88%',
        once: true,
        onEnter: (elements) =>
            gsap.from(elements, {
                y: 24,
                autoAlpha: 0,
                duration: 0.7,
                ease: 'power3.out',
                stagger: 0.08,
                clearProps: 'all',
            }),
    });

    document.querySelectorAll('[data-float]').forEach((element, index) => {
        gsap.to(element, {
            y: index % 2 === 0 ? -8 : 8,
            duration: 3 + index,
            ease: 'sine.inOut',
            yoyo: true,
            repeat: -1,
        });
    });
}
