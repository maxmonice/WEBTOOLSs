(function () {
  'use strict';
  var overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-xmark';
  }
  function closeSidebarMenu() {
    document.getElementById('sidebar').classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-bars';
  }
  window.toggleSidebar = function () {
    document.getElementById('sidebar').classList.contains('open') ? closeSidebarMenu() : openSidebar();
  };
  overlay.addEventListener('click', closeSidebarMenu);
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.nav-item:not(.locked)').forEach(function (item) {
      item.addEventListener('click', function () { if (window.innerWidth <= 900) closeSidebarMenu(); });
    });
  });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) closeSidebarMenu(); });
})();

let pendingCompleteOrderId = null;

// ── ETA Modal ──
function openPrepareModal(orderId) {
  document.getElementById('etaOrderId').value = orderId;
  document.getElementById('etaSelect').value = '20-30 mins';
  document.getElementById('etaCustom').style.display = 'none';
  document.getElementById('etaModal').style.display = 'flex';
}
function closeEtaModal() {
  document.getElementById('etaModal').style.display = 'none';
}
document.getElementById('etaSelect').addEventListener('change', function() {
  document.getElementById('etaCustom').style.display = this.value === 'custom' ? 'block' : 'none';
});
function submitPrepare() {
  const orderId = document.getElementById('etaOrderId').value;
  const sel = document.getElementById('etaSelect');
  const eta = sel.value === 'custom' ? document.getElementById('etaCustom').value.trim() : sel.value;
  if (!eta) { alert('Please enter an estimated time.'); return; }
  const fd = new FormData();
  fd.append('action', 'prepare_order');
  fd.append('order_id', orderId);
  fd.append('eta', eta);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { closeEtaModal(); showToast('🍳 Order is being prepared!'); setTimeout(() => location.reload(), 1200); }
      else alert('Error: ' + d.message);
    });
}

// ── Done Preparing ──
function completeOrder(orderId) {
  pendingCompleteOrderId = orderId;
  const label = '#ORD-' + String(orderId).padStart(4, '0');
  document.getElementById('sendRiderOrderLabel').textContent = 'Order ' + label;
  document.getElementById('sendRiderConfirm').style.display = 'flex';
}

function closeSendRiderConfirm() {
  pendingCompleteOrderId = null;
  document.getElementById('sendRiderConfirm').style.display = 'none';
}

function submitSendToRider() {
  if (!pendingCompleteOrderId) return;
  const fd = new FormData();
  fd.append('action', 'complete_order');
  fd.append('order_id', pendingCompleteOrderId);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        closeSendRiderConfirm();
        showToast('🏍️ Order sent to rider!');
        setTimeout(() => location.reload(), 1200);
      }
      else alert('Error: ' + d.message);
    });
}

// ── Cancel ──
function cancelOrder(orderId) {
  if (!confirm('Cancel this order?')) return;
  const fd = new FormData();
  fd.append('action', 'cancel_order');
  fd.append('order_id', orderId);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { showToast('Order cancelled.'); setTimeout(() => location.reload(), 1000); }
      else alert('Error: ' + d.message);
    });
}

// ── View Details Modal ──
function openViewModal(order) {
    const modal = document.getElementById('viewModal');
    document.getElementById('viewOrderIdLabel').textContent = '#ORD-' + order.id.toString().padStart(4, '0');
    
    // Use orders.items (option 1) from the staff row payload
    // items_json can be stored as JSON array OR as an object wrapper
    let parsed = [];
    try {
        parsed = JSON.parse(order.items_json || '[]');
    } catch (e) {
        parsed = [];
    }

    // If items are wrapped like {"items": [...]}
    const wrapped = (parsed && parsed.items) ? parsed.items : parsed;
    const safeItems = Array.isArray(wrapped) ? wrapped : [];

    let itemsHtml = safeItems.map(item => `
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
            <span>${item.name} <span style="color:rgba(255,255,255,0.4);">x${item.quantity || 1}</span></span>
            <span style="font-weight:600;">₱${parseFloat(item.price || 0).toLocaleString()}</span>
        </div>
    `).join('');

    const content = `
        <div class="view-modal-info-grid">
            <div>
                <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Customer</p>
                <p style="margin:0; font-weight:700;">${order.customer_name}</p>
                <p style="margin:2px 0 0; font-size:0.8rem; color:rgba(255,255,255,0.6);">${order.customer_email}</p>
            </div>
            <div>
                <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Payment Method</p>
                <p style="margin:0; font-weight:700;">${order.payment_method.toUpperCase()}</p>
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Delivery Address</p>
            <p style="margin:0; font-style:italic; line-height:1.4;">${order.address}</p>
        </div>
        <div style="background:rgba(255,255,255,0.03); border-radius:10px; padding:15px; border:1px solid rgba(255,255,255,0.05);">
            <p style="margin:0 0 10px; color:#3498db; font-size:0.75rem; font-weight:800; text-transform:uppercase;">Items Summary</p>
            ${itemsHtml || '<p style="color:rgba(255,255,255,0.4);">No items found</p>'}
            <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.1);">
                <span style="font-weight:700; color:#fff;">Total Amount</span>
                <span style="font-weight:800; color:var(--red); font-size:1.1rem;">₱${parseFloat(order.total).toLocaleString()}</span>
            </div>
        </div>
        ${order.eta ? `
            <div style="margin-top:15px; padding:10px; background:rgba(243,156,18,0.1); border-left:3px solid #f39c12; border-radius:4px;">
                <span style="font-size:0.8rem; color:#f39c12;"><i class="fa-solid fa-clock"></i> Current ETA: <strong>${order.eta}</strong></span>
            </div>
        ` : ''}
    `;

    document.getElementById('viewOrderContent').innerHTML = content;
    modal.style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// ── Toast ──
function showToast(msg) {
  const t = document.createElement('div');
  t.textContent = msg;
  t.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:99999;padding:12px 22px;border-radius:10px;font-size:0.9rem;font-weight:600;color:#fff;background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 8px 30px rgba(0,0,0,0.4);';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ── Auto-Refresh for Incoming Orders ──
// Refresh the page every 30 seconds to show new orders automatically
setInterval(() => {
    // Only refresh if no modal is open to avoid interrupting the staff
    const etaModal = document.getElementById('etaModal');
    const viewModal = document.getElementById('viewModal');
    if (etaModal.style.display === 'none' && viewModal.style.display === 'none') {
        location.reload();
    }
}, 30000);