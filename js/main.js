// Franklin Air Arkansas - Main JS

(function () {
    'use strict';

    // Mobile nav toggle
    var toggle = document.getElementById('navToggle');
    var navLinks = document.getElementById('navLinks');

    if (toggle && navLinks) {
        toggle.addEventListener('click', function () {
            navLinks.classList.toggle('active');
            toggle.classList.toggle('open');
        });

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
        }, { passive: true });
    }

    // Scroll-triggered animations with stagger
    var animatedElements = document.querySelectorAll('[data-animate]');

    // Mark elements so CSS fallback animation is disabled (JS will handle it)
    animatedElements.forEach(function (el) {
        el.classList.add('js-ready');
    });

    if (animatedElements.length > 0 && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            // Collect newly visible elements to stagger them
            var newlyVisible = [];
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    newlyVisible.push(entry.target);
                    observer.unobserve(entry.target);
                }
            });

            // Stagger the reveal within each batch
            newlyVisible.forEach(function (el, i) {
                setTimeout(function () {
                    el.classList.add('visible');
                }, i * 100);
            });
        }, {
            threshold: 0.05,
            rootMargin: '0px 0px -20px 0px'
        });

        animatedElements.forEach(function (el) {
            observer.observe(el);
        });

        // Safety fallback: if someone lands mid-page (e.g. from a Facebook ad
        // with #contact anchor), reveal everything after 1.5 seconds
        setTimeout(function () {
            animatedElements.forEach(function (el) {
                if (!el.classList.contains('visible')) {
                    el.classList.add('visible');
                }
            });
        }, 1500);

    } else {
        // No IntersectionObserver support: show everything immediately
        animatedElements.forEach(function (el) {
            el.classList.add('visible');
        });
    }

    // Contact form AJAX submission
    var contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var btn = document.getElementById('contactSubmit');
            var status = document.getElementById('contactStatus');
            var formData = new FormData(contactForm);

            // Disable button and show sending state
            btn.disabled = true;
            btn.classList.add('sending');
            status.className = 'contact-status';
            status.textContent = '';

            fetch('contact.php', {
                method: 'POST',
                body: formData,
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    status.className = 'contact-status success';
                    status.textContent = result.data.message;
                    contactForm.reset();
                    // Track as Facebook Lead event
                    if (typeof fbq === 'function') {
                        fbq('track', 'Lead', { content_name: 'Contact Form', content_category: 'Form Submission' });
                    }
                } else {
                    status.className = 'contact-status error';
                    status.textContent = result.data.message || 'Something went wrong. Please call (479) 207-2454.';
                }
            })
            .catch(function () {
                status.className = 'contact-status error';
                status.textContent = 'Network error. Please call us at (479) 207-2454.';
            })
            .finally(function () {
                btn.disabled = false;
                btn.classList.remove('sending');
            });
        });
    }

    // Facebook Pixel - Track CTA clicks as Lead events
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
