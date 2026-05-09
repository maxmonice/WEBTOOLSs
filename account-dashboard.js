// ── Session guard ──
(async function guardSession() {
            try {
                const res  = await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({action:'check_session'}) });
                const data = await res.json();
                if (!data.success) { sessionStorage.clear(); window.location.href = 'account.php'; return; }
                sessionStorage.setItem('user_name',  data.name  || '');
                sessionStorage.setItem('user_email', data.email || '');
            } catch (e) { sessionStorage.clear(); window.location.href = 'account.php'; }
        })().then(() => initDashboard());

        function initDashboard() {
            userData.name  = sessionStorage.getItem('user_name')  || 'Guest';
            userData.email = sessionStorage.getItem('user_email') || '';
            updateUI();
        }

        let userData = { name:'', email:'' };

        function updateUI() {
            const initial = userData.name.trim().charAt(0).toUpperCase() || '?';
            document.getElementById('avatarInitial').textContent = initial;
            document.getElementById('displayName').textContent   = userData.name;
            document.getElementById('displayEmail').textContent  = userData.email;
            document.getElementById('detailName').textContent    = userData.name;
            document.getElementById('detailEmail').textContent   = userData.email;
            document.getElementById('logoutEmail').textContent   = userData.email;
            const now = new Date();
            document.getElementById('memberSince').textContent =
                now.toLocaleString('default', { month:'long', year:'numeric' });
        }

        // ── Edit Profile ──
        function openEdit() { document.getElementById('inputName').value = userData.name; document.getElementById('inputEmail').value = userData.email; document.getElementById('editModal').classList.add('open'); }
        function closeEdit() { document.getElementById('editModal').classList.remove('open'); }
        function saveProfile() {
            const n = document.getElementById('inputName').value.trim();
            const e = document.getElementById('inputEmail').value.trim();
            if (!n || !e) { showToast('Please fill in all fields.', true); return; }
            userData.name = n; userData.email = e;
            sessionStorage.setItem('user_name', n); sessionStorage.setItem('user_email', e);
            updateUI(); closeEdit(); showToast('Profile updated successfully!');
        }

        // ── Change Password ──
        function openChangePw() {
            ['currentPw','newPw','confirmPw'].forEach(id => { document.getElementById(id).value = ''; document.getElementById(id).type = 'password'; });
            document.querySelectorAll('#changePwModal .pw-toggle i').forEach(i => i.className = 'fas fa-eye');
            document.getElementById('pwStrengthWrap').style.display = 'none';
            const btn = document.getElementById('changePwSaveBtn'); btn.disabled = false; btn.textContent = 'Update Password';
            document.getElementById('changePwModal').classList.add('open');
        }
        function closeChangePw() { document.getElementById('changePwModal').classList.remove('open'); }

        function togglePwField(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('newPw').addEventListener('input', function () {
                const val  = this.value;
                const wrap = document.getElementById('pwStrengthWrap');
                if (!val) { wrap.style.display = 'none'; return; }
                wrap.style.display = 'block';
                let score = 0;
                if (val.length >= 8)           score++;
                if (/[A-Z]/.test(val))         score++;
                if (/[0-9]/.test(val))         score++;
                if (/[^A-Za-z0-9]/.test(val))  score++;
                const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
                const labels = ['Weak','Fair','Good','Strong'];
                const color  = colors[score-1] || '#ef4444';
                for (let i = 1; i <= 4; i++) {
                    document.getElementById('psb'+i).style.background = i<=score ? color : 'rgba(255,255,255,0.1)';
                }
                const lbl = document.getElementById('pwStrengthLabel');
                lbl.textContent = labels[score-1] || '';
                lbl.style.color = color;
            });
        });

        async function submitChangePassword() {
            const current = document.getElementById('currentPw').value;
            const newPw   = document.getElementById('newPw').value;
            const confirm = document.getElementById('confirmPw').value;
            if (!current || !newPw || !confirm) { showToast('Please fill in all fields.', true); return; }
            if (newPw.length < 8)               { showToast('New password must be at least 8 characters.', true); return; }
            if (newPw !== confirm)               { showToast('New passwords do not match.', true); return; }

            const btn = document.getElementById('changePwSaveBtn');
            btn.disabled = true; btn.textContent = 'Updating…';
            try {
                const res  = await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({ action:'change_password', current_password:current, new_password:newPw, confirm_password:confirm }) });
                const data = await res.json();
                if (data.success) { closeChangePw(); showToast(data.message || 'Password updated successfully!'); }
                else { showToast(data.message || 'Failed to update password.', true); }
            } catch (e) { showToast('Network error. Please try again.', true); }
            finally { btn.disabled = false; btn.textContent = 'Update Password'; }
        }

        // ── Logout ──
        function openLogout()  { document.getElementById('logoutModal').classList.add('open'); }
        function closeLogout() { document.getElementById('logoutModal').classList.remove('open'); }
        async function doLogout() {
            closeLogout(); showToast('Signing out…');
            try { await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({action:'logout'}) }); }
            catch (e) { console.warn('Logout failed:', e.message); }
            sessionStorage.clear(); localStorage.clear();
            window.location.href = 'account.php';
        }

        // ── Toast ──
        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            t.querySelector('i').className = isError ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-circle-check';
            t.classList.toggle('error', isError);
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3500);
        }

        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
        });

document.getElementById('mobile-menu').addEventListener('click', () => {
            document.getElementById('navMenu').classList.toggle('active');
        });

        // ── Order Tracking ──
        let _currentOrder = null;

        async function loadOrderTracking() {
            const section = document.getElementById('orderTrackingSection');
            const orderId = localStorage.getItem('order_id');
            const hasPending = localStorage.getItem('order_pending') === 'true';

            if (!hasPending && !orderId) {
                renderNoOrder(section);
                return;
            }

            // Try fetching from backend
            try {
                const url = orderId ? `get-order.php?order_id=${orderId}` : 'get-order.php';
                const res = await fetch(url, { credentials: 'include' });
                const data = await res.json();
                if (data.success && data.order) {
                    _currentOrder = data.order;
                    renderOrderCard(section, data.order);
                    return;
                }
            } catch (e) { /* fallback below */ }

            // Fallback: show generic pending state if localStorage says pending
            if (hasPending) {
                renderGenericPending(section);
            } else {
                renderNoOrder(section);
            }
        }

        function statusToStep(status) {
            if (status === 'delivered') return 3;
            // Backend uses 'shipped' when rider is en route (see rider-orders-api.php)
            if (status === 'shipped' || status === 'on_the_way' || status === 'picked_up' || status === 'on_route') return 2;
            return 1;
        }

        function renderOrderCard(section, order) {
            const step = statusToStep(order.status);
            const fmt  = n => '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
            const payLabel = { cod:'Cash on Delivery', gcash:'GCash', card:'Credit / Debit Card' };
            if (Number.isFinite(Number(order.delivery_latitude)) && Number.isFinite(Number(order.delivery_longitude))) {
                DEST_LAT = Number(order.delivery_latitude);
                DEST_LNG = Number(order.delivery_longitude);
            }

            section.innerHTML = `
            <div class="order-track-card">
                <div class="order-track-header">
                    <span class="order-track-badge"><span class="dot"></span>${order.status.replace(/_/g,' ')}</span>
                    <span class="order-track-id">Order #${order.id}</span>
                </div>
                <div class="order-track-address">
                    <i class="fas fa-location-dot"></i>
                    <span>${order.address}</span>
                </div>

                <!-- Mini animated map -->
                <div class="mini-map-wrap" onclick="openMapFullscreen()" title="Click to expand">
                    <div id="customer-mini-map" style="width:100%; height:100%; border-radius:12px; z-index:1; pointer-events:none;"></div>
                    <div class="map-label" style="z-index:10;">Tap to view full map</div>
                    <div class="map-expand-hint" style="z-index:10;"><i class="fas fa-expand-alt"></i> Full screen</div>
                </div>

                <!-- Progress -->
                <div class="track-progress">
                    <div class="track-step">
                        <div class="track-dot ${step>=1?'done':''}"><i class="fas fa-check" style="font-size:0.55rem"></i></div>
                        <div class="track-label ${step>=1?'done':''}">Order<br>Placed</div>
                    </div>
                    <div class="track-line ${step>=2?'done':''}"></div>
                    <div class="track-step">
                        <div class="track-dot ${step===2?'active':step>2?'done':''}"><i class="fas fa-motorcycle" style="font-size:0.6rem"></i></div>
                        <div class="track-label ${step>=2?'active':''}">On the<br>Way</div>
                    </div>
                    <div class="track-line ${step>=3?'done':''}"></div>
                    <div class="track-step">
                        <div class="track-dot ${step>=3?'done':''}"><i class="fas fa-box" style="font-size:0.55rem"></i></div>
                        <div class="track-label ${step>=3?'done':''}">Delivered</div>
                    </div>
                </div>

                <!-- Info row -->
                <div style="font-size:0.78rem;color:var(--muted);margin-bottom:14px;display:flex;gap:14px;flex-wrap:wrap;">
                    <span><i class="fas fa-receipt" style="color:var(--red);margin-right:4px"></i>${fmt(order.total_amount)}</span>
                    <span><i class="fas fa-wallet" style="color:var(--red);margin-right:4px"></i>${payLabel[order.payment_method]||order.payment_method}</span>
                </div>

                <div class="track-actions">
                    <button class="btn-view-map" onclick="openMapFullscreen()">
                        <i class="fas fa-map-marked-alt"></i> View Full Map
                    </button>
                    <button class="btn-received" onclick="clearOrderTracking()">
                        <i class="fas fa-check-circle"></i> Mark Received
                    </button>
                </div>
            </div>`;

            // Pre-fill fullscreen map address
            const fsAddr = document.getElementById('fsAddress');
            if (fsAddr) fsAddr.textContent = order.address;

            // Initialize the mini map preview immediately after DOM injection
            setTimeout(initCustomerMiniMap, 50);
        }

        function renderGenericPending(section) {
            section.innerHTML = `
            <div class="order-track-card">
                <div class="order-track-header">
                    <span class="order-track-badge"><span class="dot"></span>Pending</span>
                </div>
                <div class="order-track-address"><i class="fas fa-clock"></i><span>Your order is being processed…</span></div>
                <div class="mini-map-wrap" onclick="openMapFullscreen()">
                    <div class="map-grid-bg"></div>
                    <div class="map-road-h" style="top:35%;height:14px"></div>
                    <div class="map-road-v" style="left:30%;width:12px"></div>
                    <div class="map-route"></div>
                    <div class="map-rider-pin"><div class="map-rider-pulse"><i class="fas fa-motorcycle map-rider-icon"></i></div></div>
                    <div class="map-dest-pin"><div class="map-dest-inner"><i class="fas fa-home" style="font-size:0.65rem"></i></div></div>
                    <div class="map-label">Tap to view full map</div>
                    <div class="map-expand-hint"><i class="fas fa-expand-alt"></i> Full screen</div>
                </div>
                <div class="track-actions">
                    <button class="btn-view-map" onclick="openMapFullscreen()"><i class="fas fa-map-marked-alt"></i> View Map</button>
                    <button class="btn-received" onclick="clearOrderTracking()"><i class="fas fa-check-circle"></i> Mark Received</button>
                </div>
            </div>`;
        }

        function renderNoOrder(section) {
            section.innerHTML = `
            <div class="menu-row" style="cursor:default;padding:20px 22px;">
                <div class="mr-left">
                    <div class="mr-icon"><i class="fa-solid fa-box-open"></i></div>
                    <div class="mr-text">
                        <div class="mr-title">No Active Orders</div>
                        <div class="mr-sub">Place an order to track it here</div>
                    </div>
                </div>
            </div>`;
        }

        // ── Leaflet & Socket.io for Customer ──
        let customerMap;
        let customerRiderMarker;
        let customerDestMarker;
        let customerRouteLine;
        
        let miniMap;
        let miniRiderMarker;
        let miniDestMarker;
        let miniRouteLine;

        let trackingSocket;

        let DEST_LAT = 14.545; // fallback if order has no coordinates
        let DEST_LNG = 121.050;

        const riderIconHtml = `
          <div style="width:36px;height:36px;border-radius:50%;background:rgba(194,38,38,0.25);border:2px solid rgba(194,38,38,0.5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 10px rgba(194,38,38,0.4)">
            <i class="fas fa-motorcycle" style="color:#C22626;font-size:1.1rem;"></i>
          </div>
        `;
        const cRiderIcon = L.divIcon({ html: riderIconHtml, className: '', iconSize: [36, 36], iconAnchor: [18, 18] });

        const destIconHtml = `
          <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px rgba(34,197,94,0.5)">
            <i class="fas fa-home" style="color:#fff;font-size:0.85rem;"></i>
          </div>
        `;
        const cDestIcon = L.divIcon({ html: destIconHtml, className: '', iconSize: [30, 30], iconAnchor: [15, 15] });

        function initCustomerMap() {
            if (customerMap) {
                // Resize map if already initialized
                setTimeout(() => customerMap.invalidateSize(), 100);
                return;
            }

            const mapContainer = document.getElementById('customer-map-view');
            if (!mapContainer) return;

            customerMap = L.map(mapContainer, { zoomControl: false }).setView([14.545, 121.050], 14);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(customerMap);

            customerRiderMarker = L.marker([0, 0], { icon: cRiderIcon }).addTo(customerMap);
            customerDestMarker = L.marker([DEST_LAT, DEST_LNG], { icon: cDestIcon }).addTo(customerMap);

            setupSocketListener(); // Setup socket if not already done
        }

        function initCustomerMiniMap() {
            const container = document.getElementById('customer-mini-map');
            if (!container || miniMap) return; // Only init once

            miniMap = L.map(container, {
                zoomControl: false,
                dragging: false,
                touchZoom: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false
            }).setView([14.545, 121.050], 14);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(miniMap);

            miniRiderMarker = L.marker([0, 0], { icon: cRiderIcon }).addTo(miniMap);
            miniDestMarker = L.marker([DEST_LAT, DEST_LNG], { icon: cDestIcon }).addTo(miniMap);

            setupSocketListener(); // Ensure socket is connected to move mini map too
        }

        let socketSetupDone = false;
        function setupSocketListener() {
            if (socketSetupDone) return;
            socketSetupDone = true;
            try {
                trackingSocket = io('http://localhost:3000');
                trackingSocket.on('connect', () => {
                    console.log("Connected to tracking server! ID: " + trackingSocket.id);
                    if (_currentOrder) {
                        trackingSocket.emit('join-order', _currentOrder.id);
                    }
                });

                trackingSocket.on('receive-location', (data) => {
                    // data contains lat and lng directly from the server
                    const newLatLng = [data.lat, data.lng];
                    
                    // Update fullscreen marker & route
                    if (customerRiderMarker) {
                        customerRiderMarker.setLatLng(newLatLng);
                        updateCustomerRoute(newLatLng, [DEST_LAT, DEST_LNG]);
                    }
                    
                    // Update mini marker & route
                    if (miniRiderMarker) {
                        miniRiderMarker.setLatLng(newLatLng);
                        updateMiniRoute(newLatLng, [DEST_LAT, DEST_LNG]);
                    }
                });
            } catch (e) {
                console.warn('Socket.io not available or server offline.');
            }
        }

        function updateCustomerRoute(startLatLng, endLatLng) {
            if (customerRouteLine) {
                customerMap.removeLayer(customerRouteLine);
            }

            customerRouteLine = L.polyline([startLatLng, endLatLng], {
                color: '#C22626', weight: 4, opacity: 0.8, dashArray: '10, 10'
            }).addTo(customerMap);
            
            customerMap.fitBounds([startLatLng, endLatLng], { padding: [30, 30] });

            // Calculate ETA locally
            const distKm = getDistance(startLatLng[0], startLatLng[1], endLatLng[0], endLatLng[1]);
            const etaInMinutes = Math.max(1, Math.round((distKm / 20) * 60)); 
            const fsEtaEl = document.getElementById('fsEta');
            if (fsEtaEl) fsEtaEl.textContent = '~' + etaInMinutes + ' mins';
        }

        function updateMiniRoute(startLatLng, endLatLng) {
            if (!miniMap) return;
            if (miniRouteLine) miniMap.removeLayer(miniRouteLine);

            miniRouteLine = L.polyline([startLatLng, endLatLng], {
                color: '#C22626', weight: 3, opacity: 0.8, dashArray: '5, 5'
            }).addTo(miniMap);
            
            miniMap.fitBounds([startLatLng, endLatLng], { padding: [15, 15] });
        }

        function getDistance(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
        }

        function openMapFullscreen() {
            const overlay = document.getElementById('mapFullscreen');
            if (!overlay) return;
            if (_currentOrder && _currentOrder.address) {
                const el = document.getElementById('fsAddress');
                if (el) el.textContent = _currentOrder.address;
            }
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            
            // Initialize or resize leafet map
            initCustomerMap();
        }

        function closeMapFullscreen() {
            document.getElementById('mapFullscreen')?.classList.remove('open');
            document.body.style.overflow = '';
        }

        function clearOrderTracking() {
            localStorage.removeItem('order_pending');
            localStorage.removeItem('order_id');
            _currentOrder = null;
            renderNoOrder(document.getElementById('orderTrackingSection'));
            showToast('Order marked as received!');
            if (trackingSocket) trackingSocket.disconnect();
        }

        // Close fullscreen map on Escape key
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMapFullscreen(); });

        // Load on page init
        loadOrderTracking();