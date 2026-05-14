(function () {
  'use strict';

  function toggleSidebar() {
    var s = document.getElementById('sidebar');
    if (s) s.classList.toggle('open');
  }
  window.toggleSidebar = toggleSidebar;

  function postForm(body) {
    return fetch('admin-orders.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(body),
      credentials: 'same-origin',
    }).then(function (r) {
      return r.json();
    });
  }

  window.adminPrepareOrder = function (orderId) {
    var eta = window.prompt('Customer ETA (required), e.g. "45 minutes" or "2:30 PM":', '45 minutes');
    if (eta === null) return;
    eta = String(eta).trim();
    if (!eta) {
      window.alert('ETA is required to start preparing.');
      return;
    }
    postForm({ action: 'prepare_order', order_id: String(orderId), eta: eta })
      .then(function (data) {
        if (data && data.success) {
          window.location.reload();
        } else {
          window.alert((data && data.message) || 'Could not update order');
        }
      })
      .catch(function () {
        window.alert('Network error. Please try again.');
      });
  };

  window.adminCompletePrepare = function (orderId) {
    postForm({ action: 'complete_order', order_id: String(orderId) })
      .then(function (data) {
        if (data && data.success) {
          window.location.reload();
        } else {
          window.alert((data && data.message) || 'Could not update order');
        }
      })
      .catch(function () {
        window.alert('Network error. Please try again.');
      });
  };

  window.adminSetOrderStatus = function (orderId, status) {
    if (status === 'cancelled' && !window.confirm('Cancel this order?')) return;
    postForm({ action: 'update_order_status', order_id: String(orderId), status: status })
      .then(function (data) {
        if (data && data.success) {
          window.location.reload();
        } else {
          window.alert((data && data.message) || 'Could not update order');
        }
      })
      .catch(function () {
        window.alert('Network error. Please try again.');
      });
  };
})();
