// runs before <body> so the theme is applied before the page renders - prevents flash
// apply saved theme straight away
(function () {
    var saved = localStorage.getItem('xl-theme-v2') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
}());

// wire up the toggle button
function themeToggle() {
    var btns = document.querySelectorAll('.theme-toggle');
    if (!btns.length) return;

    function applyTheme(theme) {
        // update the data attribute and save to localstorage
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('xl-theme-v2', theme);

        // update button label for screen readers
        var label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
        btns.forEach(function (btn) { btn.setAttribute('aria-label', label); });
    }

    // handle click
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme');
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    });

    // also respect OS dark mode preference if user hasn't chosen manually
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        if (!localStorage.getItem('xl-theme-v2')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });
}

document.addEventListener('DOMContentLoaded', themeToggle);
