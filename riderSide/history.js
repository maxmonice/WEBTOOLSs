document.addEventListener('DOMContentLoaded', () => {
  fetchHistory();
});

let fullHistory = [];

async function fetchHistory() {
  try {
    const res = await fetch('rider-orders-api.php?action=get_history');
    const data = await res.json();
    if (data.success) {
      fullHistory = data.history;
      renderStats(data);
      renderHistory(fullHistory);
    }
  } catch (err) {
    console.error('History fetch error:', err);
  }
}

function handleHistorySearch() {
  const query = document.getElementById('historySearch').value.toLowerCase().trim();
  if (!query) {
    renderHistory(fullHistory);
    return;
  }

  const filtered = fullHistory.filter(item => {
    return item.customer_name.toLowerCase().includes(query) ||
           item.order_num.toLowerCase().includes(query) ||
           item.address.toLowerCase().includes(query);
  });
  
  renderHistory(filtered);
}

function renderStats(data) {
  const deliveriesVal = document.querySelector('.earning-item .earning-val');
  if (deliveriesVal) deliveriesVal.textContent = data.total_deliveries;
}

function renderHistory(history) {
  const container = document.querySelector('.history-body');
  if (!container) return;

  if (history.length === 0) {
    container.innerHTML = `
      <div style="text-align:center;padding:40px;opacity:0.5;">
        <i class="fas fa-box-open" style="font-size:3rem;margin-bottom:15px;"></i>
        <div>No delivery history yet</div>
      </div>
    `;
    return;
  }

  // Group by date
  const groups = {};
  history.forEach(item => {
    if (!groups[item.date]) groups[item.date] = [];
    groups[item.date].push(item);
  });

  let html = '';
  for (const date in groups) {
    html += `<div class="history-date-label">${date}</div>`;
    groups[date].forEach(item => {
      // Clean order ID for the API call (strip #ORD-)
      const cleanId = item.order_num.replace('#ORD-', '').replace(/^0+/, '');
      html += `
        <div class="history-card" onclick="viewDelivery('${cleanId}')">
          <div class="history-icon"><i class="fas fa-check"></i></div>
          <div class="history-info">
            <div class="history-name">${item.customer_name} — ${item.order_num}</div>
            <div class="history-meta">
              <div class="meta-item">
                <i class="fas fa-box"></i> 
                <span>${item.items_count} ${item.items_count === 1 ? 'item' : 'items'}</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-map-marker-alt"></i> 
                <span class="meta-address">${item.address}</span>
              </div>
            </div>
          </div>
          <div class="history-right">
            <div class="history-amount">+${item.total}</div>
            <div class="history-time">${item.time}</div>
          </div>
        </div>
      `;
    });
  }
  container.innerHTML = html;
}

async function viewDelivery(orderId) {
  const modal = document.getElementById('historyModal');
  const modalBody = document.getElementById('modalBody');
  const modalTitle = document.getElementById('modalOrderNum');

  modalTitle.textContent = 'Loading...';
  modalBody.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i></div>';
  modal.classList.add('open');

  try {
    const res = await fetch(`rider-orders-api.php?action=get_order_details&order_id=${orderId}`);
    const data = await res.json();

    if (data.success) {
      const order = data.order;
      modalTitle.textContent = order.order_num;
      
      let itemsHtml = order.items.map(item => `
        <div class="detail-item">
          <span><span class="item-qty">${item.quantity}x</span> ${item.name}</span>
          <span>₱${(item.price * item.quantity).toFixed(2)}</span>
        </div>
      `).join('');

      modalBody.innerHTML = `
        <div class="detail-section">
          <div class="detail-label">Customer Information</div>
          <div class="detail-box">
            <div class="detail-row"><strong>Name:</strong> <span>${order.user_name}</span></div>
            <div class="detail-row"><strong>Phone:</strong> <span>${order.phone}</span></div>
            <div class="detail-row"><strong>Address:</strong> <span>${order.address}</span></div>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-label">Order Summary</div>
          <div class="detail-box">
            <div class="detail-item-list">
              ${itemsHtml}
            </div>
            <div class="detail-total">
              <div class="detail-row"><strong>Payment:</strong> <span>${order.payment_method}</span></div>
              <div class="detail-row" style="font-size:1.1rem;color:#4ade80;margin-top:5px;">
                <strong>Total Earned:</strong> <span>₱${parseFloat(order.total_amount).toLocaleString()}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-label">Timeline</div>
          <div class="detail-box">
            <div class="detail-row"><strong>Placed:</strong> <span>${new Date(order.created_at).toLocaleString()}</span></div>
            <div class="detail-row"><strong>Delivered:</strong> <span>${order.formatted_date} at ${order.formatted_time}</span></div>
          </div>
        </div>
      `;
    } else {
      modalBody.innerHTML = `<div style="color:var(--red);text-align:center;">${data.message}</div>`;
    }
  } catch (err) {
    modalBody.innerHTML = '<div style="color:var(--red);text-align:center;">Failed to load details.</div>';
  }
}

function closeModal() {
  document.getElementById('historyModal').classList.remove('open');
}

function updateClock() {
  const now = new Date();
  document.querySelector('.time').textContent =
    now.getHours().toString().padStart(2, '0') + ':' +
    now.getMinutes().toString().padStart(2, '0');
}
updateClock();
setInterval(updateClock, 60000);