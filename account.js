// =====================================================
//  account.js — Luke's Seafood Trading
//  Includes: email login/signup with 2FA OTP flow,
//  Google OAuth, Facebook OAuth
// =====================================================

const AUTH_URL       = 'auth.php';
const GOOGLE_CLIENT_ID = window.APP_CONFIG?.googleClientId || '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';

// =====================================================
//  GOOGLE SDK
// =====================================================
function loadGoogleSDK() {
    const script   = document.createElement('script');
    script.src     = 'https://accounts.google.com/gsi/client';
    script.async   = true;
    script.defer   = true;
    script.onload  = initGoogle;
    document.head.appendChild(script);
}

function initGoogle() {
    google.accounts.id.initialize({
        client_id:             GOOGLE_CLIENT_ID,
        callback:              handleGoogleResponse,
        auto_select:           false,
        cancel_on_tap_outside: true,
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
        showError('Google sign-in failed. Please try again.');
    } finally {
        showLoading(false);
    }
}

function signInWithGoogle() {
    if (typeof google === 'undefined' || !google.accounts) {
        showError('Google sign-in is not available. Please try again in a moment.');
        return;
    }
    const old = document.getElementById('_g_btn_container');
    if (old) old.remove();

    const container = document.createElement('div');
    container.id = '_g_btn_container';
    container.style.cssText =
        'position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;overflow:hidden;';
    document.body.appendChild(container);

    google.accounts.id.renderButton(container, { type: 'standard', theme: 'outline', size: 'large' });

    setTimeout(() => {
        const btn = container.querySelector('div[role="button"]') || container.querySelector('button');
        if (btn) {
            btn.click();
        } else {
            google.accounts.id.prompt((notification) => {
                if (notification.isNotDisplayed()) {
                    showError('Google sign-in was blocked by your browser.');
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
        FB.init({ appId: '1282425887392045', cookie: true, xfbml: true, version: 'v19.0' });
    };
    const script = document.createElement('script');
    script.src   = 'https://connect.facebook.net/en_US/sdk.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

function signInWithFacebook() {
    if (typeof FB === 'undefined') {
        showError('Facebook sign-in is not available. Please try again in a moment.');
        return;
    }
    FB.login(function (response) {
        if (!response.authResponse) {
            showError('Facebook login was cancelled.');
            return;
        }
        showLoading(true);
        callAuth({ action: 'facebook_auth', access_token: response.authResponse.accessToken })
            .then(function (result) {
                if (result.success) {
                    onLoginSuccess(result);
                } else {
                    showError(result.message || 'Facebook sign-in failed.');
                }
            })
            .catch(function () {
                showError('Facebook sign-in failed. Please try again.');
            })
            .finally(function () {
                showLoading(false);
            });
    }, { scope: 'public_profile,email' });
}

// =====================================================
//  CORE API CALLER
// =====================================================
async function callAuth(payload) {
    const runId = window.__debugRunId || `run_${Date.now()}`;
    window.__debugRunId = runId;
    const payloadWithRunId = { ...payload, runId };
    const res = await fetch(AUTH_URL, {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'include',
        body:        JSON.stringify(payloadWithRunId),
    });
    if (!res.ok) {
        let bodyText = '';
        try { bodyText = await res.text(); } catch (_) {}
        throw new Error('Server error: ' + res.status + (bodyText ? ' ' + bodyText.slice(0, 120) : ''));
    }

    const raw = await res.text();
    try {
        return JSON.parse(raw);
    } catch (e) {
        const firstBrace = raw.indexOf('{');
        const lastBrace = raw.lastIndexOf('}');
        if (firstBrace !== -1 && lastBrace > firstBrace) {
            const jsonSlice = raw.slice(firstBrace, lastBrace + 1);
            try {
                return JSON.parse(jsonSlice);
            } catch (_) {}
        }
        throw e;
    }
}

// =====================================================
//  ON LOGIN SUCCESS
//  Handles both regular users and the admin account.
//  If auth.php returns a 'redirect' URL, we use it —
//  this is how admin@gmail.com lands on admin-dashboard.
// =====================================================
function onLoginSuccess(result) {
    // Email login/signup routes to OTP first
    if (result.requires_2fa) {
        showOtpOverlay(result.email_hint || '');
        return;
    }
    sessionStorage.setItem('user_name',  result.name  || '');
    sessionStorage.setItem('user_email', result.email || '');

    // result.redirect is set by the server for special accounts (admin)
    const redirect = result.redirect
        || sessionStorage.getItem('redirect_after_login')
        || 'account-dashboard.php';
    sessionStorage.removeItem('redirect_after_login');
    window.location.href = redirect;
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
    el.offsetHeight; // force reflow
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
    const err       = document.createElement('div');
    err.className   = 'auth-error';
    err.id          = 'authError';
    err.innerHTML   = `<i class="fas fa-exclamation-circle"></i> ${escapeHtml(msg)}`;
    const form      = activeModal.querySelector('.modal-form');
    form.insertBefore(err, form.firstChild);
}

function clearError() {
    document.getElementById('authError')?.remove();
}

function showLoading(on) {
    document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.disabled    = on;
        btn.style.opacity = on ? '0.7' : '1';
    });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// =====================================================
//  OTP OVERLAY
// =====================================================

let _resendTimer = null;

function showOtpOverlay(emailHint) {
    const overlay = document.getElementById('otpOverlay');
    document.getElementById('otpEmailHint').textContent = emailHint || 'your email';

    // Reset state
    clearOtpError();
    clearOtpSuccess();
    document.querySelectorAll('.otp-digit').forEach(d => {
        d.value = '';
        d.classList.remove('filled', 'error-shake');
    });
    document.getElementById('otpVerifyBtn').disabled = false;

    overlay.classList.add('active');

    // Focus first digit
    setTimeout(() => {
        document.querySelectorAll('.otp-digit')[0]?.focus();
    }, 100);

    // Start the 60-second resend cooldown
    startResendCooldown(60);
}

function hideOtpOverlay() {
    document.getElementById('otpOverlay').classList.remove('active');
    clearOtpError();
    clearOtpSuccess();
    if (_resendTimer) { clearInterval(_resendTimer); _resendTimer = null; }
    // Reset resend link
    const link = document.getElementById('otpResendLink');
    link.classList.remove('disabled');
    link.textContent = 'Resend code';
    document.getElementById('otpCountdown').textContent = '';
}

function getOtpValue() {
    return Array.from(document.querySelectorAll('.otp-digit'))
        .map(d => d.value)
        .join('');
}

function showOtpError(msg) {
    clearOtpSuccess();
    const box  = document.getElementById('otpErrorMsg');
    document.getElementById('otpErrorText').textContent = msg;
    box.classList.add('visible');
    // Shake all digits
    document.querySelectorAll('.otp-digit').forEach(d => {
        d.classList.remove('error-shake');
        void d.offsetWidth;
        d.classList.add('error-shake');
    });
    setTimeout(() => {
        document.querySelectorAll('.otp-digit').forEach(d => d.classList.remove('error-shake'));
    }, 400);
}

function clearOtpError() {
    document.getElementById('otpErrorMsg')?.classList.remove('visible');
}

function showOtpSuccess(msg) {
    clearOtpError();
    const box = document.getElementById('otpSuccessMsg');
    document.getElementById('otpSuccessText').textContent = msg;
    box.classList.add('visible');
}

function clearOtpSuccess() {
    document.getElementById('otpSuccessMsg')?.classList.remove('visible');
}

async function submitOtp() {
    const code = getOtpValue();
    if (code.length < 6) {
        showOtpError('Please enter all 6 digits.');
        return;
    }

    const btn = document.getElementById('otpVerifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
    clearOtpError();

    try {
        const result = await callAuth({ action: 'verify_otp', code });
        if (result.success) {
            showOtpSuccess('Verified! Redirecting…');
            sessionStorage.setItem('user_name',  result.name  || '');
            sessionStorage.setItem('user_email', result.email || '');
            const redirect = result.redirect
                || sessionStorage.getItem('redirect_after_login')
                || 'account-dashboard.php';
            sessionStorage.removeItem('redirect_after_login');
            setTimeout(() => { window.location.href = redirect; }, 700);
        } else {
            showOtpError(result.message || 'Verification failed.');
            btn.disabled = false;
            btn.innerHTML = '<span>Verify Code</span><i class="fas fa-arrow-right"></i>';
            // Clear boxes on wrong code
            document.querySelectorAll('.otp-digit').forEach(d => d.value = '');
            document.querySelectorAll('.otp-digit')[0]?.focus();
        }
    } catch (e) {
        showOtpError('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<span>Verify Code</span><i class="fas fa-arrow-right"></i>';
    }
}

async function resendOtp() {
    const link = document.getElementById('otpResendLink');
    if (link.classList.contains('disabled')) return;

    clearOtpError();
    clearOtpSuccess();

    try {
        const result = await callAuth({ action: 'resend_otp' });
        if (result.success) {
            showOtpSuccess('New code sent! Check your inbox.');
            document.querySelectorAll('.otp-digit').forEach(d => { d.value = ''; d.classList.remove('filled'); });
            document.querySelectorAll('.otp-digit')[0]?.focus();
            startResendCooldown(60);
        } else {
            showOtpError(result.message || 'Could not resend code.');
        }
    } catch (e) {
        showOtpError('Network error. Please try again.');
    }
}

function startResendCooldown(seconds) {
    const link      = document.getElementById('otpResendLink');
    const countdown = document.getElementById('otpCountdown');

    link.classList.add('disabled');
    link.textContent = 'Resend code';
    if (_resendTimer) clearInterval(_resendTimer);

    let remaining = seconds;
    countdown.textContent = ` (${remaining}s)`;

    _resendTimer = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(_resendTimer);
            _resendTimer = null;
            countdown.textContent = '';
            link.classList.remove('disabled');
        } else {
            countdown.textContent = ` (${remaining}s)`;
        }
    }, 1000);
}

// =====================================================
//  OTP DIGIT INPUT BEHAVIOUR
// =====================================================
function initOtpDigits() {
    const digits = Array.from(document.querySelectorAll('.otp-digit'));

    digits.forEach((input, index) => {
        // Type a digit → advance
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val.slice(-1); // keep only last digit
            if (val) {
                e.target.classList.add('filled');
                if (index < digits.length - 1) digits[index + 1].focus();
                // Auto-submit when all 6 filled
                if (getOtpValue().length === 6) submitOtp();
            } else {
                e.target.classList.remove('filled');
            }
            clearOtpError();
        });

        // Backspace → clear & go back
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace') {
                if (input.value) {
                    input.value = '';
                    input.classList.remove('filled');
                } else if (index > 0) {
                    digits[index - 1].focus();
                    digits[index - 1].value = '';
                    digits[index - 1].classList.remove('filled');
                }
                e.preventDefault();
            }
            // Arrow keys
            if (e.key === 'ArrowLeft'  && index > 0)              digits[index - 1].focus();
            if (e.key === 'ArrowRight' && index < digits.length-1) digits[index + 1].focus();
        });

        // Paste → distribute digits
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData)
                .getData('text').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((ch, i) => {
                if (digits[i]) {
                    digits[i].value = ch;
                    digits[i].classList.add('filled');
                }
            });
            const next = Math.min(pasted.length, digits.length - 1);
            digits[next].focus();
            if (pasted.length === 6) submitOtp();
        });

        // Only allow numeric input
        input.addEventListener('keypress', (e) => {
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
    });
}

// =====================================================
//  DOM READY
// =====================================================
document.addEventListener('DOMContentLoaded', async () => {
    window.__debugRunId = `run_${Date.now()}`;

    loadGoogleSDK();
    loadFacebookSDK();
    initOtpDigits();

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
                onLoginSuccess(result); // routes to OTP overlay OR redirect directly for admin
            } else {
                showError(result.message);
            }
        } catch (e) {
            showError(e?.message || 'Login failed. Please check your connection and try again.');
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
        if (password.length < 8)          { showError('Password must be at least 8 characters.'); return; }

        showLoading(true);
        try {
            const result = await callAuth({ action: 'signup', name, email, password });
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message);
            }
        } catch (e) {
            showError(e?.message || 'Signup failed. Please check your connection and try again.');
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
            const redirect = session.redirect
                || sessionStorage.getItem('redirect_after_login')
                || 'account-dashboard.php';
            sessionStorage.removeItem('redirect_after_login');
            window.location.href = redirect;
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