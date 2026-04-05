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
        <div class="cart-item-price">₱${(item.rawPrice * item.quantity).toLocaleString('en-PH')}</div>
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
  if (subhead) subhead.textContent = `You have ${cart.reduce((s, i) => s + i.quantity, 0)} item${cart.reduce((s, i) => s + i.quantity, 0) !== 1 ? 's' : ''} in your cart`;
}

function updateQty(idx, delta) {
  cart[idx].quantity += delta;
  if (cart[idx].quantity <= 0) cart.splice(idx, 1);
  renderCart();
  window.updateCartCount();
}

function removeItem(idx) {
  cart.splice(idx, 1);
  renderCart();
  window.updateCartCount();
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
      document.getElementById('cardFields').style.display = isGcash ? 'none' : 'block';
      document.getElementById('gcashFields').style.display = isGcash ? 'block' : 'none';
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

  // Checkout
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
      if (cart.length === 0) {
        alert('Your cart is empty!');
        return;
      }
      const address = document.getElementById('cartAddress').value.trim();
      if (!address) {
        document.getElementById('cartAddress').focus();
        return;
      }
      alert('Order placed! (Demo)');
      // Clear cart
      cart = [];
      window.updateCartCount();
      renderCart();
      window.closeCart();
    });
  }

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
