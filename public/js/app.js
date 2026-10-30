
/**
 * Mokhatlo 2.0
 * Global Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    const THEME_KEY = 'mokhatlo.theme';
    const root = document.documentElement;

    const themeButtons = document.querySelectorAll(
        '[data-theme-toggle]'
    );

    const mediaQuery = window.matchMedia(
        '(prefers-color-scheme: dark)'
    );

    function getSavedTheme() {
        try {
            return localStorage.getItem(THEME_KEY);
        } catch (error) {
            return null;
        }
    }

    function systemTheme() {
        return mediaQuery.matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        const isDark = theme === 'dark';

        root.setAttribute(
            'data-bs-theme',
            theme
        );

        themeButtons.forEach(function (button) {
            const icon = button.querySelector(
                '[data-theme-icon]'
            );

            const label = button.querySelector(
                '[data-theme-label]'
            );

            button.setAttribute(
                'aria-pressed',
                String(isDark)
            );

            button.setAttribute(
                'title',
                isDark
                    ? 'Switch to light mode'
                    : 'Switch to dark mode'
            );

            if (icon) {
                icon.className = isDark
                    ? 'bi bi-sun-fill'
                    : 'bi bi-moon-stars-fill';
            }

            if (label) {
                label.textContent = isDark
                    ? 'Light mode'
                    : 'Dark mode';
            }
        });
    }

    // Apply theme when the page loads.
    applyTheme(
        root.getAttribute('data-bs-theme') || systemTheme()
    );

    // Manual theme switching.
    themeButtons.forEach(function (button) {

        button.addEventListener('click', function () {

            const currentTheme = root.getAttribute(
                'data-bs-theme'
            );

            const newTheme = currentTheme === 'dark'
                ? 'light'
                : 'dark';

            try {
                localStorage.setItem(
                    THEME_KEY,
                    newTheme
                );
            } catch (error) {
                // The theme still applies to this page.
            }

            applyTheme(newTheme);
        });
    });

    // Follow device changes until a manual preference is saved.
    mediaQuery.addEventListener('change', function () {
        if (!['light', 'dark'].includes(getSavedTheme())) {
            applyTheme(systemTheme());
        }
    });

    // Synchronise theme changes across browser tabs.
    window.addEventListener('storage', function (event) {
        if (event.key === THEME_KEY) {
            const saved = getSavedTheme();

            applyTheme(
                ['light', 'dark'].includes(saved)
                    ? saved
                    : systemTheme()
            );
        }
    });

    // Automatically dismiss alerts.
    document.querySelectorAll(
        '[data-auto-dismiss]'
    ).forEach(function (element) {

        setTimeout(function () {
            if (element.isConnected && window.bootstrap) {
                bootstrap.Alert.getOrCreateInstance(
                    element
                ).close();
            }
        }, 6000);
    });

    // Initialise tooltips and popovers.
    if (window.bootstrap) {
        document.querySelectorAll(
            '[data-bs-toggle="tooltip"]'
        ).forEach(function (element) {
            new bootstrap.Tooltip(element);
        });

        document.querySelectorAll(
            '[data-bs-toggle="popover"]'
        ).forEach(function (element) {
            new bootstrap.Popover(element);
        });
    }

    
document.querySelectorAll('form').forEach(function (form) {
    const submitButton = form.querySelector('[data-submit-once]');

    if (!submitButton) {
        return;
    }

    form.addEventListener('submit', function (event) {
        if (form.dataset.submitted === 'true') {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) {
            return;
        }

        form.dataset.submitted = 'true';

        submitButton.disabled = true;
        submitButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Saving...';
    });
});


});
