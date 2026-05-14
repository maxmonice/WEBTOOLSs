'use strict';

// ─── Cart Storage ────────────────────────────────────────────────────────────
let cart = JSON.parse(localStorage.getItem('cart')) || [];
const SHIPPING = 50;

console.log('carT.js loaded — cart:', cart.length, 'items');

window.__cartSessionOk = false;

// ─── Init Xendit ─────────────────────────────────────────────────────────────
if (window.Xendit && window.XENDIT_PUBLIC_KEY) {
    Xendit.setPublishableKey(window.XENDIT_PUBLIC_KEY);
    console.log('✅ Xendit initialized');
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function fmt(n) {
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getActivePromo() {
    const discount = parseFloat(localStorage.getItem('promo_discount') || '0');
    const category = (localStorage.getItem('promo_category') || 'All Items').toLowerCase();
    const code = localStorage.getItem('active_promo') || '';
    if (!discount || discount <= 0) return null;
    return { discount, category, code };
}

function promoAppliesTo(itemData) {
    const promo = getActivePromo();
    if (!promo) return false;
    const itemCategory = String(itemData.category || itemData.applicable_category || '').toLowerCase();
    return ['all items', 'all menu', 'all'].includes(promo.category) || itemCategory.includes(promo.category);
}

function applyPromoPrice(itemData, price) {
    const promo = getActivePromo();
    if (!promo || !promoAppliesTo(itemData)) {
        return { price, originalPrice: null, discountPercent: 0, promoCode: '' };
    }
    return {
        price: price * (1 - (promo.discount / 100)),
        originalPrice: price,
        discountPercent: promo.discount,
        promoCode: promo.code
    };
}
window.applyPromoPrice = applyPromoPrice;

function refreshCartPromoPrices() {
    let changed = false;
    cart.forEach(item => {
        const basePrice = Number(item.originalPrice || item.rawPrice || 0);
        const promoPrice = applyPromoPrice(item, basePrice);
        if (Math.abs((item.rawPrice || 0) - promoPrice.price) > 0.001 || (item.discountPercent || 0) !== promoPrice.discountPercent) {
            item.rawPrice = promoPrice.price;
            item.price = fmt(promoPrice.price);
            item.originalPrice = promoPrice.originalPrice;
            item.discountPercent = promoPrice.discountPercent;
            item.promoCode = promoPrice.promoCode;
            changed = true;
        }
    });
    if (changed) localStorage.setItem('cart', JSON.stringify(cart));
}

function lockScroll(lock) {
    document.body.style.overflow = lock ? 'hidden' : '';
}

// ─── Cart Count ──────────────────────────────────────────────────────────────
window.updateCartCount = function () {
    const badges = document.querySelectorAll('.cart-count');
    const total = cart.reduce((sum, item) => sum + item.quantity, 0);
    badges.forEach(badge => {
        badge.textContent = total;
        badge.classList.add('pulse');
        setTimeout(() => badge.classList.remove('pulse'), 400);
    });
    localStorage.setItem('cart', JSON.stringify(cart));
};

// ─── Add to Cart ─────────────────────────────────────────────────────────────
window.addItemToCart = window.addToCart = function (itemData, qty = 1, variation = null) {
    if (!itemData) return;
    const basePrice = Number(itemData.rawPrice) || (typeof itemData.price === 'string'
        ? parseFloat(itemData.price.replace(/[₱,]/g, ''))
        : parseFloat(itemData.price) || 0);
    const promoPrice = applyPromoPrice(itemData, basePrice);
    const price = promoPrice.price;

    const existing = cart.find(item => item.name === itemData.name && item.variation === variation);
    if (existing) {
        existing.quantity += qty;
        existing.rawPrice = price;
        existing.price = fmt(price);
        existing.originalPrice = promoPrice.originalPrice;
        existing.discountPercent = promoPrice.discountPercent;
        existing.promoCode = promoPrice.promoCode;
        existing.category = itemData.category || existing.category || null;
    } else {
        cart.push({
            name: itemData.name,
            price: fmt(price),
            rawPrice: price,
            originalPrice: promoPrice.originalPrice,
            discountPercent: promoPrice.discountPercent,
            promoCode: promoPrice.promoCode,
            category: itemData.category || null,
            pieces: itemData.pieces || null,
            variation: variation || null,
            quantity: qty,
            image: itemData.image || ''
        });
    }
    window.updateCartCount();
    showTopNotif(`"${itemData.name}" added to cart!`, 'success');
};

// ─── Leaflet Map ─────────────────────────────────────────────────────────────
let leafletLoaded = false;
let map = null;
let marker = null;
let deliveryCircle = null;

const STORE_LOC = { lat: 14.5244, lng: 121.0559 }; // Vulcan St, Pinagsama, Taguig
const MAX_RADIUS_KM = 5.5;

async function initLeafletMap() {
    if (!leafletLoaded) {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);

        await new Promise((resolve, reject) => {
            const js = document.createElement('script');
            js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            js.onload = resolve;
            js.onerror = reject;
            document.head.appendChild(js);
        });
        leafletLoaded = true;
    }
    // Remove previous modal if any
    document.getElementById('mapModalOverlay')?.remove();
    map?.remove();
    initMapModal();
}

function initMapModal() {
    marker = null;
    const modalHTML = `
    <div id="mapModalOverlay" style="
        position:fixed;inset:0;background:rgba(0,0,0,0.9);
        display:flex;align-items:center;justify-content:center;z-index:9999;">
      <div id="mapModalContent" style="
          background:#111;border-radius:15px;width:95%;max-width:1000px;
          position:relative;box-shadow:0 0 50px rgba(0,0,0,1);
          display:flex;flex-direction:column;max-height:90vh;overflow:hidden;
          border: 1px solid #333;">
        
        <div style="padding:15px 25px;background:linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-family:'Aclonica',sans-serif;color:#fff;font-size:1.2rem;">
              📍 Select Delivery Location
            </h3>
            <button id="closeMapBtn" style="
                background:rgba(0,0,0,0.3);border:none;border-radius:50%;
                width:32px;height:32px;color:#fff;font-size:0.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-x" style="color: rgb(255, 255, 255);"></i>
            </button>
        </div>

        <div style="padding:15px 25px;background:#222;border-bottom:1px solid #333;">
          <div style="position:relative;">
            <div style="display:flex;gap:10px;">
                <div style="position:relative;flex:1;">
                    <i class="fas fa-map-marker-alt" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#C22626;font-size:0.9rem;"></i>
                    <input id="mapSearchInput" placeholder="Search for your street or city..."
                        style="width:100%;padding:12px 16px 12px 35px;border-radius:8px;
                               border:1px solid #444;background:#111;
                               color:#fff;font-size:.95rem;box-sizing:border-box;outline:none;">
                </div>
                <button id="gpsLocationBtn" title="Use my current GPS" style="
                    width:48px;background:linear-gradient(135deg,#C22626,#8B0A1E);
                    border:none;border-radius:8px;color:#fff;cursor:pointer;
                    transition:all .2s;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-crosshairs"></i>
                </button>
            </div>
            <div id="mapSearchResults" style="display:none;position:absolute;top:100%;left:0;right:58px;
                background:#222;border:1px solid #444;border-radius:0 0 8px 8px;
                max-height:200px;overflow-y:auto;z-index:10000;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
          </div>
        </div>

        <div id="mapContainer" style="flex:1;min-height:400px;width:100%;"></div>

        <div style="padding:20px 25px;background:#111;border-top:1px solid #333;">
          <button id="confirmLocationBtn" disabled style="
              width:100%;padding:14px;background:#333;
              border:none;border-radius:10px;color:#777;
              font-weight:700;font-size:1rem;cursor:not-allowed;transition:all .2s;">
            Confirm Location
          </button>
        </div>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    document.getElementById('closeMapBtn').onclick = closeMapModal;
    document.getElementById('mapModalOverlay').onclick = e => {
        if (e.target.id === 'mapModalOverlay') closeMapModal();
    };
    document.getElementById('confirmLocationBtn').onclick = confirmLocation;

    const gpsBtn = document.getElementById('gpsLocationBtn');
    gpsBtn.onclick = async () => {
        gpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };

        navigator.geolocation.getCurrentPosition(async pos => {
            try {
                const { latitude: lat, longitude: lng } = pos.coords;
                map.setView([lat, lng], 17);
                if (marker) marker.setLatLng([lat, lng]);
                else marker = L.marker([lat, lng]).addTo(map);
                await updateSelectedAddress(lat, lng);
            } catch (err) {
                console.error('GPS update failed', err);
            } finally {
                gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
            }
        }, err => {
            let msg = 'Location Error: ';
            if (err.code === 1) msg += 'PERMISSION_DENIED. Please click the Padlock/Info icon in your address bar and set Location to ALLOW.';
            else if (err.code === 2) msg += 'POSITION_UNAVAILABLE. Your device cannot find a signal.';
            else if (err.code === 3) msg += 'TIMEOUT. It took too long to find you.';
            else msg += err.message;

            alert(msg);
            gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
        }, options);
    };

    function getDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius of earth in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    async function updateSelectedAddress(lat, lng) {
        const btn = document.getElementById('confirmLocationBtn');
        const searchInput = document.getElementById('mapSearchInput');
        if (!btn) return;

        // Use placeholder for loading state so it's not editable
        if (searchInput) {
            searchInput.value = '';
            searchInput.placeholder = 'Locating address...';
        }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

        try {
            const dist = getDistance(STORE_LOC.lat, STORE_LOC.lng, lat, lng);
            const isOutside = dist > MAX_RADIUS_KM;

            const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
            const res = await fetch(`https://us1.locationiq.com/v1/reverse?key=${token}&lat=${lat}&lon=${lng}&format=json`);
            const data = await res.json();
            let addr = data.display_name || '';


            if (!addr) addr = 'Unknown Street - Please refine pin';

            if (searchInput) {
                searchInput.value = addr;
                searchInput.placeholder = 'Search for street, city, or venue...';
            }
            btn._address = addr;

            if (isOutside) {
                btn.disabled = true;
                btn.style.background = '#444';
                btn.style.color = '#888';
                btn.style.cursor = 'not-allowed';
                btn.style.boxShadow = 'none';
                btn.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Too Far (${dist.toFixed(1)}km)`;
            } else {
                btn.disabled = false;
                btn.style.background = 'linear-gradient(135deg,#C22626,#8B0A1E)';
                btn.style.color = '#fff';
                btn.style.cursor = 'pointer';
                btn.style.borderColor = 'transparent';
                btn.style.boxShadow = '0 8px 20px rgba(194,38,38,0.3)';
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Selection';
            }
        } catch (e) {
            console.error('Reverse geocode failed', e);
            if (searchInput) {
                searchInput.value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                searchInput.placeholder = 'Search for street, city, or venue...';
            }
            btn.disabled = false;
            btn.innerHTML = 'Confirm Selection';
        }
    }

    // Init map after modal 
    setTimeout(() => {
        map = L.map('mapContainer').setView([STORE_LOC.lat, STORE_LOC.lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // 1. Store location pin
        L.marker([STORE_LOC.lat, STORE_LOC.lng], {
            icon: L.divIcon({
                className: 'store-marker',
                html: '<div style="background:#C22626;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 0 10px rgba(0,0,0,0.5);"><i class="fas fa-store" style="color:#fff;font-size:12px;"></i></div>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(map).bindPopup('<b>Our Store</b><br>Vulcan St, Taguig');

        // 2. Delivery Radius Circle
        deliveryCircle = L.circle([STORE_LOC.lat, STORE_LOC.lng], {
            color: '#C22626',
            fillColor: '#C22626',
            fillOpacity: 0.1,
            radius: MAX_RADIUS_KM * 1000 // meters
        }).addTo(map);

        // 3. LocationIQ Geocoder Control (Autocomplete)
        const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
        if (typeof L.Control.geocoder === 'function') {
            const geocoder = L.Control.geocoder(token, {
                placeholder: "Search for your street in Taguig...",
                expanded: true,
                position: 'topright'
            }).addTo(map);

            geocoder.on('select', function(e) {
                const { lat, lng } = e.latlng;
                const addr = e.feature.name;
                map.setView([lat, lng], 17);
                if (marker) marker.setLatLng([lat, lng]);
                else marker = L.marker([lat, lng]).addTo(map);
                const searchInput = document.getElementById('mapSearchInput');
                if (searchInput) searchInput.value = addr;
                updateSelectedAddress(lat, lng);
            });
        } else {
            console.warn('LocationIQ Geocoder library not loaded yet.');
        }


        // Click to pin
        map.on('click', async function (e) {
            const { lat, lng } = e.latlng;
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng]).addTo(map);
            await updateSelectedAddress(lat, lng);
        });

        // Search Suggestions (Photon API)
        const searchInput = document.getElementById('mapSearchInput');
        const resultsBox = document.getElementById('mapSearchResults');

        searchInput.addEventListener('input', debounce(async () => {
            const query = searchInput.value.trim();
            if (query.length < 3) {
                resultsBox.style.display = 'none';
                return;
            }

            try {
                const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
                // LocationIQ Autocomplete API
                const res = await fetch(`https://us1.locationiq.com/v1/autocomplete.php?key=${token}&q=${encodeURIComponent(query)}&limit=5&lat=${STORE_LOC.lat}&lon=${STORE_LOC.lng}&tag=place:city,place:town,place:village,place:suburb,place:neighbourhood`);
                const data = await res.json();
                
                if (Array.isArray(data) && data.length > 0) {
                    resultsBox.innerHTML = data.map(f => {
                        const name = f.display_name;
                        return `
                            <div class="map-suggestion-item" data-lat="${f.lat}" data-lon="${f.lon}" data-addr="${name}" style="
                                padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);
                                color:#ccc;font-size:0.85rem;cursor:pointer;transition:all 0.2s;">
                                <i class="fas fa-map-marker-alt" style="margin-right:8px;color:#C22626;"></i>
                                ${name}
                            </div>
                        `;
                    }).join('');
                    resultsBox.style.display = 'block';

                    resultsBox.querySelectorAll('.map-suggestion-item').forEach(item => {
                        item.onclick = () => {
                            const lat = +item.dataset.lat;
                            const lon = +item.dataset.lon;
                            const addr = item.dataset.addr;

                            map.setView([lat, lon], 17);
                            if (marker) marker.setLatLng([lat, lon]);
                            else marker = L.marker([lat, lon]).addTo(map);

                            searchInput.value = addr;
                            updateSelectedAddress(lat, lon);
                            resultsBox.style.display = 'none';
                        };
                    });
                } else {
                    resultsBox.style.display = 'none';
                }
            } catch (e) { console.error('LocationIQ search failed', e); }

        }, 400));

        // Handle Enter key (Photon)
        searchInput.addEventListener('keypress', async e => {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query.length < 3) return;

                try {
                    const res = await fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=1&lat=14.55&lon=121.02`);
                    const data = await res.json();
                    if (data.features && data.features.length > 0) {
                        const f = data.features[0];
                        const lat = f.geometry.coordinates[1];
                        const lon = f.geometry.coordinates[0];
                        const p = f.properties;
                        const addr = [p.name, p.street, p.city].filter(Boolean).join(', ');

                        map.setView([lat, lon], 17);
                        if (marker) marker.setLatLng([lat, lon]);
                        else marker = L.marker([lat, lon]).addTo(map);

                        searchInput.value = addr;
                        updateSelectedAddress(lat, lon);
                        resultsBox.style.display = 'none';
                    }
                } catch (err) { console.error('Search failed', err); }
            }
        });

        // Hide results when clicking away
        document.addEventListener('click', e => {
            if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                resultsBox.style.display = 'none';
            }
        });

        map.invalidateSize();
    }, 100);
}

function debounce(func, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => func(...args), wait); };
}

// Validate a manually typed address:
// 1) Resolve it to coordinates via Photon (geocoding).
// 2) Ensure the resolved point is within the store radius.
// Returns: { ok: boolean, message: string }
async function validateAddressWithinRadius(address) {
    const trimmed = (address || '').trim();
    if (!trimmed) return { ok: false, message: 'Please select or enter your delivery address.' };

    // Too short -> likely incomplete
    if (trimmed.length < 8) {
        return { ok: false, message: 'Please enter a more complete delivery address.' };
    }

    try {
        // Photon geocode search near Taguig.
        // NOTE: Photon expects q=... and returns features with geometry.coordinates [lon, lat]
        // LocationIQ geocode search
        const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
        const res = await fetch(`https://us1.locationiq.com/v1/search?key=${token}&q=${encodeURIComponent(trimmed)}&format=json&limit=1`);
        const data = await res.json();

        const f = data && data.length ? data[0] : null;
        if (!f || !f.lat || !f.lon) {
            return { ok: false, message: 'Address not recognized. Please use the map to select a valid location.' };
        }

        const lon = parseFloat(f.lon);
        const lat = parseFloat(f.lat);


        // Distance check
        const R = 6371;
        const dLat = (lat - STORE_LOC.lat) * Math.PI / 180;
        const dLon = (lon - STORE_LOC.lng) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(STORE_LOC.lat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const distKm = R * c;

        if (distKm > MAX_RADIUS_KM) {
            return { ok: false, message: `Delivery is available within ${MAX_RADIUS_KM}km of our store. Your address is ${distKm.toFixed(1)}km away.` };
        }

        return { ok: true };
    } catch (e) {
        // If lookup fails, don’t block hard—just prompt user to use map.
        return { ok: false, message: 'Unable to verify this address. Please select your delivery location using the map.' };
    }
}


function closeMapModal() {
    document.getElementById('mapModalOverlay')?.remove();
    map?.remove();
    map = null;
}

function confirmLocation() {
    const btn = document.getElementById('confirmLocationBtn');
    const searchInput = document.getElementById('mapSearchInput');
    const addr = btn._address || searchInput?.value;
    const addressField = document.getElementById('cartAddress');
    if (addressField && addr) {
        addressField.value = addr;
    }

    // ✅ Store the actual coordinates for the backend
    if (marker) {
        const pos = marker.getLatLng();
        window.selectedLat = pos.lat;
        window.selectedLng = pos.lng;
        console.log('📍 Location confirmed:', window.selectedLat, window.selectedLng);
    }

    closeMapModal();
}

// ─── Render Cart ─────────────────────────────────────────────────────────────
function renderCart() {
    const list = document.getElementById('cartItemsList');
    const empty = document.getElementById('cartEmpty');
    const subtotalEl = document.getElementById('cartSubtotal');
    const totalEl = document.getElementById('cartTotal');
    const checkoutEl = document.getElementById('checkoutTotal');
    const subheadEl = document.getElementById('cartSubheading');
    if (!list) return;
    refreshCartPromoPrices();
    list.innerHTML = '';

    if (cart.length === 0) {
        empty?.classList.add('show');
        list.style.display = 'none';
        if (subtotalEl) subtotalEl.textContent = '₱0';
        if (subheadEl) subheadEl.textContent = 'Your cart is empty';
    } else {
        empty?.classList.remove('show');
        list.style.display = 'flex';

        cart.forEach((item, idx) => {
            const row = document.createElement('div');
            row.className = 'cart-item-row';
            row.innerHTML = `
              <img src="${item.image}" alt="${item.name}" class="cart-item-img"
                onerror="this.style.background='#333';this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIyNSIgY3k9IjI1IiByPSIyNSIgZmlsbD0iIzIyMjIyMiIvPjx0ZXh0IHg9IjI1IiB5PSIzNCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNTU1IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5ObyBJbWc8L3RleHQ+PC9zdmc+'">
              <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-meta">${item.pieces ? item.pieces + (item.variation ? ' · ' : '') : ''}${item.variation || ''}</div>
              </div>
              <div class="cart-item-qty">
                <span class="cart-qty-num">${item.quantity}</span>
                <div class="cart-qty-btn">
                  <i class="fas fa-caret-up" onclick="updateQty(${idx},1)" title="Increase"></i>
                  <i class="fas fa-caret-down" onclick="updateQty(${idx},-1)" title="Decrease"></i>
                </div>
              </div>
              <div class="cart-item-prices">
                <div class="cart-item-price">${fmt(item.rawPrice * item.quantity)}</div>
                <div class="cart-item-unit-price">${item.originalPrice ? `<span style="text-decoration:line-through;color:rgba(255,255,255,0.35);margin-right:5px;">${fmt(item.originalPrice)}</span>` : ''}${fmt(item.rawPrice)}</div>
                ${item.discountPercent ? `<div class="cart-item-unit-price" style="color:#22c55e;">${item.discountPercent}% promo applied</div>` : ''}
              </div>
              <button class="cart-item-delete" onclick="removeItem(${idx})" title="Remove">
                <i class="far fa-trash-alt"></i>
              </button>`;
            list.appendChild(row);
        });

        const subtotal = cart.reduce((s, i) => s + i.rawPrice * i.quantity, 0);
        const vat = subtotal * 0.12;
        const total = subtotal + vat + SHIPPING;

        const vatEl = document.getElementById('cartTax');
        if (vatEl) vatEl.textContent = fmt(vat);

        if (subtotalEl) subtotalEl.textContent = fmt(subtotal);
        if (totalEl) totalEl.textContent = fmt(total);
        if (checkoutEl) checkoutEl.textContent = fmt(total);
        if (subheadEl) {
            const count = cart.reduce((s, i) => s + i.quantity, 0);
            subheadEl.textContent = `You have ${count} item${count !== 1 ? 's' : ''} in your cart`;
        }
    }
}

// ─── Order Confirm Modal ──────────────────────────────────────────────────────
window.showOrderConfirm = function () {
    const overlay = document.getElementById('orderConfirmOverlay');
    const summary = document.getElementById('orderConfirmSummary');
    if (!overlay || !summary) return;

    summary.innerHTML = '';
    cart.forEach(item => {
        const div = document.createElement('div');
        div.className = 'order-confirm-item';
        div.innerHTML = `
          <div class="order-confirm-item-info">
            <img src="${item.image || ''}" class="order-confirm-item-img" onerror="this.style.background='#333'">
            <div class="order-confirm-item-name">${item.name}</div>
            <span class="order-confirm-item-qty">x${item.quantity}</span>
          </div>
          <span class="order-confirm-item-price">${fmt(item.rawPrice * item.quantity)}</span>`;
        summary.appendChild(div);
    });

    const subtotal = cart.reduce((s, i) => s + i.rawPrice * i.quantity, 0);
    const vat = subtotal * 0.12;
    const total = subtotal + vat + SHIPPING;

    document.getElementById('orderSubtotal').textContent = fmt(subtotal);
    const orderVatEl = document.getElementById('orderVat');
    if (orderVatEl) orderVatEl.textContent = fmt(vat);
    document.getElementById('orderTotal').textContent = fmt(total);

    const method = document.querySelector('.payment-method-btn.active')?.dataset.method || 'card';
    const labels = { card: 'Credit / Debit Card', cod: 'Cash on Delivery', gcash: 'GCash' };
    const payText = document.getElementById('orderConfirmPayText');
    if (payText) payText.textContent = labels[method] || method.toUpperCase();

    overlay.classList.add('open');
    lockScroll(true);
};

window.hideOrderConfirm = function (keepLocked = false) {
    document.getElementById('orderConfirmOverlay')?.classList.remove('open');
    if (!keepLocked) lockScroll(false);
};

// ─── XENDIT SEAMLESS CARD TOKENIZATION ───────────────────────────────────────
async function tokenizeCard(total, payerEmail) {
    const cardName = document.getElementById('card_name').value.trim();
    const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
    const cardExpiry = document.getElementById('card_expiry').value.split('/');
    const cardCvv = document.getElementById('card_cvv').value.trim();
    const cardMobile = document.getElementById('card_mobile').value.trim();

    if (!cardName || !cardNumber || cardExpiry.length !== 2 || !cardCvv) {
        showTopNotif('Please fill in all card details correctly.', 'error');
        return null;
    }

    setPlaceBtnState('Verifying card…', true);

    const nameParts = cardName.split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '-';

    return new Promise((resolve) => {
        Xendit.card.createToken({
            amount: total,
            card_number: cardNumber,
            card_exp_month: cardExpiry[0].trim(),
            card_exp_year: '20' + cardExpiry[1].trim(),
            card_cvn: cardCvv,
            card_holder_first_name: firstName || 'Customer',
            card_holder_last_name: (lastName !== '-') ? lastName : 'Name',
            card_holder_email: payerEmail || 'customer@lukes.com',
            is_multiple_use: false,
            should_authenticate: true
        }, (err, response) => {
            if (err) {
                showTopNotif('Card validation failed: ' + (err.message || 'Unknown error'), 'error');
                setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
                return resolve(null);
            }

            if (response.status === 'VERIFIED') {
                // ✅ Token ready — charge server-side
                resolve(response.id);
            } else if (response.status === 'IN_REVIEW' && response.payer_authentication_url) {
                // 3DS required — show inline iframe instead of redirecting away
                handle3DS(response.payer_authentication_url, resolve);
            } else {
                showTopNotif('Payment verification failed: ' + (response.status || 'Unknown'), 'error');
                setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
                resolve(null);
            }
        });
    });
}

function handle3DS(authUrl, resolve) {
    if (window.hideOrderConfirm) window.hideOrderConfirm(true); // Keep scroll locked
    const overlay = document.getElementById('xendit3DSOverlay');
    const frame = document.getElementById('threeDSFrame');
    const cancel = document.getElementById('threeDSCancelBtn');

    if (!overlay || !frame) return resolve(null);

    overlay.classList.add('open');
    frame.src = authUrl;

    const cleanup = () => {
        window.removeEventListener('message', on3DSMessage);
        if (cancel) cancel.onclick = null;
        overlay.classList.remove('open');
        setTimeout(() => { frame.src = 'about:blank'; }, 300);
    };

    if (cancel) {
        cancel.onclick = () => {
            cleanup();
            setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
            resolve(null);
        };
    }

    function on3DSMessage(e) {
        let data = e.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (err) { return; }
        }

        if (!data) return;

        // Xendit sends status via postMessage after 3DS
        if (data.status === 'VERIFIED' || data.token_id || data.id) {
            console.log('✅ 3DS Verified:', data);
            cleanup();
            resolve(data.token_id || data.id || null);
        } else if (data.status === 'FAILED') {
            console.log('❌ 3DS Failed:', data);
            cleanup();
            showTopNotif('3DS authentication failed. Please try again.', 'error');
            setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
            resolve(null);
        }
    }
    window.addEventListener('message', on3DSMessage);
}

function setPlaceBtnState(html, disabled) {
    const btn = document.getElementById('orderConfirmClose');
    if (!btn) return;
    btn.disabled = disabled;
    btn.innerHTML = html;
}

// ─── Submit Order ─────────────────────────────────────────────────────────────
window.submitOrder = async function () {
    const paymentMethod = document.querySelector('.payment-method-btn.active')?.dataset.method || '';
    const address = document.getElementById('cartAddress')?.value.trim() || '';
    const payerEmail = document.getElementById('xenditPayerEmail')?.value.trim() || '';
    const subtotal = cart.reduce((s, i) => s + (i.rawPrice || 0) * (i.quantity || 1), 0);
    const tax = subtotal * 0.12;
    const total = subtotal + tax + SHIPPING;

    const paymentDetails = {};
    ['codName', 'codMobile', 'card_mobile'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) paymentDetails[id] = el.value.trim();
    });

    const basePayload = {
        items: cart.map(item => ({
            name: item.name,
            price: Number(item.rawPrice || 0),
            quantity: Number(item.quantity || 1),
            variation: item.variation || null,
            pieces: item.pieces || null
        })),
        address,
        paymentDetails,
        subtotal,
        tax,
        shipping: SHIPPING,
        total,
        lat: window.selectedLat || null,
        lng: window.selectedLng || null
    };

    // ── CARD: tokenize on-site, charge server-side ──
    if (paymentMethod === 'card') {
        const tokenId = await tokenizeCard(total, payerEmail);
        if (!tokenId) return; // error already shown

        // 1. EXIT & SHOW SUCCESS UI IMMEDIATELY (Zero Delay)
        console.log('⚡ Verification done, showing success UI instantly...');
        onOrderSuccess(); // Triggers notif, bubble, and closes cart

        try {
            const res = await fetch('checkout-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    paymentMethod: 'card',
                    xenditToken: tokenId,
                    payerEmail,
                    ...basePayload
                })
            });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                console.log('✅ Backend order confirmed:', data.order_id);
                if (data.order_id) localStorage.setItem('order_id', String(data.order_id));
            } else {
                console.error('❌ Backend reported error (after UI success):', data.message);
            }
        } catch (e) {
            console.error('❌ Network error in background:', e);
        }
        return;
    }

    // ── GCASH: Seamless (Input Number in Cart) ──
    if (paymentMethod === 'gcash') {
        setPlaceBtnState('Initiating GCash…', true);
        const gcashMobile = document.getElementById('gcash_mobile')?.value.trim();
        if (!gcashMobile) {
            showTopNotif('Please enter your GCash mobile number.', 'error');
            setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
            return;
        }

        try {
            const res = await fetch('checkout-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    paymentMethod: 'gcash',
                    ...basePayload
                })
            });
            const data = await res.json().catch(() => ({}));

            if (data.success && data.invoice_url) {
                // Open the 3DS overlay but for GCash invoice
                handle3DS(data.invoice_url, (verifiedData) => {
                    // Only complete the order if we got a success signal
                    if (verifiedData) {
                        onOrderSuccess(data.order_id);
                    }
                });
            } else {
                showTopNotif(data.message || 'GCash setup failed. Please try again.', 'error');
                setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
            }
        } catch (e) {
            console.error('❌ GCash AJAX error:', e);
            showTopNotif('Network error while initiating GCash.', 'error');
            setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
        }
        return;
    }

    // ── COD: direct server call ──
    setPlaceBtnState('Placing order…', true);
    try {
        const res = await fetch('process-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create_order', paymentMethod: 'cod', ...basePayload })
        });
        const data = await res.json().catch(() => ({}));
        if (data.success) {
            onOrderSuccess(data.order_id);
        } else {
            showTopNotif(data.message || 'Failed to place order. Please try again.', 'error');
            setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
        }
    } catch (e) {
        showTopNotif('Network error while placing order. Please try again.', 'error');
        setPlaceBtnState('<i class="fas fa-check-circle"></i> Place Order', false);
    }
};

function onOrderSuccess(orderId = null) {
    console.log('🎉 Instant Success Triggered');

    // Clear cart state
    cart = [];
    localStorage.removeItem('cart');
    if (window.updateCartCount) window.updateCartCount();

    // 1. Force Close EVERYTHING immediately
    const overlays = ['xendit3DSOverlay', 'orderConfirmOverlay', 'cartOverlay', 'confirmRemoveOverlay'];
    overlays.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('open', 'active');
            if (id === 'cartOverlay') el.style.setProperty('display', 'none', 'important');
        }
    });

    lockScroll(false);

    // 2. Save order status
    localStorage.setItem('order_pending', 'true');
    if (orderId) localStorage.setItem('order_id', String(orderId));

    // 3. Show Notification (Instant)
    showTopNotif(`Order placed successfully!`, 'success');

    // 4. Trigger the Tracking Speech Bubble (Persistent)
    const bubble = document.getElementById('orderSpeechBubble') || document.getElementById('trackSpeechBubble');
    if (bubble) {
        bubble.classList.add('show');
    }
}

// ─── Top Notification ─────────────────────────────────────────────────────────
// ✅ FIX: was using .show / .active inconsistently — now always uses .active
function showTopNotif(message, type = 'success') {
    const notif = document.getElementById('topNotif');
    const notifText = document.getElementById('topNotifText');
    if (!notif || !notifText) return;

    // Premium Top-Center Design (Slim & Pill-shaped)
    notif.style.cssText = `
        position: fixed;
        top: 40px;
        left: 50%;
        transform: translate(-50%, -20px) scale(0.9);
        background: rgba(18, 18, 18, 0.85);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 16px;

        z-index: 100000;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        backdrop-filter: blur(10px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: none;
    `;




    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    const iconColor = type === 'success' ? '#22c55e' : '#C22626';
    
    notifText.innerHTML = `
        <div style="display:flex; align-items:center; gap:8px;">
            <i class="fas ${iconClass}" style="color: ${iconColor}; font-size: 1rem;"></i>
            <span style="font-size: 0.85rem; font-weight: 500; letter-spacing: 0.01em;">${message}</span>
        </div>
    `;


    
    notif.style.display = 'block';
    
    // Animate in
    requestAnimationFrame(() => {
        notif.style.opacity = '1';
        notif.style.transform = 'translate(-50%, 0) scale(1)';
    });

    // Auto-hide
    setTimeout(() => {
        notif.style.opacity = '0';
        notif.style.transform = 'translate(-50%, -20px) scale(0.9)';
        setTimeout(() => {
            notif.style.display = 'none';
        }, 400);
    }, 3500);

}


// ─── Qty / Remove ─────────────────────────────────────────────────────────────
function updateQty(idx, delta) {
    if (idx < 0 || idx >= cart.length) return;
    if (delta === -1 && cart[idx].quantity <= 1) { removeItem(idx); return; }
    cart[idx].quantity = Math.max(1, cart[idx].quantity + delta);
    renderCart();
    window.updateCartCount();
}

let pendingRemoveIdx = null;

function showRemoveConfirm(idx) {
    pendingRemoveIdx = idx;
    document.getElementById('confirmRemoveOverlay')?.classList.add('open');
}

function hideRemoveConfirm() {
    pendingRemoveIdx = null;
    document.getElementById('confirmRemoveOverlay')?.classList.remove('open');
}

function confirmRemoveItem() {
    if (pendingRemoveIdx !== null && pendingRemoveIdx < cart.length) {
        cart.splice(pendingRemoveIdx, 1);
        renderCart();
        window.updateCartCount();
    }
    hideRemoveConfirm();
}

function removeItem(idx) { showRemoveConfirm(idx); }

// ─── Open / Close Cart ────────────────────────────────────────────────────────
window.openCart = function () {
    const overlay = document.getElementById('cartOverlay');
    if (!overlay) return;

    // Clear any forced 'none' style from previous success
    overlay.style.display = '';

    overlay.classList.add('active');
    lockScroll(true);
    renderCart();
    fetchLastUsedDetails();
    syncPaymentFields();

    if (!overlay.dataset.bgClickBound) {
        overlay.dataset.bgClickBound = '1';
        overlay.addEventListener('click', e => { if (e.target === overlay) window.closeCart(); });
    }
};

window.closeCart = function () {
    const overlay = document.getElementById('cartOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        lockScroll(false);
    }
};

// ─── Payment field sync ───────────────────────────────────────────────────────
function syncPaymentFields() {
    const method = document.querySelector('.payment-method-btn.active')?.dataset.method || 'card';
    const codEl = document.getElementById('codFields');
    const onlineEl = document.getElementById('onlinePayFields');
    const cardFields = document.getElementById('cardDetailsFields');
    const gcashFields = document.getElementById('gcashFields');
    const invoiceInfo = document.getElementById('xenditInvoiceInfo');

    const isCod = method === 'cod';
    if (codEl) codEl.style.display = isCod ? 'block' : 'none';
    if (onlineEl) onlineEl.style.display = !isCod ? 'block' : 'none';
    if (cardFields) cardFields.style.display = (method === 'card') ? 'block' : 'none';
    if (gcashFields) gcashFields.style.display = (method === 'gcash') ? 'block' : 'none';
    if (invoiceInfo) invoiceInfo.style.display = 'none'; // Replaced by gcashFields
}

// ─── Fetch last-used details ──────────────────────────────────────────────────
async function fetchLastUsedDetails() {
    try {
        const res = await fetch('Auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check_session' })
        });
        const data = await res.json();
        if (data.success) {
            const addrField = document.getElementById('cartAddress');
            if (data.last_address && addrField && !addrField.value) {
                addrField.value = data.last_address;
            }
            if (data.last_mobiles?.length > 0) {
                const dl = document.getElementById('phoneSuggestions');
                if (dl) dl.innerHTML = data.last_mobiles.map(m => `<option value="${m}">`).join('');
                const m = data.last_mobiles[0];
                const codMob = document.getElementById('codMobile');
                const cardMob = document.getElementById('card_mobile');
                if (codMob && !codMob.value) codMob.value = m;
                if (cardMob && !cardMob.dataset.userEdited) cardMob.value = m;
            }
        }
    } catch (e) { /* silently ignore */ }
}

// ─── Validation helpers ───────────────────────────────────────────────────────
function clearErrors() {
    document.querySelectorAll('.cart-error-msg').forEach(el => {
        el.textContent = '';
        el.classList.remove('active');
    });
    document.querySelectorAll('.cart-input').forEach(el => {
        el.classList.remove('error-border');
    });
}

function showError(fieldId, msg) {
    const el = document.getElementById('error-' + fieldId);
    const input = document.getElementById(fieldId);
    if (el) {
        el.textContent = msg;
        el.classList.add('active');
        if (input) {
            input.classList.add('error-border');
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// ─── DOMContentLoaded ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Check if we just returned from a full-page GCash redirect
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('order_success') === 'true') {
        const orderId = urlParams.get('order_id');
        console.log('🏁 Returned from GCash redirect, triggering success for Order:', orderId);

        // Brief delay to let the page settle
        setTimeout(() => {
            if (window.onOrderSuccess) {
                window.onOrderSuccess(orderId);
            }
        }, 500);

        // Clean up the URL so refreshing doesn't trigger it again
        const newUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({}, document.title, newUrl);
    }

    // Map button
    const mapBtn = document.getElementById('cartAddressIcon');
    if (mapBtn) {
        mapBtn.addEventListener('click', () => {
            initLeafletMap();
            mapBtn.style.background = 'rgba(255,255,255,.3)';
            setTimeout(() => mapBtn.style.background = '', 200);
        });
    }

    // Remove confirm
    document.getElementById('confirmRemoveYes')?.addEventListener('click', confirmRemoveItem);
    document.getElementById('confirmRemoveNo')?.addEventListener('click', hideRemoveConfirm);

    // Order confirm modal
    document.getElementById('orderConfirmClose')?.addEventListener('click', window.submitOrder);
    document.getElementById('orderConfirmCancelBtn')?.addEventListener('click', window.hideOrderConfirm);

    // Back button
    document.getElementById('cartBackBtn')?.addEventListener('click', e => {
        e.preventDefault();
        window.closeCart();
    });

    // ── Checkout button with full validation ──
    document.getElementById('checkoutBtn')?.addEventListener('click', async function () {
        clearErrors();
        let hasError = false;

        if (cart.length === 0) {
            showTopNotif('Your cart is empty!', 'error');
            return;
        }

        const address = document.getElementById('cartAddress')?.value.trim();
        if (!address) {
            showError('cartAddress', 'Please select or enter your delivery address.');
            hasError = true;
        } else {
            // Validate typed address resolves and is within radius.
            const parsed = await validateAddressWithinRadius(address);
            if (!parsed.ok) {
                showError('cartAddress', parsed.message);
                hasError = true;
            }
        }

        const method = document.querySelector('.payment-method-btn.active')?.dataset.method;
        if (!method) {
            showTopNotif('Please select a payment method!', 'error');
            hasError = true;
        } else {
            if (method === 'card') {
                const cardName = document.getElementById('card_name')?.value.trim();
                const cardNumber = document.getElementById('card_number')?.value.replace(/\s/g, '');
                const cardExpiry = document.getElementById('card_expiry')?.value.trim();
                const cardCvv = document.getElementById('card_cvv')?.value.trim();
                const cardMobile = document.getElementById('card_mobile')?.value.trim();

                if (!cardName) { showError('card_name', 'Name on card is required.'); hasError = true; }
                if (!cardNumber || cardNumber.length < 15) { showError('card_number', 'Enter a valid card number.'); hasError = true; }
                if (!cardExpiry.includes('/') || cardExpiry.length < 5) { showError('card_expiry', 'Invalid expiry (mm/yy).'); hasError = true; }
                if (!cardCvv || cardCvv.length < 3) { showError('card_cvv', 'CVV is required (3–4 digits).'); hasError = true; }
                if (!cardMobile || cardMobile.length < 11) { showError('card_mobile', 'Enter a valid 11-digit mobile number.'); hasError = true; }
            }

            if (method === 'gcash') {
                const gcashMobile = document.getElementById('gcash_mobile')?.value.trim();
                const gcashAccount = document.getElementById('gcash_account')?.value.trim();

                if (!gcashMobile || gcashMobile.length < 11) {
                    showError('gcash_mobile', 'Enter a valid 11-digit GCash number.');
                    hasError = true;
                }
                if (!gcashAccount) {
                    showError('gcash_account', 'GCash account name is required.');
                    hasError = true;
                }
            }

            if (method === 'cod') {
                const name = document.getElementById('codName')?.value.trim();
                const mobile = document.getElementById('codMobile')?.value.trim();
                if (!name) { showError('codName', "Receiver's name is required."); hasError = true; }
                if (!mobile || mobile.length < 11) { showError('codMobile', 'Enter a valid 11-digit mobile number.'); hasError = true; }
            }
        }

        if (hasError) return;

        window.showOrderConfirm();
    });

    // Payment method toggle
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            syncPaymentFields();
        });
    });

    syncPaymentFields();

    // Card number formatting
    document.getElementById('card_number')?.addEventListener('input', function () {
        const val = this.value.replace(/\D/g, '');
        this.value = val.match(/.{1,4}/g)?.join(' ') || val;
    });

    // Expiry formatting
    document.getElementById('card_expiry')?.addEventListener('input', function () {
        let val = this.value.replace(/\D/g, '');
        if (val.length >= 2) val = val.substring(0, 2) + '/' + val.substring(2, 4);
        this.value = val;
    });

    // Mark card mobile as user-edited to skip auto-fill override
    document.getElementById('card_mobile')?.addEventListener('input', function () {
        this.dataset.userEdited = '1';
    });

    // Live Address Validation
    const cartAddrInput = document.getElementById('cartAddress');
    if (cartAddrInput) {
        cartAddrInput.addEventListener('input', debounce(async function () {
            const addr = this.value.trim();
            if (!addr) {
                clearErrors('cartAddress');
                return;
            }

            // Don't bother validating if it's clearly incomplete (e.g. "Main St")
            if (addr.length < 8) {
                showError('cartAddress', 'Please enter a more complete delivery address.');
                return;
            }

            const result = await validateAddressWithinRadius(addr);
            if (!result.ok) {
                showError('cartAddress', result.message);
            } else {
                clearErrors('cartAddress');
            }
        }, 800));
    }

    // Delivery notice close
    document.getElementById('deliveryNoticeClose')?.addEventListener('click', () => {
        document.getElementById('deliveryNoticeOverlay').style.display = 'none';
    });

    window.updateCartCount();
    console.log('✅ carT.js ready — seamless Xendit card, map, all fixed!');
});
