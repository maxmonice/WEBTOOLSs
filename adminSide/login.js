// adminSide/login.js

(async () => {
    try {
        const res = await fetch('../Auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'check_session' })
        });
        const data = await res.json();
        // If already an admin, go to dashboard
        if (data.success && data.is_admin) {
            window.location.href = 'admin-dashboard.php';
        }
    } catch (e) {}
})();

function togglePw() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function showError(msg) {
    const box  = document.getElementById('errorBox');
    const text = document.getElementById('errorText');
    if (text) text.textContent = msg;
    if (box) box.style.display = 'flex';
}

function clearError() {
    const box = document.getElementById('errorBox');
    if (box) box.style.display = 'none';
}

async function doLogin() {
    const email    = document.getElementById('emailInput').value.trim();
    const password = document.getElementById('passwordInput').value;
    const btn      = document.getElementById('loginBtn');

    clearError();

    if (!email || !password) {
        showError('Please enter admin credentials.');
        return;
    }

    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Authorizing...';

    try {
        const res = await fetch('../Auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'login', email, password })
        });
        const data = await res.json();

        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Access Granted';
            setTimeout(() => {
                // The backend returns the redirect URL relative to the root
                // e.g. "adminSide/admin-dashboard.php"
                // Since we are in adminSide/, we just need the filename
                const parts = data.redirect.split('/');
                const page = parts[parts.length - 1];
                window.location.href = page;
            }, 800);
        } else {
            showError(data.message || 'Access Denied.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (e) {
        showError('Security link interrupted. Please retry.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
});
