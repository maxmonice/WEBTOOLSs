// Mobile menu toggle
const menuToggle = document.getElementById('mobile-menu');
const navMenu = document.getElementById('navMenu');
if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
}
