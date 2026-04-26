// =====================================================
//  account-new.js — Clean, working authentication
//  Simple email signup/login + Google/Facebook OAuth
// =====================================================

const AUTH_URL = 'Auth-new.php';
const GOOGLE_CLIENT_ID = '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';

// =====================================================
//  GOOGLE OAUTH
// =====================================================
function loadGoogleSDK() {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.onload = initGoogle;
    document.head.appendChild(script);
}

function initGoogle() {
    google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: handleGoogleResponse,
        auto_select: false,
        cancel_on_tap_outside: true,
    });
}

function handleGoogleResponse(response) {
    if (!response || !response.credential) {
        showError('Google sign-in failed. Please try again.');
        return;
    }
    
    showLoading(true);
    callAuth({
        action: 'google_auth',
        id_token: response.credential
    }).then(result => {
        if (result.success) {
            onLoginSuccess(result);
        } else {
            showError(result.message || 'Google sign-in failed.');
        }
    }).catch(e => {
        showError('Google sign-in failed. Please try again.');
    }).finally(() => {
        showLoading(false);
    });
}

function signInWithGoogle() {
    if (typeof google === 'undefined') {
        showError('Google sign-in is not available. Please try again in a moment.');
        return;
    }
    
    // Try to show the popup
    try {
        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed()) {
                showError('Google sign-in was blocked by your browser.');
            } else if (notification.isSkippedMoment()) {
                showError('Google sign-in was dismissed. Please try again.');
            }
        });
    } catch (e) {
        showError('Google sign-in failed. Please try again.');
    }
}

// =====================================================
//  FACEBOOK OAUTH
// =====================================================
function loadFacebookSDK() {
    if (window.fbAsyncInitCalled) return;
    window.fbAsyncInitCalled = true;
    
    window.fbAsyncInit = function() {
        try {
            FB.init({
                appId: '1282425887392045',
                cookie: true,
                xfbml: true,
                version: 'v19.0',
                status: true
            });
        } catch (e) {
            console.error('Facebook SDK init failed:', e);
        }
    };
    
    const script = document.createElement('script');
    script.src = 'https://connect.facebook.net/en_US/sdk.js';
    script.async = true;
    document.head.appendChild(script);
}

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
            action: 'facebook_auth',
            access_token: response.authResponse.accessToken
        }).then(result => {
            if (result.success) {
                onLoginSuccess(result);
            } else {
                showError(result.message || 'Facebook sign-in failed.');
            }
        }).catch(e => {
            showError('Facebook sign-in failed. Please try again.');
        }).finally(() => {
            showLoading(false);
        });
    }, { scope: 'public_profile,email' });
}

// =====================================================
//  CORE API CALLER
// =====================================================
async function callAuth(payload) {
    const response = await fetch(AUTH_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload),
    });
    
    if (!response.ok) {
        throw new Error('Server error: ' + response.status);
    }
    
    return response.json();
}

// =====================================================
//  LOGIN SUCCESS HANDLER
// =====================================================
function onLoginSuccess(result) {
    sessionStorage.setItem('user_name', result.name || '');
    sessionStorage.setItem('user_email', result.email || '');
    
    const redirect = result.redirect || 'account-dashboard.php';
    window.location.href = redirect;
}

// =====================================================
//  UI HELPERS
// =====================================================
function showError(msg) {
    clearMessages();
    const activeModal = document.querySelector('.modal:not(.hidden)');
    if (!activeModal) return;
    
    const error = document.createElement('div');
    error.className = 'auth-error';
    error.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${escapeHtml(msg)}`;
    
    const form = activeModal.querySelector('.modal-form');
    form.insertBefore(error, form.firstChild);
}

function showSuccess(msg) {
    clearMessages();
    const activeModal = document.querySelector('.modal:not(.hidden)');
    if (!activeModal) return;
    
    const success = document.createElement('div');
    success.className = 'auth-success';
    success.innerHTML = `<i class="fas fa-check-circle"></i> ${escapeHtml(msg)}`;
    
    const form = activeModal.querySelector('.modal-form');
    form.insertBefore(success, form.firstChild);
}

function clearMessages() {
    document.querySelectorAll('.auth-error, .auth-success').forEach(el => el.remove());
}

function showLoading(show) {
    document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.disabled = show;
        btn.style.opacity = show ? '0.7' : '1';
    });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email.trim());
}

// =====================================================
//  MODAL SWITCHING
// =====================================================
function switchModal(target) {
    const login = document.getElementById('loginModal');
    const signup = document.getElementById('signupModal');
    
    clearMessages();
    
    if (target === 'signup') {
        login.classList.add('hidden');
        signup.classList.remove('hidden');
    } else {
        signup.classList.add('hidden');
        login.classList.remove('hidden');
    }
}

// =====================================================
//  PASSWORD TOGGLE
// =====================================================
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// =====================================================
//  DOM READY
// =====================================================
document.addEventListener('DOMContentLoaded', async () => {
    // Load SDKs
    loadGoogleSDK();
    loadFacebookSDK();
    
    // Mobile menu
    const menuToggle = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('navMenu');
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
    }
    
    // Social login buttons
    document.querySelectorAll('.btn-google').forEach(btn => {
        btn.addEventListener('click', signInWithGoogle);
    });
    
    document.querySelectorAll('.btn-facebook').forEach(btn => {
        btn.addEventListener('click', signInWithFacebook);
    });
    
    // Email login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessages();
            
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            
            if (!email || !password) {
                showError('Please fill in all fields.');
                return;
            }
            
            if (!validateEmail(email)) {
                showError('Please enter a valid email address.');
                return;
            }
            
            showLoading(true);
            
            try {
                const result = await callAuth({
                    action: 'login',
                    email,
                    password
                });
                
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
    }
    
    // Email signup form
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessages();
            
            const name = document.getElementById('signupName').value.trim();
            const email = document.getElementById('signupEmail').value.trim();
            const password = document.getElementById('signupPassword').value;
            
            if (!name || !email || !password) {
                showError('Please fill in all fields.');
                return;
            }
            
            if (!validateEmail(email)) {
                showError('Please enter a valid email address.');
                return;
            }
            
            if (password.length < 6) {
                showError('Password must be at least 6 characters.');
                return;
            }
            
            showLoading(true);
            
            try {
                const result = await callAuth({
                    action: 'signup',
                    name,
                    email,
                    password
                });
                
                if (result.success) {
                    showSuccess('Account created successfully! You can now log in.');
                    
                    // Clear signup form
                    document.getElementById('signupName').value = '';
                    document.getElementById('signupEmail').value = '';
                    document.getElementById('signupPassword').value = '';
                    
                    // Switch to login after 2 seconds
                    setTimeout(() => {
                        switchModal('login');
                        document.getElementById('loginEmail').value = email;
                    }, 2000);
                } else {
                    showError(result.message);
                }
            } catch (e) {
                showError('Signup failed. Please check your connection and try again.');
            } finally {
                showLoading(false);
            }
        });
    }
    
    // Check session on page load (no auto-redirect)
    try {
        const session = await callAuth({ action: 'check_session' });
        if (session.success) {
            sessionStorage.setItem('user_name', session.name || '');
            sessionStorage.setItem('user_email', session.email || '');
            // Don't auto-redirect - let user stay on account.php
        }
    } catch (e) {
        // Session check failed, continue with login
    }
});
