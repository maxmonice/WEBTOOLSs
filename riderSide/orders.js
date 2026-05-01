// =====================================================
// ORDERS PAGE JAVASCRIPT
// =====================================================
let currentOrderStatus = '';

// ── Filter tabs ───────────────────────────────────────
function filterOrders(tab, status) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  tab.classList.add('active');
  document.querySelectorAll('.order-card').forEach(card => {
    card.style.display = (status === 'all' || card.dataset.status === status) ? 'block' : 'none';
  });
}

// ── Order Detail Modal ────────────────────────────────
function openOrderDetail(id, name, address, items, total, payment, phone, status) {
  currentOrderStatus = status;
  document.getElementById('detailTitle').textContent = id;
  document.getElementById('detailName').textContent = name;
  document.getElementById('detailPhone').textContent = phone;
  document.getElementById('detailPayment').textContent = payment;
  document.getElementById('detailAddress').textContent = address;
  document.getElementById('detailTotal').textContent = total;

  const prices = ['₱195', '₱390', '₱280', '₱360', '₱1,250'];
  document.getElementById('detailItems').innerHTML = items.split(', ').map((item, i) => `
    <div class="order-item-row">
      <div class="item-name-qty">
        <span class="item-qty-badge">${item.match(/x(\d+)/)?.[1] || 1}x</span>
        <span>${item.replace(/x\d+/, '').trim()}</span>
      </div>
      <span class="item-price">${prices[i] || '₱195'}</span>
    </div>`).join('');

  const btn = document.getElementById('detailMainBtn');
  if (status === 'new') btn.innerHTML = '<i class="fas fa-check"></i> Accept Order';
  else if (status === 'pickup') btn.innerHTML = '<i class="fas fa-map-marked-alt"></i> Navigate';
  else btn.innerHTML = '<i class="fas fa-check-circle"></i> Mark Delivered';

  document.getElementById('orderDetailModal').classList.add('open');
}

function closeOrderDetail() {
  document.getElementById('orderDetailModal').classList.remove('open');
}

function acceptFromDetail() {
  closeOrderDetail();
  if (currentOrderStatus === 'new') {
    showToast('✅ Order accepted!');
    updateBadge();
  } else if (currentOrderStatus === 'pickup') {
    window.location.href = 'map.php';
  } else {
    showToast('🎉 Delivery confirmed!');
    setTimeout(() => window.location.href = 'history.php', 1200);
  }
}

// ── Accept order button ───────────────────────────────
function acceptOrder(btn) {
  btn.textContent = '✓ Accepted';
  btn.style.background = 'rgba(34,197,94,.2)';
  btn.style.color = '#4ade80';
  btn.style.border = '1px solid rgba(34,197,94,.3)';
  btn.disabled = true;
  showToast('✅ Order accepted!');
  updateBadge();
}

function updateBadge() {
  const badge = document.getElementById('order-badge');
  const cur = parseInt(badge.textContent);
  if (cur > 0) badge.textContent = cur - 1;
  if (parseInt(badge.textContent) === 0) badge.style.display = 'none';
}

// ── Toast ─────────────────────────────────────────────
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// ── Clock ─────────────────────────────────────────────
function updateClock() {
  const now = new Date();
  document.querySelector('.time').textContent =
    now.getHours().toString().padStart(2, '0') + ':' +
    now.getMinutes().toString().padStart(2, '0');
}
updateClock();
setInterval(updateClock, 60000);