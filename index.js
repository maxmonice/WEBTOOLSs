/**
 * index.js
 * 
 * Handles interaction and animations for the index/home page.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenu) {
        mobileMenu.addEventListener('click', function () {
            document.getElementById('navMenu').classList.toggle('active');
        });
    }

    // Scroll reveal observer for elements with .sr class
    const srObs = new IntersectionObserver((entries) => {
        entries.forEach((e, i) => {
            if (e.isIntersecting) {
                // Staggered animation effect
                setTimeout(() => e.target.classList.add('sr-visible'), i * 80);
                srObs.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.sr').forEach(el => srObs.observe(el));
});
