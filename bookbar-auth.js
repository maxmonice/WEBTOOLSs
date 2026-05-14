/**
 * bookbar-auth.js
 * 
 * Handles the authentication check and UI updates before allowing
 * users to submit a booking form.
 */

// Track login state
window.__isLoggedIn = false;

// Check session from PHP on page load
(async function checkAuth() {
    try {
        const res = await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'check_session' })
        });
        const data = await res.json();
        window.__isLoggedIn = !!data.success && data.session_side === 'customer';

        if (data.success) {
            // Sync latest session data locally
            sessionStorage.setItem('user_name',  data.name  || '');
            sessionStorage.setItem('user_email', data.email || '');
        }
    } catch (e) {
        window.__isLoggedIn = false;
    }

    renderAuthUI();
})();

function renderAuthUI() {
    const bar    = document.getElementById('authStatusBar');
    const btn    = document.getElementById('submitBtn');

    if (window.__isLoggedIn) {
        const name = sessionStorage.getItem('user_name') || 'User';
        bar.className  = 'auth-status-bar signed-in';
        bar.innerHTML  = `<i class="fa-solid fa-circle-check"></i> Signed in as <strong style="margin-left:4px;color:#fff;">${name}</strong>`;
        bar.style.display = 'flex';
        btn.classList.remove('locked');
    } else {
        bar.className  = 'auth-status-bar signed-out';
        bar.innerHTML  = `<i class="fa-solid fa-triangle-exclamation"></i> You're not signed in — you must <a href="account.php">log in</a> to submit a booking.`;
        bar.style.display = 'flex';
        btn.classList.add('locked');
    }
}

// Capture-phase listener — fires BEFORE bookbar.js submit handler
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('bookingForm');

    form.addEventListener('submit', function (e) {
        if (!window.__isLoggedIn) {
            // Stop everything — bookbar.js never sees this event
            e.preventDefault();
            e.stopImmediatePropagation();
            openAuthModal();
        }
    }, true); // ← capture: true is the key
});

// Modal controls
function openAuthModal() {
    document.getElementById('authModal').classList.add('open');
}
function closeAuthModal() {
    document.getElementById('authModal').classList.remove('open');
}
function goToSignIn() {
    // Store intended destination so account page can redirect back
    sessionStorage.setItem('redirect_after_login', 'bookbar.php');
    window.location.href = 'account.php';
}

// Close modal on overlay click
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeAuthModal();
        });
    }

    // Mobile nav toggle
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenu) {
        mobileMenu.addEventListener('click', function () {
            document.getElementById('navMenu').classList.toggle('active');
        });
    }
});
