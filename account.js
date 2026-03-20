// =====================================================
//  account.js — Luke's Seafood Trading
//  Talks to auth.php for all auth actions
// =====================================================

const AUTH_URL = 'auth.php'; // path to auth.php on your server

// =====================================================
//  GOOGLE CLIENT ID
// =====================================================
const GOOGLE_CLIENT_ID = '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';

// =====================================================
//  GOOGLE SDK
// =====================================================
function loadGoogleSDK() {
    const script = document.createElement('script');
    script.src   = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.onload = initGoogle;
    document.head.appendChild(script);
}

function initGoogle() {
    google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback:  handleGoogleResponse,
    });
}

// Called by Google SDK after user picks account
async function handleGoogleResponse(response) {
    showLoading(true);
    try {
        const result = await callAuth({ action: 'google_auth', id_token: response.credential });
        if (result.success) {
            onLoginSuccess(result);
        } else {
            showError(result.message);
        }
    } catch (e) {
        showError('Google sign-in failed. Please try again.');
    } finally {
        showLoading(false);
    }
}

function signInWithGoogle() {
    google.accounts.id.prompt((notification) => {
        if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
            // Fallback: render a hidden button and click it
            const wrapper = document.createElement('div');
            wrapper.style.display = 'none';
            document.body.appendChild(wrapper);
            google.accounts.id.renderButton(wrapper, { theme: 'outline', size: 'large' });
            google.accounts.id.prompt();
        }
    });
}

// =====================================================
//  FACEBOOK SDK
// =====================================================
function loadFacebookSDK() {
    window.fbAsyncInit = function () {
        FB.init({
            appId:   'YOUR_FACEBOOK_APP_ID_HERE',  // <-- paste your FB App ID
            cookie:  true,
            xfbml:   true,
            version: 'v19.0',
        });
    };

    const script    = document.createElement('script');
    script.src      = 'https://connect.facebook.net/en_US/sdk.js';
    script.async    = true;
    script.defer    = true;
    document.head.appendChild(script);
}

async function signInWithFacebook() {
    FB.login(async function (response) {
        if (!response.authResponse) {
            showError('Facebook login was cancelled.');
            return;
        }
        showLoading(true);
        try {
            const result = await callAuth({
                action:       'facebook_auth',
                access_token: response.authResponse.accessToken,
            });
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message);
            }
        } catch (e) {
            showError('Facebook sign-in failed. Please try again.');
        } finally {
            showLoading(false);
        }
    }, { scope: 'public_profile,email' });
}

// =====================================================
//  CORE API CALLER
// =====================================================
async function callAuth(payload) {
    const res = await fetch(AUTH_URL, {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'include',   // send/receive cookies for sessions
        body:        JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
}

// =====================================================
//  ON LOGIN / SIGNUP SUCCESS
// =====================================================
function onLoginSuccess(result) {
    // Store user info in sessionStorage for display
    sessionStorage.setItem('user_name',  result.name  || '');
    sessionStorage.setItem('user_email', result.email || '');

    // Show welcome state or redirect
    showWelcome(result.name);
}

function showWelcome(name) {
    const overlay = document.getElementById('modalOverlay');
    overlay.innerHTML = `
        <div class="modal" style="text-align:center;">
            <div class="modal-fish-icon" style="margin:0 auto 20px;">
                <i class="fas fa-fish"></i>
            </div>
            <h2 style="font-family:'Aclonica',sans-serif;font-size:1.3rem;margin-bottom:8px;">
                Welcome, ${escapeHtml(name)}!
            </h2>
            <p style="color:rgba(255,255,255,0.6);font-size:0.88rem;margin-bottom:28px;">
                You're now signed in to Luke's Seafood Trading.
            </p>
            <a href="index.html" class="btn-primary" style="text-decoration:none;display:inline-flex;">
                <span>Go to Homepage</span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <br><br>
            <button class="btn-google" onclick="handleLogout()" style="width:100%;">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </button>
        </div>
    `;
}

// =====================================================
//  LOGOUT
// =====================================================
async function handleLogout() {
    await callAuth({ action: 'logout' });
    sessionStorage.clear();
    window.location.reload();
}

// =====================================================
//  MODAL SWITCHER
// =====================================================
function switchModal(target) {
    const login  = document.getElementById('loginModal');
    const signup = document.getElementById('signupModal');
    clearError();
    if (target === 'signup') {
        login.classList.add('hidden');
        signup.classList.remove('hidden');
        triggerAnimation(signup);
    } else {
        signup.classList.add('hidden');
        login.classList.remove('hidden');
        triggerAnimation(login);
    }
}

function triggerAnimation(el) {
    el.style.animation = 'none';
    el.offsetHeight;
    el.style.animation = '';
}

// =====================================================
//  PASSWORD TOGGLE
// =====================================================
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// =====================================================
//  ERROR / LOADING UI
// =====================================================
function showError(msg) {
    clearError();
    // Find active modal
    const activeModal = document.querySelector('.modal:not(.hidden)');
    if (!activeModal) return;
    const err = document.createElement('div');
    err.className = 'auth-error';
    err.id        = 'authError';
    err.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${escapeHtml(msg)}`;
    // Insert before first form-group
    const form = activeModal.querySelector('.modal-form');
    form.insertBefore(err, form.firstChild);
}

function clearError() {
    document.getElementById('authError')?.remove();
}

function showLoading(on) {
    const btns = document.querySelectorAll('.btn-primary');
    btns.forEach(btn => { btn.disabled = on; btn.style.opacity = on ? '0.7' : '1'; });
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// =====================================================
//  DOM READY
// =====================================================
document.addEventListener('DOMContentLoaded', async () => {

    // Load OAuth SDKs
    loadGoogleSDK();
    loadFacebookSDK();

    // Mobile nav toggle
    const menuToggle = document.getElementById('mobile-menu');
    const navMenu    = document.getElementById('navMenu');
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
    }

    // Wire Google buttons
    document.querySelectorAll('.btn-google').forEach(btn => {
        btn.addEventListener('click', signInWithGoogle);
    });

    // Wire Facebook buttons
    document.querySelectorAll('.btn-facebook').forEach(btn => {
        btn.addEventListener('click', signInWithFacebook);
    });

    // --- EMAIL LOGIN ---
    document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        const email    = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;
        const remember = document.getElementById('rememberMe')?.checked ?? false;

        if (!email || !password) { showError('Please fill in all fields.'); return; }

        showLoading(true);
        try {
            const result = await callAuth({ action: 'login', email, password, remember });
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message);
            }
        } catch (e) {
            showError('Login failed. Please check your connection and try again.');
        } finally {
            showLoading(false);
        }
    });

    // --- EMAIL SIGNUP ---
    document.getElementById('signupForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        const name     = document.getElementById('signupName').value.trim();
        const email    = document.getElementById('signupEmail').value.trim();
        const password = document.getElementById('signupPassword').value;

        if (!name || !email || !password) { showError('Please fill in all fields.'); return; }
        if (password.length < 8) { showError('Password must be at least 8 characters.'); return; }

        showLoading(true);
        try {
            const result = await callAuth({ action: 'signup', name, email, password });
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message);
            }
        } catch (e) {
            showError('Signup failed. Please check your connection and try again.');
        } finally {
            showLoading(false);
        }
    });

    // --- CHECK EXISTING SESSION on page load ---
    try {
        const session = await callAuth({ action: 'check_session' });
        if (session.success) {
            showWelcome(session.name);
        }
    } catch (e) {
        // No session — show login form (default state)
    }
});