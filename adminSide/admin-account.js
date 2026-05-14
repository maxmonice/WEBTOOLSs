(function () {
  'use strict';

  function toggleSidebar() {
    var s = document.getElementById('sidebar');
    if (s) s.classList.toggle('open');
  }
  window.toggleSidebar = toggleSidebar;

  function showMessage(message, type) {
    type = type || 'success';
    var container = document.getElementById('messageContainer');
    if (!container) return;
    var messageDiv = document.createElement('div');
    messageDiv.style.cssText =
      'padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; color: #fff; font-weight: 500; animation: slideIn 0.3s ease; ' +
      (type === 'success' ? 'background: #22c55e;' : 'background: #ef4444;');
    messageDiv.innerHTML =
      '<i class="fa-solid fa-' +
      (type === 'success' ? 'check-circle' : 'exclamation-circle') +
      '"></i> ' +
      message;
    container.appendChild(messageDiv);
    setTimeout(function () {
      messageDiv.style.animation = 'slideOut 0.3s ease';
      setTimeout(function () {
        messageDiv.remove();
      }, 300);
    }, 3000);
  }

  function resetForm() {
    var form = document.getElementById('profileForm');
    if (!form) return;
    var n = form.getAttribute('data-default-name') || '';
    var e = form.getAttribute('data-default-email') || '';
    form.reset();
    var nameEl = document.getElementById('name');
    var emailEl = document.getElementById('email');
    if (nameEl) nameEl.value = n;
    if (emailEl) emailEl.value = e;
  }
  window.resetForm = resetForm;

  function postSettings(payload) {
    return fetch('../admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    }).then(function (r) {
      return r.json();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('profileForm');
    if (!form) return;

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var name = (document.getElementById('name') && document.getElementById('name').value) || '';
      var email = (document.getElementById('email') && document.getElementById('email').value) || '';
      var current = (document.getElementById('current_password') && document.getElementById('current_password').value) || '';
      var newPw = (document.getElementById('new_password') && document.getElementById('new_password').value) || '';
      var confirmPw = (document.getElementById('confirm_password') && document.getElementById('confirm_password').value) || '';

      if (current || newPw || confirmPw) {
        if (!current || !newPw || !confirmPw) {
          showMessage('To change password, fill current, new, and confirm fields.', 'error');
          return;
        }
      }

      var changingPw = !!(current && newPw && confirmPw);
      var chain = Promise.resolve();

      if (changingPw) {
        chain = chain.then(function () {
          return postSettings({
            action: 'change_password',
            current_password: current,
            new_password: newPw,
            confirm_password: confirmPw,
          }).then(function (data) {
            if (!data || !data.success) {
              throw new Error((data && data.message) || 'Password change failed');
            }
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
          });
        });
      }

      chain
        .then(function () {
          return postSettings({ action: 'update_profile', name: name.trim(), email: email.trim() });
        })
        .then(function (data) {
          if (!data || !data.success) {
            throw new Error((data && data.message) || 'Could not update profile');
          }
          form.setAttribute('data-default-name', name.trim());
          form.setAttribute('data-default-email', email.trim());
          showMessage(data.message || 'Saved successfully.', 'success');
        })
        .catch(function (err) {
          showMessage(err.message || 'Request failed', 'error');
        });
    });
  });
})();
