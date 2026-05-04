// FIXED: Map works for ALL payments + Landscape layout + Validation

'use strict';

// Cart storage
let cart = JSON.parse(localStorage.getItem('cart')) || [];
const SHIPPING = 50;

console.log('carT.js loaded - cart:', cart.length, 'items');

// Price formatter
function fmt(n) {
  return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Update cart count badges
window.updateCartCount = function() {
  const badges = document.querySelectorAll('.cart-count');
  const total = cart.reduce((sum, item) => sum + item.quantity, 0);
  badges.forEach(badge => {
    badge.textContent = total;
    // Pulsating effect on add/ordering
    badge.classList.add('pulse');
    setTimeout(() => badge.classList.remove('pulse'), 400);
  });
  localStorage.setItem('cart', JSON.stringify(cart));
};

// Add item to cart (global for menu.js)
window.addItemToCart = window.addToCart = function(itemData, qty = 1, variation = null) {
  if (!itemData) return;

  const price = typeof itemData.price === 'string' 
    ? parseFloat(itemData.price.replace(/[₱,]/g, '')) 
    : itemData.rawPrice || parseFloat(itemData.price) || 0;

  const existing = cart.find(item => item.name === itemData.name && item.variation === variation);
  if (existing) {
    existing.quantity += qty;
  } else {
    cart.push({
      name: itemData.name,
      price: fmt(price),
      rawPrice: price,
      pieces: itemData.pieces || null,
      variation: variation || null,
      quantity: qty,
      image: itemData.image || ''
    });
  }
  window.updateCartCount();
  console.log('Added to cart:', itemData.name);
};

// 🌍 FREE LEAFLET MAP LOCATION SELECTOR
let leafletLoaded = false;
let mapModal = null;
let map = null;
let marker = null;

async function initLeafletMap() {
  if (leafletLoaded) {
    // If it's already loaded, just recreate or show the modal
    if (!document.getElementById('mapModalOverlay')) {
      initMapModal();
    } else {
      document.getElementById('mapModalOverlay').style.display = 'flex';
    }
    return;
  }
  
  try {
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);
    
    const js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = initMapModal;
    document.head.appendChild(js);
    
    leafletLoaded = true;
    console.log('✅ Leaflet loaded!');
  } catch (e) {
    console.error('Leaflet load failed:', e);
  }
}


function initMapModal() {
  // Create map modal HTML
  const modalHTML = `
    <div id="mapModalOverlay" class="leaflet-map-overlay" style="
      position: fixed; inset: 0; z-index: 10001; 
      background: rgba(0,0,0,0.85); backdrop-filter: blur(12px);
      display: flex; align-items: center; justify-content: center; padding: 24px;
    ">
      <div style="
        background: #222; border-radius: 18px; width: 92%; max-width: 850px; max-height: 75vh; aspect-ratio: 16/9;
        position: relative; box-shadow: 0 24px 60px rgba(0,0,0,0.5);
      ">
        <div style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-family: 'Aclonica', sans-serif; color: #fff; font-size: 1.2rem;">
              📍 Select Delivery Location
            </h3>
            <button id="closeMapBtn" style="
              background: rgba(194,38,38,0.5); border: none; border-radius: 50%; 
              width: 36px; height: 36px; color: #fff; font-size: 1rem; cursor: pointer;
            ">×</button>
          </div>
          <input id="searchInput" placeholder="Search address (e.g. BGC Taguig)" 
                 style="
            width: 100%; padding: 12px 16px; margin-top: 12px; border-radius: 8px; 
            border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.5);
            color: #fff; font-size: 0.95rem; box-sizing: border-box;
          ">
        </div>
        <div id="mapContainer" style="height: 55%; width: 100%; border-radius: 0 0 18px 18px;"></div>
        <div style="padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1);">
          <button id="confirmLocationBtn" class="confirm-location-btn" disabled style="
            width: 100%; padding: 12px; background: rgba(194,38,38,0.6); 
            border: none; border-radius: 10px; color: #fff; font-weight: 700;
            font-size: 0.95rem; cursor: not-allowed; transition: all 0.2s;
          ">
            Confirm Location
          </button>
          <div id="selectedAddress" style="margin-top: 12px; font-size: 0.85rem; color: rgba(255,255,255,0.7);"></div>
        </div>
      </div>
    </div>
  `;

  
  document.body.insertAdjacentHTML('beforeend', modalHTML);
  
  // Event listeners
  document.getElementById('closeMapBtn').onclick = closeMapModal;
  document.getElementById('confirmLocationBtn').onclick = confirmLocation;
  document.getElementById('mapModalOverlay').onclick = (e) => {
    if (e.target.id === 'mapModalOverlay') closeMapModal();
  };
  
  initMap();
}

function initMap() {
  // Manila center (Taguig area)
  const defaultPos = [14.5995, 120.9842];
  
  map = L.map('mapContainer').setView(defaultPos, 13);
  
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);
  
  marker = L.marker(defaultPos).addTo(map);
  
  // Search functionality
  const searchInput = document.getElementById('searchInput');
  searchInput.addEventListener('keypress', async (e) => {
    if (e.key === 'Enter') {
      await searchAddress(searchInput.value);
    }
  });
  
  searchInput.addEventListener('input', debounce(searchAddress, 500));
}

async function searchAddress(query) {
  try {
    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Taguig, Philippines')}&limit=5&countrycodes=PH&addressdetails=1`);
    const results = await response.json();
    
    if (results.length > 0) {
      const result = results[0];
      const lat = parseFloat(result.lat);
      const lon = parseFloat(result.lon);
      
      map.setView([lat, lon], 16);
      marker.setLatLng([lat, lon]);
      
      const address = result.display_name.split(', Philippines')[0];
      document.getElementById('selectedAddress').textContent = address;
      document.getElementById('confirmLocationBtn').disabled = false;
      document.getElementById('confirmLocationBtn').style.background = 'linear-gradient(135deg, #C22626, #8B0A1E)';
      document.getElementById('confirmLocationBtn').style.cursor = 'pointer';
    }
  } catch (e) {
    console.error('Search failed:', e);
  }
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function closeMapModal() {
  const overlay = document.getElementById('mapModalOverlay');
  if (overlay) overlay.remove();
  map?.remove();
}

function confirmLocation() {
  const address = document.getElementById('selectedAddress').textContent;
  const addressField = document.getElementById('cartAddress');
  if (addressField) {
    addressField.value = address;
    addressField.dispatchEvent(new Event('input', { bubbles: true }));
    addressField.dispatchEvent(new Event('change', { bubbles: true }));
  }
  
  closeMapModal();
  console.log('📍 Leaflet address confirmed:', address);
}

// Render cart items (unchanged)
function renderCart() {
  const list = document.getElementById('cartItemsList');
  const empty = document.getElementById('cartEmpty');
  const subtotalEl = document.getElementById('cartSubtotal');
  const totalEl = document.getElementById('cartTotal');
  const checkoutEl = document.getElementById('checkoutTotal');
  const subheadEl = document.getElementById('cartSubheading');

  if (!list) return console.error('cartItemsList not found');

  list.innerHTML = '';

  if (cart.length === 0) {
    if (empty) empty.classList.add('show');
    list.style.display = 'none';
    if (subtotalEl) subtotalEl.textContent = '₱0';
    if (subheadEl) subheadEl.textContent = 'Your cart is empty';
  } else {
    if (empty) empty.classList.remove('show');
    list.style.display = 'flex';

    cart.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'cart-item-row';
      row.innerHTML = `
        <img src="${item.image}" alt="${item.name}" class="cart-item-img" onerror="this.style.background='#333';this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjUiIGN5PSIyNSIgcj0iMjUiIGZpbGw9IiMyMjIyMjIiLz4KPHRleHQgeD0iMjUiIHk9IjM0IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM1NTUiIHRleHQtYW5jaG9yPSJtaWRkbGUiPk5vIEltYWdlPC90ZXh0Pgo8L3N2Zz4K'">
        <div class="cart-item-info">
         <div class="cart-item-name">${item.name}</div>
         <div class="cart-item-meta">${item.pieces ? item.pieces + (item.variation ? ' · ' : '') : ''}${item.variation || ''}</div>
        </div>
        <div class="cart-item-qty">
         <span class="cart-qty-num">${item.quantity}</span>
         <div class="cart-qty-btn">
           <i class="fas fa-caret-up" onclick="updateQty(${idx}, 1)" title="Increase"></i>
           <i class="fas fa-caret-down" onclick="updateQty(${idx}, -1)" title="Decrease"></i>
         </div>
        </div>
        <div class="cart-item-prices">
         <div class="cart-item-price">${fmt(item.rawPrice * item.quantity)}</div>
<div class="cart-item-unit-price">${fmt(item.rawPrice)}</div>
        </div>
        <button class="cart-item-delete" onclick="removeItem(${idx})" title="Remove">
         <i class="far fa-trash-alt"></i>
        </button>
      `;
      list.appendChild(row);
    });

    const subtotal = cart.reduce((sum, item) => sum + (item.rawPrice * item.quantity), 0);
    const total = subtotal + SHIPPING;

    if (subtotalEl) subtotalEl.textContent = fmt(subtotal);
    if (totalEl) totalEl.textContent = fmt(total);
    if (checkoutEl) checkoutEl.textContent = fmt(total);
    if (subheadEl) {
      const count = cart.reduce((sum, item) => sum + item.quantity, 0);
      subheadEl.textContent = `You have ${count} item${count !== 1 ? 's' : ''} in your cart`;
    }
  }
}

// Rest of your functions unchanged...
function updateQty(idx, delta) {
  if (idx < 0 || idx >= cart.length) return;
  
  if (delta === -1 && cart[idx].quantity <= 1) {
    removeItem(idx);
    return;
  }
  
  cart[idx].quantity = Math.max(1, cart[idx].quantity + delta);
  renderCart();
  window.updateCartCount();
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
  if (pendingRemoveIdx !== null && pendingRemoveIdx < cart.length) {
    cart.splice(pendingRemoveIdx, 1);
    renderCart();
    window.updateCartCount();
  }
  hideRemoveConfirm();
}

function removeItem(idx) {
  showRemoveConfirm(idx);
}

window.openCart = function() {
  console.log('openCart called');
  const overlay = document.getElementById('cartOverlay');
  if (!overlay) return console.error('cartOverlay missing');
  
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  renderCart();
};

window.closeCart = function() {
  console.log('closeCart called');
  const overlay = document.getElementById('cartOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
};

// DOM Ready + FIXED validation
document.addEventListener('DOMContentLoaded', function() {
  // FIXED: Map button - works for ALL payments
  function setupMapBtn() {
    const mapBtn = document.getElementById('cartAddressIcon');
    if (mapBtn && !mapBtn.dataset.listenerAdded) {
      mapBtn.dataset.listenerAdded = 'true';
      mapBtn.addEventListener('click', function() {
        initLeafletMap();
        mapBtn.style.background = 'rgba(255, 255, 255, 0.3)';
        setTimeout(() => mapBtn.style.background = '', 200);
      });
    }
  }
  setupMapBtn();

  // All existing listeners...
  document.getElementById('confirmRemoveYes')?.addEventListener('click', confirmRemoveItem);
  document.getElementById('confirmRemoveNo')?.addEventListener('click', hideRemoveConfirm);

  const backBtn = document.getElementById('cartBackBtn');
  if (backBtn) {
    backBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.closeCart();
    });
  }

  // FIXED Checkout with payment validation
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', function() {
      if (cart.length === 0) {
        alert('Cart empty!');
        return;
      }
      
      const address = document.getElementById('cartAddress')?.value.trim();
      if (!address) {
        alert('Please select or enter your address!');
        return;
      }

      const activePayment = document.querySelector('.payment-method-btn.active')?.dataset.method;
      if (!activePayment) {
        alert('Please select a payment method!');
        return;
      }

      // Validate per payment
      let valid = true;
      if (activePayment === 'card') {
        const name = document.getElementById('cardName')?.value.trim() || '';
        const number = (document.getElementById('cardNumber')?.value || '').replace(/\s/g,'').length;
        const expiry = document.getElementById('cardExpiry')?.value.trim() || '';
        const cvv = document.getElementById('cardCvv')?.value.trim() || '';
        if (!name || number < 13 || !expiry || cvv.length < 3) valid = false;
      } else if (activePayment === 'cod') {
        const name = document.getElementById('codName')?.value.trim() || '';
        const mobile = document.getElementById('codMobile')?.value.trim() || '';
        if (!name || mobile.length < 10) valid = false;
      } else if (activePayment === 'gcash') {
        const ref = document.getElementById('gcashRef')?.value.trim() || '';
        const mobile = document.getElementById('gcashNumber')?.value.trim() || '';
        if (!ref || mobile.length < 10) valid = false;
      }

      if (!valid) {
        alert(`Please complete ${activePayment.toUpperCase()} payment details!`);
        return;
      }
      
      alert(`✅ Order placed!\nAddress: ${address}\nPayment: ${activePayment.toUpperCase()}`);
    });
  }

  // Payment toggle
  document.querySelectorAll('.payment-method-btn')?.forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      
      const method = btn.dataset.method;
      ['cardFields', 'codFields', 'gcashFields'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = id.slice(0,-6).toLowerCase() === method ? 'block' : 'none';
      });
      
      // Re-setup map btn after toggle
      setTimeout(setupMapBtn, 100);
    });
  });

  // GCash QR Code Overlay Logic
  const viewQrBtn = document.getElementById('viewQrBtn');
  const qrOverlay = document.getElementById('qrOverlay');
  const qrClose = document.getElementById('qrClose');

  if (viewQrBtn && qrOverlay) {
    viewQrBtn.addEventListener('click', () => {
      qrOverlay.classList.add('open');
    });
  }

  if (qrClose && qrOverlay) {
    qrClose.addEventListener('click', () => {
      qrOverlay.classList.remove('open');
    });
  }

  if (qrOverlay) {
    qrOverlay.addEventListener('click', (e) => {
      if (e.target === qrOverlay) {
        qrOverlay.classList.remove('open');
      }
    });
  }

  window.updateCartCount();
  console.log('✅ Map + Payments FIXED for all methods!');
});

// GCash QR Code Overlay Logic - FIXED z-index issue
document.addEventListener('click', function(e) {
  const viewQrBtn = document.getElementById('viewQrBtn');
  const qrOverlay = document.getElementById('qrOverlay');
  const qrClose = document.getElementById('qrClose');
  
  if (e.target === viewQrBtn && qrOverlay) {
    qrOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  
  if (qrClose && e.target === qrClose) {
    qrOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }
  
  if (qrOverlay && e.target === qrOverlay) {
    qrOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }
});


