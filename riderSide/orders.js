// =====================================================
// ORDERS PAGE JAVASCRIPT — Live orders from database
// =====================================================

let currentOrder = null; // full order object currently in modal

// ── Fetch and render orders ────────────────────────────
function loadOrders() {
  fetch('rider-orders-api.php?action=get_rider_orders')
    .then(r => r.json())
    .then(data => {
      document.getElementById('loading-state').style.display = 'none';

      if (!data.success) {
        showToast('Failed to load orders', 'error');
        return;
      }

      const orders = data.orders || [];

      // Update stats
      document.getElementById('stat-incoming').textContent = orders.length;

      // Update badge
      const badge = document.getElementById('order-badge');
      if (orders.length > 0) {
        badge.textContent = orders.length;
        badge.style.display = 'block';
      } else {
        badge.style.display = 'none';
      }

      // Show/hide empty state
      if (orders.length === 0) {
        document.getElementById('empty-state').style.display = 'flex';
        document.getElementById('orders-list').innerHTML = '';
        return;
      }

      document.getElementById('empty-state').style.display = 'none';
      renderOrderCards(orders);
    })
    .catch(() => {
      document.getElementById('loading-state').style.display = 'none';
      document.getElementById('empty-state').style.display = 'flex';
      document.getElementById('stat-incoming').textContent = '0';
    });
}

function renderOrderCards(orders) {
  const container = document.getElementById('orders-list');
  container.innerHTML = orders.map(order => {
    const isShipped = order.status === 'shipped';
    const isConfirmed = order.status === 'confirmed';
    const isLocked = !isShipped && !isConfirmed;
    
    return `
    <div class="order-card ${isLocked ? 'locked' : ''}" data-id="${order.id}" onclick="handleOrderTap(${JSON.stringify(order).replace(/"/g, '&quot;')})">
      <div class="order-card-header">
        <span class="order-id">${order.order_num}</span>
        <span class="order-badge ${isShipped ? 'delivering' : isConfirmed ? 'sent' : 'prep'}">
          ${isShipped ? 'Delivering' : isConfirmed ? 'Sent' : 'Preparing'}
        </span>
      </div>
      <div class="order-info-row">
        <i class="fas fa-user"></i>
        <span>${order.customer_name}</span>
      </div>
      <div class="order-info-row">
        <i class="fas fa-map-marker-alt"></i>
        <span>${order.address}</span>
      </div>
      <div class="order-info-row">
        <i class="fas fa-shopping-bag"></i>
        <span>${order.items_summary || 'Order items'}</span>
      </div>
      ${order.eta ? `<div class="order-info-row" style="color:#f39c12;"><i class="fas fa-clock"></i><span>Ready in: ${order.eta}</span></div>` : ''}
      <div class="order-footer">
        <span class="order-total">${order.total}</span>
        ${isConfirmed
          ? `<button class="accept-btn" onclick="event.stopPropagation();quickAccept(${order.id})"><i class="fas fa-route"></i> On Route</button>`
          : isShipped
          ? `<button class="accept-btn" onclick="event.stopPropagation();window.location.href='map.php?order_id=${order.id}'" style="background:#22c55e;"><i class="fas fa-map-marked-alt"></i> View Map</button>`
          : `<button class="accept-btn secondary" disabled><i class="fas fa-clock"></i> Waiting</button>`
        }
      </div>
    </div>
    `;
  }).join('');
}

function handleOrderTap(order) {
  if (typeof order === 'string') order = JSON.parse(order.replace(/&quot;/g, '"'));
  if (order.status !== 'confirmed' && order.status !== 'shipped') {
    showToast('This order is still being prepared by staff.', 'error');
    return;
  }
  openOrderDetail(order);
}

// ── Order Detail Modal ────────────────────────────────
function openOrderDetail(order) {
  if (typeof order === 'string') order = JSON.parse(order.replace(/&quot;/g, '"'));
  currentOrder = order;

  document.getElementById('detailTitle').textContent = order.order_num;
  document.getElementById('detailName').textContent    = order.customer_name;
  document.getElementById('detailPhone').textContent   = order.customer_phone || '—';
  document.getElementById('detailPayment').textContent = order.payment;
  document.getElementById('detailAddress').textContent = order.address;
  document.getElementById('detailTotal').textContent   = order.total;

  // Build items list
  const itemsHtml = (order.items && order.items.length)
    ? order.items.map(item => {
        let price = item.price || item.subtotal || 0;
        if (typeof price === 'string') price = price.replace(/[^\d.]/g, '');
        const formattedPrice = parseFloat(price || 0).toFixed(2);
        
        return `
        <div class="order-item-row">
          <div class="item-name-qty">
            <span class="item-qty-badge">${item.quantity || 1}x</span>
            <span>${item.name || 'Item'}</span>
          </div>
          <span class="item-price">₱${formattedPrice}</span>
        </div>`;
      }).join('')
    : `<div style="padding:12px;color:rgba(255,255,255,0.4);font-size:0.85rem;">${order.items_summary || 'No item details'}</div>`;

  document.getElementById('detailItems').innerHTML = itemsHtml;
  const mainBtn = document.getElementById('detailMainBtn');
  
  if (order.status === 'confirmed') {
    mainBtn.disabled = false;
    mainBtn.innerHTML = '<i class="fas fa-route"></i> On Route';
    mainBtn.onclick = acceptFromDetail;
    mainBtn.style.background = '';
  } else if (order.status === 'shipped') {
    mainBtn.disabled = false;
    mainBtn.innerHTML = '<i class="fas fa-map-marked-alt"></i> Back to Map';
    mainBtn.onclick = () => window.location.href = 'map.php?order_id=' + order.id;
    mainBtn.style.background = '#22c55e';
  } else {
    mainBtn.disabled = true;
    mainBtn.innerHTML = '<i class="fas fa-clock"></i> Waiting for Staff';
    mainBtn.style.background = '';
  }
  document.getElementById('orderDetailModal').classList.add('open');
}

function closeOrderDetail() {
  document.getElementById('orderDetailModal').classList.remove('open');
  currentOrder = null;
}

function acceptFromDetail() {
  if (!currentOrder) return;
  if (currentOrder.status !== 'confirmed') {
    showToast('Order is not ready yet.', 'error');
    return;
  }
  doAcceptOrder(currentOrder.id);
}

// ── Accept order ──────────────────────────────────────
function quickAccept(orderId) {
  doAcceptOrder(orderId);
}

function doAcceptOrder(orderId) {
  const fd = new FormData();
  fd.append('action', 'accept_order');
  fd.append('order_id', orderId);

  fetch('rider-orders-api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        closeOrderDetail();
        showToast('✅ Order accepted! Opening map...', 'success');
        // Use order_id from response as fallback in case orderId variable is stale
        const navId = d.order_id || orderId;
        setTimeout(() => {
          window.location.href = 'map.php?order_id=' + navId;
        }, 1200);
      } else {
        showToast('❌ ' + (d.message || 'Could not accept order'), 'error');
      }
    })
    .catch(() => showToast('❌ Network error', 'error'));
}

// ── Toast ─────────────────────────────────────────────
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type === 'error' ? ' error' : '');
  setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Auto-refresh every 8 seconds for new orders ───────
function startAutoRefresh() {
  loadOrders();
  setInterval(loadOrders, 8000); // Match customer polling for consistent sync
}

// ── Init ──────────────────────────────────────────────
startAutoRefresh();