// =====================================================
// RIDER MAP JAVASCRIPT (Leaflet + Socket.io)
// =====================================================

let map;
let riderMarker;
let customerMarker;
let routeLine;
let socket;

// Store location: G3P4+5WV, Vulcan St, Pinagsama, Taguig
const STORE_LAT = 14.52625;
const STORE_LNG = 121.055375;
const DEFAULT_CENTER = [STORE_LAT, STORE_LNG];

// Custom Icons
const riderIconHtml = `
  <div style="width:36px;height:36px;border-radius:50%;background:rgba(194,38,38,0.25);border:2px solid rgba(194,38,38,0.5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 10px rgba(194,38,38,0.4)">
    <i class="fas fa-motorcycle" style="color:#C22626;font-size:1.1rem;"></i>
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

    // CartoDB Dark Matter (Free, no API key needed, matches dark theme)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Init markers (rider starts at store, destination is destination)
    L.marker([STORE_LAT, STORE_LNG], { icon: storeIcon }).addTo(map); // Static store marker
    riderMarker = L.marker([STORE_LAT, STORE_LNG], { icon: riderIcon }).addTo(map);
    customerMarker = L.marker([DEST_LAT, DEST_LNG], { icon: destIcon }).addTo(map);

    // Connect to Socket.io
    try {
        socket = io('http://localhost:3000');
        socket.on('connect', () => console.log('Socket connected!'));
    } catch (e) {
        console.error('Socket.io connection failed. Is the Node server running?', e);
    }

    startRiderTracking();
}


function startRiderTracking() {
    if ("geolocation" in navigator) {
        
        navigator.geolocation.watchPosition(
            (position) => {
                const currentLatLng = [position.coords.latitude, position.coords.longitude];

                // 1. Update Rider Marker locally
                riderMarker.setLatLng(currentLatLng);

                // 2. Redraw routing path & calculate ETA
                updateRoute(currentLatLng, [DEST_LAT, DEST_LNG]);

                // 3. Emit position to Customer via Socket.io
                if (socket && socket.connected) {
                    socket.emit('send-location', {
                        orderId: ACTIVE_ORDER_ID,
                        lat: currentLatLng[0],
                        lng: currentLatLng[1]
                    });
                }
            },
            (error) => {
                console.error("Error getting location:", error.message);
                showToast("Location access required for tracking.");
            },
            {
                enableHighAccuracy: true,
                maximumAge: 5000,
                timeout: 10000
            }
        );
    } else {
        showToast("Geolocation is not supported by your browser.");
    }
}


function updateRoute(startLatLng, endLatLng) {
    if (routeLine) {
        map.removeLayer(routeLine);
    }

    // Draw a direct guaranteed line between the two points
    routeLine = L.polyline([startLatLng, endLatLng], {
        color: '#C22626',
        weight: 4,
        opacity: 0.8,
        dashArray: '10, 10' // Makes the line dashed
    }).addTo(map);

    // Auto-zoom map to fit both points
    map.fitBounds([startLatLng, endLatLng], { padding: [50, 50] });

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

function markDelivered() {
    showToast('Order marked as delivered!');
    // In a real app, send API call here
    setTimeout(() => window.location.href = 'orders.php', 1500);
}

document.addEventListener('DOMContentLoaded', initMap);