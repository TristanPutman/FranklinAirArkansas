// Franklin Air Arkansas - Main JS

(function () {
    'use strict';

    // Mobile nav toggle
    var toggle = document.getElementById('navToggle');
    var navLinks = document.getElementById('navLinks');

    if (toggle && navLinks) {
        toggle.addEventListener('click', function () {
            navLinks.classList.toggle('active');
            // Animate hamburger
            toggle.classList.toggle('open');
        });

        // Close nav when a link is clicked
        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                navLinks.classList.remove('active');
                toggle.classList.remove('open');
            });
        });
    }

    // Navbar scroll effect
    var navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 40) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // Scroll-triggered animations
    var animatedElements = document.querySelectorAll('[data-animate]');
    if (animatedElements.length > 0) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px'
        });

        animatedElements.forEach(function (el) {
            observer.observe(el);
        });
    }

    // Facebook Pixel - Track CTA clicks as Lead events
    // (Only fires if fbq is loaded)
    document.querySelectorAll('a[href^="tel:"], a[href="#contact"]').forEach(function (link) {
        link.addEventListener('click', function () {
            if (typeof fbq === 'function') {
                fbq('track', 'Lead', {
                    content_name: link.textContent.trim(),
                    content_category: link.href.indexOf('tel:') !== -1 ? 'Phone Call' : 'Contact Form'
                });
            }
        });
    });

})();
