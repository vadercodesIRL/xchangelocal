// page load animation
function pageReveal() {
    // only show once per session
    if (sessionStorage.getItem('revealed')) return;
    sessionStorage.setItem('revealed', '1');

    var overlay = document.createElement('div');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:#1B9FAA;display:flex;align-items:center;justify-content:center;transition:opacity 400ms';
    overlay.innerHTML = '<span style="color:#fff;font-size:1.5rem;font-weight:900">XchangeLocal</span>';
    document.body.appendChild(overlay);

    // fade out after a short delay
    setTimeout(function () {
        overlay.style.opacity = '0';
        setTimeout(function () { overlay.remove(); }, 420);
    }, 350);
}

// mobile nav drawer
function mobileNav() {
    var btn    = document.getElementById('nav-toggle');
    var drawer = document.getElementById('nav-drawer');
    if (!btn || !drawer) return;

    function openNav() {
        drawer.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeNav() {
        drawer.setAttribute('aria-hidden', 'true');
        btn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    // open/close on hamburger click
    btn.addEventListener('click', function () {
        drawer.getAttribute('aria-hidden') === 'false' ? closeNav() : openNav();
    });

    // close if user clicks outside the drawer
    document.addEventListener('click', function (e) {
        if (!drawer.contains(e.target) && !btn.contains(e.target)) closeNav();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNav();
    });
}

// scroll reveal - fades elements in when entering viewport, out when leaving
function scrollReveal() {
    if (!('IntersectionObserver' in window)) return;

    // card groups get a stagger delay between siblings in the same parent
    var cardSelectors   = ['.listing-card', '.review-card', '.account-card', '.stat-card'];
    // headings, auth cards and sections fade slightly slower
    var singleSelectors = ['.auth-card', 'h1', 'h2', '.section'];

    // skip elements that already use CSS keyframe animations
    function shouldAnimate(el) {
        return !el.classList.contains('cascade-up')
            && !el.classList.contains('cascade-down')
            && !el.classList.contains('scroll-reveal');
    }

    // permanent observer - no unobserve, so fade-out on scroll also works
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            } else {
                entry.target.classList.remove('is-visible');
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    function register(el, delayMs, slowDuration) {
        el.classList.add('scroll-reveal');

        if (delayMs > 0) {
            el.style.transitionDelay = delayMs + 'ms';
        }
        if (slowDuration) {
            el.style.transitionDuration = '800ms, 800ms';
        }

        // observe permanently - the observer fires immediately for in-viewport
        // elements (isIntersecting: true) so no manual check needed
        observer.observe(el);
    }

    // cards - stagger siblings within the same parent
    cardSelectors.forEach(function (sel) {
        var parents = [];
        var groups  = [];

        document.querySelectorAll(sel).forEach(function (el) {
            if (!shouldAnimate(el)) return;
            var p   = el.parentElement;
            var idx = parents.indexOf(p);
            if (idx === -1) {
                parents.push(p);
                groups.push([]);
                idx = groups.length - 1;
            }
            groups[idx].push(el);
        });

        groups.forEach(function (group) {
            group.forEach(function (el, i) {
                register(el, (i % 4) * 80, false);
            });
        });
    });

    // single selectors - no stagger, slightly slower fade
    singleSelectors.forEach(function (sel) {
        document.querySelectorAll(sel).forEach(function (el) {
            if (!shouldAnimate(el)) return;
            register(el, 0, true);
        });
    });
}

// start everything when the dom is ready
document.addEventListener('DOMContentLoaded', function () {
    pageReveal();
    mobileNav();
    scrollReveal();
});
