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
            loadOrderTracking();
            loadBookings(); // Initialize bookings
            setInterval(loadOrderTracking, 30000);
            setInterval(loadBookings, 30000);
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


            try {
                const url = (orderId ? `get-order.php?order_id=${orderId}` : 'get-order.php') + `&t=${Date.now()}`;
                const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
                const data = await res.json();
                console.log('🔄 Polling Order Status:', data);
                if (data.success && data.order) {
                    const order = data.order;
                    console.log('📦 Current Status:', order.status, 'Updated At:', order.updated_at);
                    
                    // ── Auto-clear if delivered for > 30 mins ──
                    if (order.status === 'delivered' && order.updated_at) {
                        const deliveredTime = new Date(order.updated_at.replace(' ', 'T')).getTime(); // Better ISO support
                        const now = new Date().getTime();
                        const diffMins = (now - deliveredTime) / (1000 * 60);
                        console.log('🕒 Minutes since delivery:', diffMins.toFixed(1));
                        if (diffMins >= 30) {
                            console.log('🕒 Auto-clearing (30 min limit reached)');
                            clearOrderTracking(true);
                            return;
                        }
                    }

                    _currentOrder = order;
                    
                    // Sync localStorage if it was empty (e.g. login from new device)
                    if (!orderId) localStorage.setItem('order_id', order.id);
                    if (order.status !== 'delivered') localStorage.setItem('order_pending', 'true');

                    renderOrderCard(section, order);
                    return;

                }
            } catch (e) { /* fallback */ }

            if (hasPending) {
                renderGenericPending(section);
            } else {
                renderNoOrder(section);
            }
        }

        function statusToStep(status) {
            if (status === 'delivered') return 3;
            if (status === 'shipped' || status === 'on_the_way' || status === 'picked_up' || status === 'on_route') return 2;
            return 1;
        }

        function renderOrderCard(section, order) {
            const step = statusToStep(order.status);
            const isOnTheWay = (step === 2);
            const fmt  = n => '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
            
            if (isOnTheWay && Number.isFinite(Number(order.delivery_latitude)) && Number.isFinite(Number(order.delivery_longitude))) {
                DEST_LAT = Number(order.delivery_latitude);
                DEST_LNG = Number(order.delivery_longitude);
            }

            section.innerHTML = `
            <div class="order-track-card">
                <div class="order-track-header">
                    <span class="order-track-badge ${isOnTheWay?'live':''}"><span class="dot"></span>${order.status.replace(/_/g,' ')}</span>
                    <span class="order-track-id">Order #${order.id}</span>
                </div>
                <div class="order-track-address">
                    <i class="fas fa-location-dot"></i>
                    <span>${order.address}</span>
                </div>

                ${step === 2 ? `
                <div class="mini-map-wrap" onclick="openMapFullscreen()" title="Click to expand">
                    <div id="customer-mini-map" style="width:100%; height:100%; border-radius:12px; z-index:1; pointer-events:none;"></div>
                    <div class="map-label" style="z-index:10;">Tap to view live rider</div>
                    <div class="map-expand-hint" style="z-index:10;"><i class="fas fa-expand-alt"></i> Full screen</div>
                </div>
                ` : `
                <div class="pending-status-info" style="background:rgba(255,255,255,0.03); border:1px dashed rgba(255,255,255,0.1); border-radius:12px; padding:20px; text-align:center; margin-bottom:15px; margin-top:15px;">
                    <i class="fas fa-utensils" style="font-size:1.5rem; color:var(--red); margin-bottom:10px; display:block;"></i>
                    <p style="font-size:0.85rem; color:#fff; margin-bottom:4px;">${step === 3 ? 'Order Delivered!' : 'Preparing your Order'}</p>
                    <small style="color:var(--muted); font-size:0.75rem;">${step === 3 ? 'Your food has arrived safely. Enjoy!' : 'Tracking will be available once the rider picks up your order.'}</small>
                </div>
                `}

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

                <div class="track-actions" style="display:flex; flex-direction:column; gap:10px; width:100%;">
                    ${isOnTheWay ? `
                    <button class="btn-view-map" onclick="window.openMapFullscreen()" style="width:100%; background:linear-gradient(135deg,#C22626,#8B0A1E); border:none; color:#fff; padding:14px; border-radius:10px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 15px rgba(194,38,38,0.3); cursor:pointer; transition: transform 0.2s;">
                        <i class="fas fa-map-marked-alt"></i> Track Rider Live
                    </button>
                    ` : ''}
                    <button class="btn-received" onclick="window.markAsReceived()" 
                        style="width:100%; padding:14px; border-radius:10px; ${order.status !== 'delivered' ? 'opacity:0.5; cursor:not-allowed; filter:grayscale(1);' : ''}">
                        <i class="fas fa-check-circle"></i> ${order.status === 'delivered' ? 'Mark Received' : 'Waiting for Delivery'}
                    </button>

                    
                    ${order.status === 'pending' || order.status === 'preparing' ? `
                    <button class="btn-cancel-order" onclick="window.cancelOrder(${order.id})" style="width:100%; padding:12px;">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
                    ` : order.status === 'on_the_way' || order.status === 'picked_up' ? `
                     <button class="btn-cancel-order" disabled title="Cannot cancel while rider is on route" style="width:100%; padding:12px; opacity:0.3;">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
                    ` : ''}
                </div>
            </div>`;

            const fsAddr = document.getElementById('fsAddress');
            if (fsAddr) fsAddr.textContent = order.address;

            if (isOnTheWay) {
                setTimeout(initCustomerMiniMap, 50);
            }
        }

        function renderGenericPending(section, order) {
            const orderIdText = order && order.id ? `<span class="order-track-id">Order #${order.id}</span>` : '';
            section.innerHTML = `
            <div class="order-track-card">
                <div class="order-track-header">
                    <span class="order-track-badge"><span class="dot"></span>Pending</span>
                    ${orderIdText}
                </div>
                <div class="order-track-address"><i class="fas fa-clock"></i><span>Your order is being processed…</span></div>
                
                <div class="pending-status-info" style="background:rgba(255,255,255,0.03); border:1px dashed rgba(255,255,255,0.1); border-radius:12px; padding:20px; text-align:center; margin-bottom:15px; margin-top:15px;">
                    <i class="fas fa-utensils" style="font-size:1.5rem; color:var(--red); margin-bottom:10px; display:block;"></i>
                    <p style="font-size:0.85rem; color:#fff; margin-bottom:4px;">Preparing your Order</p>
                    <small style="color:var(--muted); font-size:0.75rem;">Tracking will be available once the rider picks up your order.</small>
                </div>

                <div class="track-actions" style="margin-top:10px; width:100%; flex-direction:column;">
                    <button class="btn-received" disabled style="width:100%; opacity:0.5; cursor:not-allowed; filter:grayscale(1);">
                        <i class="fas fa-check-circle"></i> Waiting for Delivery
                    </button>
                    <button class="btn-cancel-order" onclick="cancelOrder()" style="margin-top:8px; width:100%;">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
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
                setTimeout(() => customerMap.invalidateSize(), 100);
                return;
            }

            const mapContainer = document.getElementById('customer-map-view');
            if (!mapContainer) return;

            customerMap = L.map(mapContainer, { zoomControl: false }).setView([14.545, 121.050], 14);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(customerMap);

            customerRiderMarker = L.marker([0, 0], { icon: cRiderIcon }).addTo(customerMap);
            customerDestMarker = L.marker([DEST_LAT, DEST_LNG], { icon: cDestIcon }).addTo(customerMap);

            setupSocketListener();
        }

        function initCustomerMiniMap() {
            const container = document.getElementById('customer-mini-map');
            if (!container || miniMap) return;

            miniMap = L.map(container, {
                zoomControl: false,
                dragging: false,
                touchZoom: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false
            }).setView([14.545, 121.050], 14);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(miniMap);

            miniRiderMarker = L.marker([0, 0], { icon: cRiderIcon }).addTo(miniMap);
            miniDestMarker = L.marker([DEST_LAT, DEST_LNG], { icon: cDestIcon }).addTo(miniMap);

            setupSocketListener();
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
                    const newLatLng = [data.lat, data.lng];
                    
                    if (customerRiderMarker) {
                        customerRiderMarker.setLatLng(newLatLng);
                        updateCustomerRoute(newLatLng, [DEST_LAT, DEST_LNG]);
                    }
                    
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
            
            initCustomerMap();
        }

        function closeMapFullscreen() {
            document.getElementById('mapFullscreen')?.classList.remove('open');
            document.body.style.overflow = '';
        }

        window.clearOrderTracking = function(silent = false) {
            localStorage.removeItem('order_pending');
            localStorage.removeItem('order_id');
            _currentOrder = null;
            renderNoOrder(document.getElementById('orderTrackingSection'));
            if (!silent) showToast('Order marked as received!');
            if (trackingSocket) trackingSocket.disconnect();
        };

        window.cancelOrder = function(id) {
            const orderId = id || localStorage.getItem('order_id');
            if (!orderId) return;

            const modal = document.getElementById('cancelOrderModal');
            if (modal) {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
                
                const confirmBtn = document.getElementById('confirmCancelBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = () => window.confirmCancelOrder(orderId);
                }
            }
        };

        window.closeCancelModal = function() {
            document.getElementById('cancelOrderModal')?.classList.remove('open');
            document.body.style.overflow = '';
        };

        window.confirmCancelOrder = async function(orderId) {
            const confirmBtn = document.getElementById('confirmCancelBtn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            }

            try {
                const res = await fetch('cancel-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Order cancelled successfully', 'success');
                    window.closeCancelModal();
                    window.clearOrderTracking(true);
                } else {
                    showToast(data.message || 'Failed to cancel order', 'error');
                }
            } catch (e) {
                showToast('Server error during cancellation', 'error');
            } finally {
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Yes, Cancel Order';
                }
            }
        };

        window.markAsReceived = function() {
            if (!_currentOrder || _currentOrder.status !== 'delivered') {
                showToast('Wait for rider to mark as delivered first!', true);
                return;
            }
            const modal = document.getElementById('riderRatingModal');

            if (modal && _currentOrder) {
                // Populate Rider Name
                const riderNameEl = document.getElementById('riderNamePlaceholder');
                if (riderNameEl) riderNameEl.textContent = _currentOrder.rider_name || 'Delivery Rider';

                // Populate Items list
                const itemsEl = document.getElementById('foodRatingItems');
                if (itemsEl && _currentOrder.items) {
                    const items = _currentOrder.items;
                    if (Array.isArray(items) && items.length > 0) {
                        itemsEl.innerHTML = '<div style="font-weight:700; margin-bottom:5px; color:rgba(255,255,255,0.5)">Items ordered:</div>' + 
                            items.map(it => `<div style="padding-left:10px;">• ${it.name} x ${it.quantity}</div>`).join('');
                    } else {
                        itemsEl.innerHTML = '';
                    }
                }

                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        };

        window.closeRiderRating = function() {
            document.getElementById('riderRatingModal')?.classList.remove('open');
            document.body.style.overflow = '';
            window.showFoodRatingSection();
            window.clearOrderTracking(true);
        };


        window.ignoreRiderRating = function() {
            window.closeRiderRating();
        };

        window.showFoodRatingSection = function() {
            const section = document.getElementById('foodRatingSection');
            if (section && _currentOrder) {
                // Populate Items list
                const itemsEl = document.getElementById('foodRatingItems');
                if (itemsEl && _currentOrder.items) {
                    const items = _currentOrder.items;
                    if (Array.isArray(items) && items.length > 0) {
                        itemsEl.innerHTML = '<div style="font-weight:700; margin-bottom:5px; color:rgba(255,255,255,0.5)">Items ordered:</div>' + 
                            items.map(it => `<div style="padding-left:10px;">• ${it.name} x ${it.quantity}</div>`).join('');
                    } else {
                        itemsEl.innerHTML = '';
                    }
                }
                section.style.display = 'block';
                section.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        window.ignoreFoodRating = function() {
            const section = document.getElementById('foodRatingSection');
            if (section) section.style.display = 'none';
        };


        window.initStars = function(containerId) {
            const stars = document.querySelectorAll(`#${containerId} .star-btn`);
            const container = document.getElementById(containerId);
            
            stars.forEach(btn => {
                btn.addEventListener('click', () => {
                    const val = parseInt(btn.dataset.val);
                    stars.forEach(s => {
                        const sVal = parseInt(s.dataset.val);
                        s.classList.toggle('active', sVal <= val);
                    });
                    
                    if (containerId === 'foodStars') {
                        window._selectedFoodRating = val;
                    } else if (containerId === 'riderStars') {
                        window._selectedRiderRating = val;
                    }
                });
                
                btn.addEventListener('mouseenter', () => {
                    const val = parseInt(btn.dataset.val);
                    stars.forEach(s => {
                        const sVal = parseInt(s.dataset.val);
                        if (sVal <= val) s.classList.add('hovered');
                        else s.classList.remove('hovered');
                    });
                });
            });
            
            if (container) {
                container.addEventListener('mouseleave', () => {
                    stars.forEach(s => s.classList.remove('hovered'));
                });
            }
        };

        window.submitRiderRating = async function() {
            const rating = window._selectedRiderRating || 0;
            const comment = document.getElementById('riderComment')?.value || '';
            
            if (rating === 0) {
                showToast('Please select a star rating', 'error');
                return;
            }

            // In a real app, you'd fetch() to save-rating.php here
            console.log('Submitting Rider Rating:', { rating, comment, orderId: _currentOrder?.id });
            
            showToast('Rider feedback submitted!');
            window.closeRiderRating();
        };

        window.submitFoodRating = async function() {
            const rating = window._selectedFoodRating || 0;

            if (rating === 0) {
                showToast('Please select a star rating', 'error');
                return;
            }

            // In a real app, you'd fetch() to save-rating.php here
            console.log('Submitting Food Rating:', { rating, orderId: _currentOrder?.id });

            showToast('Food feedback submitted!');
            window.ignoreFoodRating();
        };


        window.initStars('riderStars');
        window.initStars('foodStars');

        document.addEventListener('keydown', e => { if (e.key === 'Escape') window.closeMapFullscreen(); });

        loadOrderTracking();
        // Faster polling (10s) when an order is active to detect delivery instantly
        setInterval(loadOrderTracking, 10000);

        // ── Event Bookings ────────────────────────────────────────────────────────
        async function loadBookings() {
            const section = document.getElementById('eventBookingsSection');
            if (!section) return;

            try {
                const res = await fetch('get-bookings.php');
                const data = await res.json();

                if (data.success && data.bookings.length > 0) {
                    section.innerHTML = data.bookings.map(b => `
                        <div class="booking-card" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:20px; margin-bottom:15px; position:relative; overflow:hidden;">
                            <div class="booking-status-badge" style="position:absolute; top:20px; right:20px; padding:5px 12px; border-radius:30px; font-size:0.7rem; font-weight:700; text-transform:uppercase; background:${getStatusBg(b.status)}; color:#fff;">
                                ${b.status}
                            </div>
                            <div style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
                                <div style="width:45px; height:45px; background:rgba(194,38,38,0.1); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--red);">
                                    <i class="fa-solid fa-calendar-day" style="font-size:1.2rem;"></i>
                                </div>
                                <div>
                                    <div style="font-size:1rem; font-weight:700; color:#fff;">${b.event_name}</div>
                                    <div style="font-size:0.8rem; color:rgba(255,255,255,0.5);">${b.event_date} @ ${b.event_time}</div>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.85rem;">
                                <div style="color:rgba(255,255,255,0.4);"><i class="fa-solid fa-users" style="margin-right:5px; width:15px;"></i> Guests: <span style="color:#fff;">${b.num_guests}</span></div>
                                <div style="color:rgba(255,255,255,0.4);"><i class="fa-solid fa-location-dot" style="margin-right:5px; width:15px;"></i> ${b.address.substring(0, 25)}...</div>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error('Error loading bookings:', e);
            }
        }

        function getStatusBg(status) {
            switch(status.toLowerCase()) {
                case 'confirmed': return '#22c55e';
                case 'cancelled': return '#ef4444';
                default: return '#f59e0b';
            }
        }