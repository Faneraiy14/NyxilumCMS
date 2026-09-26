// Бургер-меню (26.09.2026, перенесено з my-hub) - чиста DOM-логіка,
// жодних залежностей. .site-nav/.nav-overlay/.menu-toggle - розмітка
// з header.php.
const menuToggle = document.querySelector('.menu-toggle');
const siteNav = document.querySelector('.site-nav');
const navOverlay = document.querySelector('.nav-overlay');

if (menuToggle && siteNav && navOverlay) {
    function closeNav() {
        siteNav.classList.remove('is-open');
        navOverlay.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
    }

    menuToggle.addEventListener('click', function () {
        const willOpen = !siteNav.classList.contains('is-open');
        siteNav.classList.toggle('is-open');
        navOverlay.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    navOverlay.addEventListener('click', closeNav);

    // Внутрішні посилання закривають меню (той самий SPA-подібний UX,
    // що вже в my-hub) - зовнішні (target="_blank") НЕ закривають,
    // бо відкриваються в новій вкладці, поточна лишається як була.
    document.querySelectorAll('.site-nav a:not([target="_blank"])').forEach(function (link) {
        link.addEventListener('click', closeNav);
    });
}
