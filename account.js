// =====================================================
//  account.js — Luke's Seafood Trading
// =====================================================

const AUTH_URL = 'auth.php';
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
        client_id:              GOOGLE_CLIENT_ID,
        callback:               handleGoogleResponse,
        auto_select:            false,
        cancel_on_tap_outside:  true,
    });
}

async function handleGoogleResponse(response) {
    if (!response || !response.credential) {
        showError('Google sign-in was cancelled or failed. Please try again.');
        return;
    }
    showLoading(true);
    try {
        const result = await callAuth({ action: 'google_auth', id_token: response.credential });
        if (result.success) {
            onLoginSuccess(result);
        } else {
            showError(result.message || 'Google sign-in failed.');
        }
    } catch (e) {
        console.error(e);
        showError('Google sign-in failed. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render a real hidden Google button and click it — more reliable than prompt()
function signInWithGoogle() {
    if (typeof google === 'undefined' || !google.accounts) {
        showError('Google sign-in is not available. Please try again in a moment.');
        return;
    }

    // Remove any old container
    const old = document.getElementById('_g_btn_container');
    if (old) old.remove();

    // Create a hidden container, render the button, then click it
    const container = document.createElement('div');
    container.id = '_g_btn_container';
    container.style.cssText = 'position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;overflow:hidden;';
    document.body.appendChild(container);

    google.accounts.id.renderButton(container, {
        type:  'standard',
        theme: 'outline',
        size:  'large',
    });

    // Give the button a tick to render, then click it
    setTimeout(() => {
        const btn = container.querySelector('div[role="button"]') || container.querySelector('button');
        if (btn) {
            btn.click();
        } else {
            // Final fallback — try One Tap prompt
            google.accounts.id.prompt((notification) => {
                if (notification.isNotDisplayed()) {
                    showError('Google sign-in was blocked by your browser. Try disabling popup blockers.');
                } else if (notification.isSkippedMoment()) {
                    showError('Google sign-in was dismissed. Please try again.');
                }
            });
        }
    }, 300);
}

// =====================================================
//  FACEBOOK SDK
// =====================================================
function loadFacebookSDK() {
    window.fbAsyncInit = function () {
        FB.init({
            appId:   '1282425887392045',
            cookie:  true,
            xfbml:   true,
            version: 'v19.0',
        });
    };
    const script = document.createElement('script');
    script.src   = 'https://connect.facebook.net/en_US/sdk.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

// =====================================================
//  FACEBOOK SIGN IN — fixed (no async in FB.login callback)
// =====================================================
function signInWithFacebook() {
    if (typeof FB === 'undefined') {
        showError('Facebook sign-in is not available. Please try again in a moment.');
        return;
    }
    FB.login(function(response) {
        if (!response.authResponse) {
            showError('Facebook login was cancelled.');
            return;
        }
        showLoading(true);
        callAuth({
            action:       'facebook_auth',
            access_token: response.authResponse.accessToken,
        })
        .then(function(result) {
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message || 'Facebook sign-in failed.');
            }
        })
        .catch(function() {
            showError('Facebook sign-in failed. Please try again.');
        })
        .finally(function() {
            showLoading(false);
        });
    }, { scope: 'public_profile,email' });
}

// =====================================================
//  CORE API CALLER
// =====================================================
async function callAuth(payload) {
    const res = await fetch(AUTH_URL, {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'include',
        body:        JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
}

// =====================================================
//  ON LOGIN SUCCESS
// =====================================================
function onLoginSuccess(result) {
    sessionStorage.setItem('user_name',  result.name  || '');
    sessionStorage.setItem('user_email', result.email || '');
    window.location.href = 'account-dashboard.php';
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
    const activeModal = document.querySelector('.modal:not(.hidden)');
    if (!activeModal) return;
    const err = document.createElement('div');
    err.className = 'auth-error';
    err.id        = 'authError';
    err.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${escapeHtml(msg)}`;
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
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// =====================================================
//  DOM READY
// =====================================================
document.addEventListener('DOMContentLoaded', async () => {

    loadGoogleSDK();
    loadFacebookSDK();

    // Mobile nav
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
        btn.addEventListener('click', () => signInWithFacebook());
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

    // --- CHECK SESSION ON PAGE LOAD ---
    try {
        const session = await callAuth({ action: 'check_session' });
        if (session.success) {
            sessionStorage.setItem('user_name',  session.name  || '');
            sessionStorage.setItem('user_email', session.email || '');
            window.location.href = 'account-dashboard.php';
        } else {
            sessionStorage.removeItem('user_name');
            sessionStorage.removeItem('user_email');
        }
    } catch (e) {
        sessionStorage.removeItem('user_name');
        sessionStorage.removeItem('user_email');
        console.warn('Session check failed:', e.message);
    }
});