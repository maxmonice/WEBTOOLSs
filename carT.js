// Complete cart system code from menu.js moved here
// =====================================================
let cart = JSON.parse(localStorage.getItem('cart')) || [];
const SHIPPING = 50;

// Cart count updater (for menu page)
window.updateCartCount = function() {
  const cartCountEls = document.querySelectorAll('.cart-count');
  const totalItems = cart.reduce((s, i) => s + i.quantity, 0);
  cartCountEls.forEach(el => el.textContent = totalItems);
  localStorage.setItem('cart', JSON.stringify(cart));
}

// Format price helper
function fmt(n) {
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Global addToCart for menu modals (called from menu.js)
window.addItemToCart = function(itemData, qty = 1, variation = null) {
  if (!itemData) return;
  
  // Calculate price based on variation (if logic exists globally)
  let computedPrice = 0;
  if (typeof window.getModalPrice === 'function' && itemData.name === window.currentItem?.name) {
    computedPrice = window.getModalPrice();
  } else {
    computedPrice = typeof itemData.price === 'string' 
      ? parseFloat(itemData.price.replace(/[₱,]/g, '')) || 0 
      : itemData.rawPrice || 0;
  }

  const existing = cart.find(i => i.name === itemData.name && i.variation === variation);
  if (existing) {
    existing.quantity += qty;
  } else {
    cart.push({
      name: itemData.name,
      price: '₱' + computedPrice.toLocaleString('en-PH'),
      rawPrice: computedPrice,
      pieces: itemData.pieces || null,
      variation: variation,
      quantity: qty,
      image: itemData.image,
    });
  }
  localStorage.setItem('cart', JSON.stringify(cart));
  window.updateCartCount();
};

// Alias for compatibility
window.addToCart = window.addItemToCart;

// Full cart render/payment/checkout
function renderCart() {
  const list = document.getElementById('cartItemsList');
  const empty = document.getElementById('cartEmpty');
  const subEl = document.getElementById('cartSubtotal');
  const totalEl = document.getElementById('cartTotal');
  const checkEl = document.getElementById('checkoutTotal');
  const subhead = document.getElementById('cartSubheading');
  list.innerHTML = '';

  if (cart.length === 0) {
    empty.classList.add('show');
    list.style.display = 'none';
  } else {
    empty.classList.remove('show');
    list.style.display = 'flex';
    cart.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'cart-item-row';
      row.innerHTML = `
        <img src="${item.image}" alt="${item.name}" class="cart-item-img">
        <div class="cart-item-info">
          <div class="cart-item-name">${item.name}</div>
          <div class="cart-item-meta">${item.pieces || ''}${item.variation ? (item.pieces ? ' · ' : '') + item.variation : ''}</div>
        </div>
        <div class="cart-item-qty">
          <span class="cart-qty-num">${item.quantity}</span>
          <div class="cart-qty-btn">
            <i class="fas fa-caret-up" onclick="updateQty(${idx}, 1)"></i>
            <i class="fas fa-caret-down" onclick="updateQty(${idx}, -1)"></i>
          </div>
        </div>
        <div class="cart-item-prices">
          <div class="cart-item-price">₱${(item.rawPrice * item.quantity).toLocaleString('en-PH')}</div>
          <div class="cart-item-unit-price">₱${item.rawPrice.toLocaleString('en-PH')}</div>
        </div>
        <button class="cart-item-delete" onclick="removeItem(${idx})"><i class="far fa-trash-alt"></i></button>
      `;
      list.appendChild(row);
    });
  }

  const subtotal = cart.reduce((s, i) => s + i.rawPrice * i.quantity, 0);
  const total = subtotal + SHIPPING;
  if (subEl) subEl.textContent = '₱' + Math.round(subtotal).toLocaleString('en-PH');
  if (totalEl) totalEl.textContent = '₱' + Math.round(total).toLocaleString('en-PH');
  if (checkEl) checkEl.textContent = '₱' + Math.round(total).toLocaleString('en-PH');
  const totalItems = cart.reduce((s, i) => s + i.quantity, 0);
  if (subhead) subhead.textContent = `You have ${totalItems} item${totalItems !== 1 ? 's' : ''} in your cart`;
}

let pendingRemoveIdx = null;

function showRemoveConfirm(idx) {
  pendingRemoveIdx = idx;
  document.getElementById('confirmRemoveOverlay').classList.add('open');
}

function hideRemoveConfirm() {
  pendingRemoveIdx = null;
  document.getElementById('confirmRemoveOverlay').classList.remove('open');
}

function confirmRemoveItem() {
  if (pendingRemoveIdx !== null) {
    cart.splice(pendingRemoveIdx, 1);
    renderCart();
    window.updateCartCount();
  }
  hideRemoveConfirm();
}

function updateQty(idx, delta) {
  if (delta === -1 && cart[idx].quantity === 1) {
    showRemoveConfirm(idx);
    return;
  }
  cart[idx].quantity += delta;
  renderCart();
  window.updateCartCount();
}

function removeItem(idx) {
  showRemoveConfirm(idx);
}

window.openCart = function() {
  const overlay = document.getElementById('cartOverlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    renderCart();
  }
};

window.closeCart = function() {
  const overlay = document.getElementById('cartOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
};

document.addEventListener('DOMContentLoaded', () => {
  renderCart();
  window.updateCartCount();

// Show delivery area notice only when NOT logged in
  const deliveryNoticeKey = 'lukes_delivery_notice_shown';
  const userEmail = sessionStorage.getItem('user_email');
  if (!userEmail && !localStorage.getItem(deliveryNoticeKey)) {
    const deliveryNotice = document.getElementById('deliveryNoticeOverlay');
    if (deliveryNotice) {
      deliveryNotice.classList.add('show');
    }
  }

  // Close delivery notice and save preference
  const deliveryNoticeClose = document.getElementById('deliveryNoticeClose');
  if (deliveryNoticeClose) {
    deliveryNoticeClose.addEventListener('click', () => {
      localStorage.setItem(deliveryNoticeKey, 'true');
      const deliveryNotice = document.getElementById('deliveryNoticeOverlay');
      if (deliveryNotice) deliveryNotice.classList.remove('show');
    });
  }

const deliveryNoticeOverlay = document.getElementById('deliveryNoticeOverlay');
  if (deliveryNoticeOverlay) {
    deliveryNoticeOverlay.addEventListener('click', (e) => {
      if (e.target === deliveryNoticeOverlay) {
        localStorage.setItem(deliveryNoticeKey, 'true');
        deliveryNoticeOverlay.classList.remove('show');
      }
    });
  }

  // Confirm remove modal
  document.getElementById('confirmRemoveYes').addEventListener('click', confirmRemoveItem);
  document.getElementById('confirmRemoveNo').addEventListener('click', hideRemoveConfirm);
  document.getElementById('confirmRemoveOverlay').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) hideRemoveConfirm();
  });

  const cartBackBtn = document.getElementById('cartBackBtn');
  const backToMenuLink = document.getElementById('backToMenuLink');
  if (cartBackBtn) {
    cartBackBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.closeCart();
    });
  }
  if (backToMenuLink) {
    backToMenuLink.addEventListener('click', (e) => {
      e.preventDefault();
      window.closeCart();
    });
  }

  // Payment toggle
  document.querySelectorAll('.payment-method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const isGcash = btn.dataset.method === 'gcash';
      const isCod = btn.dataset.method === 'cod';
      document.getElementById('cardFields').style.display = (isGcash || isCod) ? 'none' : 'block';
      document.getElementById('gcashFields').style.display = isGcash ? 'block' : 'none';
      document.getElementById('codFields').style.display = isCod ? 'block' : 'none';
    });
  });

  // Card input formatting
  const cardNumber = document.getElementById('cardNumber');
  if (cardNumber) {
    cardNumber.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').substring(0, 16);
      this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
  }

  const cardExpiry = document.getElementById('cardExpiry');
  if (cardExpiry) {
    cardExpiry.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').substring(0, 4);
      if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
      this.value = v;
    });
  }

  // QR modal open/close — use delegation so it works even when gcashFields is hidden
  const qrOverlay = document.getElementById('qrOverlay');
  const qrClose = document.getElementById('qrClose');

  document.body.addEventListener('click', (e) => {
    if (e.target.closest('#viewQrBtn')) {
      if (qrOverlay) qrOverlay.classList.add('open');
    }
    if (e.target.closest('#qrClose') || e.target === qrOverlay) {
      if (qrOverlay) qrOverlay.classList.remove('open');
    }
  });

  // GCash number copy-to-clipboard
  const gcashCopyNumber = document.getElementById('gcashCopyNumber');
  if (gcashCopyNumber) {
    gcashCopyNumber.addEventListener('click', async () => {
      const number = gcashCopyNumber.textContent.trim();
      try {
        await navigator.clipboard.writeText(number);
        showCopyTooltip(gcashCopyNumber, 'Copied!');
      } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = number;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyTooltip(gcashCopyNumber, 'Copied!');
      }
    });
  }

  function showCopyTooltip(element, message) {
    let tooltip = element.querySelector('.gcash-copy-tooltip');
    if (!tooltip) {
      tooltip = document.createElement('span');
      tooltip.className = 'gcash-copy-tooltip';
      element.appendChild(tooltip);
    }
    tooltip.textContent = message;
    tooltip.classList.add('show');
    setTimeout(() => {
      tooltip.classList.remove('show');
    }, 1500);
  }

// Toast show/hide functions
  window.showCartEmptyToast = function() {
    document.getElementById('cartToastOverlay').classList.add('show');
    document.getElementById('cartToast').classList.add('show');
  };
  
  window.hideCartEmptyToast = function() {
    document.getElementById('cartToastOverlay').classList.remove('show');
    document.getElementById('cartToast').classList.remove('show');
  };

// Cart toast button - browse menu
  const cartToastBtn = document.getElementById('cartToastBtn');
  if (cartToastBtn) {
    cartToastBtn.addEventListener('click', () => {
      window.hideCartEmptyToast();
      window.closeCart();
    });
  }

  // Address map selector button
  const cartAddressIcon = document.getElementById('cartAddressIcon');
  if (cartAddressIcon) {
    cartAddressIcon.addEventListener('click', () => {
      // Open Google Maps location picker in a new tab
      window.open('https://www.google.com/maps', '_blank');
    });
  }
  // Also close toast when clicking overlay
  const cartToastOverlay = document.getElementById('cartToastOverlay');
  if (cartToastOverlay) {
    cartToastOverlay.addEventListener('click', () => {
      window.hideCartEmptyToast();
    });
  }

// Cart notification bar functions
  window.showCartNotif = function(message, type = 'error') {
    const notif = document.getElementById('cartNotif');
    const notifText = document.getElementById('cartNotifText');
    if (!notif || !notifText) return;
    
    notif.classList.remove('show', 'error', 'success', 'info');
    notif.classList.add('show', type);
    notifText.textContent = message;
    
    // Auto hide after 3 seconds
    setTimeout(() => {
      notif.classList.remove('show');
    }, 3000);
  };

// Order Track Bubble Functions
  window.showOrderTrackBubble = function() {
    const bubble = document.getElementById('orderTrackBubble');
    if (!bubble) return;
    localStorage.setItem('order_pending', 'true');
    bubble.classList.add('show');
  };

  window.hideOrderTrackBubble = function() {
    const bubble = document.getElementById('orderTrackBubble');
    if (!bubble) return;
    localStorage.removeItem('order_pending');
    bubble.classList.remove('show');
  };

  // Check and show bubble on page load if there's a pending order
  if (localStorage.getItem('order_pending') === 'true') {
    const bubble = document.getElementById('orderTrackBubble');
    if (bubble) bubble.classList.add('show');
  }

  // Hide bubble when clicking account icon
  document.querySelectorAll('.nav-account-icon').forEach(icon => {
    icon.addEventListener('click', () => {
      window.hideOrderTrackBubble();
    });
  });

// Top notification bar functions (outside cart)
  window.showTopNotif = function(message, type = 'success') {
    const topNotif = document.getElementById('topNotif');
    const topNotifText = document.getElementById('topNotifText');
    if (!topNotif || !topNotifText) return;
    
    topNotif.classList.remove('show', 'error', 'success', 'info', 'hiding');
    topNotif.classList.add('show', type);
    topNotifText.textContent = message;
    
    // Auto hide after 2 seconds with slide-out animation
    setTimeout(() => {
      topNotif.classList.add('hiding');
      setTimeout(() => {
        topNotif.classList.remove('show', 'hiding');
      }, 200);
    }, 2000);
  };

  // Checkout
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
if (cart.length === 0) {
window.showTopNotif('Cart is empty!', 'info');
                return;
              }
      const address = document.getElementById('cartAddress').value.trim();
      if (!address) {
        document.getElementById('cartAddress').focus();
        return;
      }
      // Show order confirmation modal
      showOrderConfirm();
    });
  }

  // Order confirmation modal
  window.showOrderConfirm = function() {
    const summaryEl = document.getElementById('orderConfirmSummary');
    const subtotalEl = document.getElementById('orderSubtotal');
    const totalEl = document.getElementById('orderTotal');
    
    // Build summary items
    summaryEl.innerHTML = '';
    cart.forEach(item => {
      const itemEl = document.createElement('div/');
      itemEl.className = 'order-confirm-item';
      itemEl.innerHTML = `
        <div class="order-confirm-item-info">
          <img src="${item.image}" alt="${item.name}" class="order-confirm-item-img">
          <div>
            <div class="order-confirm-item-name">${item.name}</div>
            <div class="order-confirm-item-qty">Qty: ${item.quantity}</div>
          </div>
        </div>
        <div class="order-confirm-item-price">₱${(item.rawPrice * item.quantity).toLocaleString('en-PH')}</div>
      `;
      summaryEl.appendChild(itemEl);
    });
    
    // Calculate totals
    const subtotal = cart.reduce((s, i) => s + i.rawPrice * i.quantity, 0);
    const total = subtotal + SHIPPING;
    
if (subtotalEl) subtotalEl.textContent = '₱' + Math.round(subtotal).toLocaleString('en-PH');
    if (totalEl) totalEl.textContent = '₱' + Math.round(total).toLocaleString('en-PH');
    
    // Show modal - keep cart items preserved until order is actually placed
    document.getElementById('orderConfirmOverlay').classList.add('open');
  };

// Place Order button
  document.getElementById('orderConfirmClose').addEventListener('click', () => {
    document.getElementById('orderConfirmOverlay').classList.remove('open');
    
    // Clear cart after successful order
    cart = [];
    window.updateCartCount();
    renderCart();
    
    // Close cart immediately
    window.closeCart();
    
    // Show the order tracking bubble
    window.showOrderTrackBubble();
    
    // Then show top notification bar (outside cart)
    window.showTopNotif('Order Successful!', 'success');
  });
  
  document.getElementById('orderConfirmCancelBtn').addEventListener('click', () => {
    document.getElementById('orderConfirmOverlay').classList.remove('open');
  });
  
  document.getElementById('orderConfirmOverlay').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
      document.getElementById('orderConfirmOverlay').classList.remove('open');
    }
  });

  // Auth modal
  const authModal = document.getElementById('authModal');
  if (authModal) {
    authModal.addEventListener('click', e => {
      if (e.target === authModal) closeAuthModal();
    });
  }
});

function goToSignIn() {
  window.location.href = 'account.html';
}

function closeAuthModal() {
  document.getElementById('authModal').classList.remove('open');
} 