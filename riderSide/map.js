// =====================================================
// RIDER MAP JAVASCRIPT (Leaflet + Socket.io)
// =====================================================

let map;
let riderMarker;
let customerMarker;
let routingControl;
let socket;


// Store location: G3P4+5WV, Vulcan St, Pinagsama, Taguig
const STORE_LAT = 14.52625;
const STORE_LNG = 121.055375;
const DEFAULT_CENTER = [STORE_LAT, STORE_LNG];

// Custom Icons
const riderIconHtml = `
  <div style="display:flex;align-items:center;justify-content:center;">
    <i class="fa-solid fa-fish" style="color:#C22626;font-size:1.8rem;filter: drop-shadow(0 0 5px rgba(194,38,38,0.4));"></i>
  </div>
`;
const riderIcon = L.divIcon({
    html: riderIconHtml,
    className: '',
    iconSize: [36, 36],
    iconAnchor: [18, 18]
});

const destIconHtml = `
  <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px rgba(34,197,94,0.5)">
    <i class="fas fa-home" style="color:#fff;font-size:0.85rem;"></i>
  </div>
`;
const destIcon = L.divIcon({
    html: destIconHtml,
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

const storeIconHtml = `
  <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#2563eb);display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px rgba(59,130,246,0.5)">
    <i class="fas fa-store" style="color:#fff;font-size:0.85rem;"></i>
  </div>
`;
const storeIcon = L.divIcon({
    html: storeIconHtml,
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});


function initMap() {
    const mapContainer = document.getElementById('map-view');
    if (!mapContainer) return;

    map = L.map(mapContainer, { zoomControl: false }).setView(DEFAULT_CENTER, 14);

    // CartoDB Voyager (Light theme)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Init markers (rider starts at store, destination is destination)
    L.marker([STORE_LAT, STORE_LNG], { icon: storeIcon }).addTo(map); // Static store marker
    riderMarker = L.marker([STORE_LAT, STORE_LNG], { icon: riderIcon }).addTo(map);
    
    if (ACTIVE_ORDER_ID > 0) {
        // Only add destination marker if we have valid coordinates
        if (DEST_LAT !== 0 && DEST_LNG !== 0) {
            customerMarker = L.marker([DEST_LAT, DEST_LNG], { icon: destIcon }).addTo(map);
        }
        
        // Connect to Socket.io
        try {
            socket = io('http://localhost:3000', {
                transports: ['websocket', 'polling'],
                reconnection: true,
                reconnectionAttempts: 5,
                reconnectionDelay: 2000
            });
            socket.on('connect', () => {
                console.log('✅ Socket connected! Joining order room:', ACTIVE_ORDER_ID);
                socket.emit('join-order', ACTIVE_ORDER_ID);
                socket.emit('join-chat', ACTIVE_ORDER_ID);
            });
            socket.on('connect_error', (e) => console.warn('Socket error:', e.message));
        } catch (e) {
            console.warn('Socket.io unavailable, falling back to polling.', e);
        }

        startRiderTracking();
    }
}


function startRiderTracking() {
    if (!('geolocation' in navigator)) {
        showToast('⚠️ Geolocation not supported by this browser.');
        return;
    }

    navigator.geolocation.watchPosition(
        (position) => {
            const currentLatLng = [position.coords.latitude, position.coords.longitude];

            // 1. Update Rider Marker locally
            riderMarker.setLatLng(currentLatLng);
            map.panTo(currentLatLng);

            // 2. Redraw routing path & calculate ETA
            if (DEST_LAT !== 0 && DEST_LNG !== 0) {
                updateRoute(currentLatLng, [DEST_LAT, DEST_LNG]);
            }

            // 3. Emit position to Customer via Socket.io
            if (socket && socket.connected) {
                socket.emit('send-location', {
                    orderId: ACTIVE_ORDER_ID,
                    lat: currentLatLng[0],
                    lng: currentLatLng[1]
                });
            }

            // 4. Also save location via HTTP as fallback for polling customers
            saveLocationHTTP(currentLatLng[0], currentLatLng[1]);
        },
        (error) => {
            const msgs = {
                1: 'Location permission denied. Please enable it in browser settings.',
                2: 'Location unavailable. Check GPS signal.',
                3: 'Location request timed out. Retrying...'
            };
            showToast('⚠️ ' + (msgs[error.code] || error.message));
            console.error('Geolocation error:', error);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 3000,
            timeout: 15000
        }
    );
}

// Fallback: save rider location via HTTP so customer polling can also get it
function saveLocationHTTP(lat, lng) {
    if (!ACTIVE_ORDER_ID) return;
    const fd = new FormData();
    fd.append('action', 'update_rider_location');
    fd.append('order_id', ACTIVE_ORDER_ID);
    fd.append('lat', lat);
    fd.append('lng', lng);
    fetch('rider-orders-api.php', { method: 'POST', body: fd }).catch(() => {});
}


function updateRoute(startLatLng, endLatLng) {
    if (routingControl) {
        map.removeControl(routingControl);
    }

    // Use Leaflet Routing Machine for professional routing
    routingControl = L.Routing.control({
        waypoints: [
            L.latLng(startLatLng[0], startLatLng[1]),
            L.latLng(endLatLng[0], endLatLng[1])
        ],
        lineOptions: {
            styles: [{color: '#0044cc', opacity: 0.8, weight: 6}] // Professional Blue line
        },
        createMarker: function() { return null; }, // Hide default markers
        addWaypoints: false,
        draggableWaypoints: false,
        fitSelectedRoutes: true,
        show: false // Hide the instruction panel
    }).addTo(map);

    // Calculate approximate ETA locally (based on distance)
    const distKm = getDistance(startLatLng[0], startLatLng[1], endLatLng[0], endLatLng[1]);
    const avgSpeedKmh = 20; // Average scooter speed in Manila traffic
    const etaInMinutes = Math.max(1, Math.round((distKm / avgSpeedKmh) * 60));
    
    // Update ETA UI in delivery card
    const etaValEl = document.querySelector('.eta-val');
    if (etaValEl) etaValEl.textContent = etaInMinutes;
}


// Haversine formula to get distance in km between two lat/lngs
function getDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
}

function showToast(msg) {
    const t = document.getElementById('toast');
    if(!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

window.triggerDeliveryConfirm = function() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('open');
    } else {
        if (confirm('Are you sure you want to mark this order as delivered?')) {
            window.handleDeliverySuccess();
        }
    }
};

window.closeConfirmModal = function() {
    document.getElementById('confirmModal')?.classList.remove('open');
};

window.handleDeliverySuccess = function() {
    const orderId = typeof ACTIVE_ORDER_ID !== 'undefined' ? ACTIVE_ORDER_ID : null;
    if (!orderId) {
        showToast('❌ Error: Missing Order ID');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'deliver_order');
    fd.append('order_id', orderId);

    fetch('rider-orders-api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.closeConfirmModal();
                showToast('✅ Order delivered!');
                setTimeout(() => {
                    window.location.href = 'orders.php';
                }, 1500);
            } else {
                showToast('❌ ' + (data.message || 'Error updating status'));
            }
        })
        .catch(() => showToast('❌ Network error'));
};

document.addEventListener('DOMContentLoaded', initMap);