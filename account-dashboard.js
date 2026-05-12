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
            userData.photo = localStorage.getItem('user_photo')   || '';
            updateUI();
            loadOrderTracking();
            loadBookings();
            // Start socket early so it's ready when order is shipped
            setupSocketListener();
            setInterval(loadOrderTracking, 8000);
            setInterval(loadBookings, 8000);
        }

        window.cancelOrder = async function(orderId) {
            const id = orderId || localStorage.getItem('order_id');
            if (!id) {
                showToast('Order not found.', true);
                return;
            }

            if (!confirm('Are you sure you want to cancel this order?')) return;
            try {
                const res = await fetch('cancel-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: id })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Order cancelled successfully.');
                    localStorage.removeItem('order_pending');
                    localStorage.removeItem('order_id');
                    loadOrderTracking();
                } else {
                    showToast(data.message || 'Could not cancel order.', true);
                }
            } catch (e) {
                showToast('Network error while cancelling.', true);
            }
        };


        let userData = { name:'', email:'', photo:'' };

        function updateUI() {
            const initial = userData.name.trim().charAt(0).toUpperCase() || '?';
            const initialEl = document.getElementById('avatarInitial');
            const imgEl = document.getElementById('avatarImg');

            if (userData.photo) {
                if (initialEl) initialEl.style.display = 'none';
                if (imgEl) {
                    imgEl.src = userData.photo;
                    imgEl.style.display = 'block';
                }
            } else {
                if (initialEl) {
                    initialEl.textContent = initial;
                    initialEl.style.display = 'block';
                }
                if (imgEl) imgEl.style.display = 'none';
            }

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
        window.openEdit = function() { 
            document.getElementById('inputName').value = userData.name; 
            document.getElementById('inputEmail').value = userData.email; 
            
            // Set edit preview
            const initial = userData.name.trim().charAt(0).toUpperCase() || '?';
            const initialEl = document.getElementById('editAvatarInitial');
            const imgEl = document.getElementById('editAvatarImg');
            
            if (userData.photo) {
                if (initialEl) initialEl.style.display = 'none';
                if (imgEl) {
                    imgEl.src = userData.photo;
                    imgEl.style.display = 'block';
                }
            } else {
                if (initialEl) {
                    initialEl.textContent = initial;
                    initialEl.style.display = 'block';
                }
                if (imgEl) imgEl.style.display = 'none';
            }
            
            document.getElementById('editModal').classList.add('open'); 
        }
        
        window.closeEdit = function() { document.getElementById('editModal').classList.remove('open'); }
        
        window.previewProfilePhoto = function(input) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgEl = document.getElementById('editAvatarImg');
                const initialEl = document.getElementById('editAvatarInitial');
                if (imgEl) {
                    imgEl.src = e.target.result;
                    imgEl.style.display = 'block';
                    imgEl.dataset.newPhoto = e.target.result; // Store temporarily
                }
                if (initialEl) initialEl.style.display = 'none';
            };
            reader.readAsDataURL(file);
        };

        window.saveProfile = function() {
            const n = document.getElementById('inputName').value.trim();
            const e = document.getElementById('inputEmail').value.trim();
            const newPhoto = document.getElementById('editAvatarImg').dataset.newPhoto;
            
            if (!n || !e) { showToast('Please fill in all fields.', true); return; }
            
            userData.name = n; 
            userData.email = e;
            if (newPhoto) {
                userData.photo = newPhoto;
                localStorage.setItem('user_photo', newPhoto);
            }
            
            sessionStorage.setItem('user_name', n); 
            sessionStorage.setItem('user_email', e);
            
            updateUI(); 
            window.closeEdit(); 
            showToast('Profile updated');
        }

        // ── Change Password ──
        window.openChangePw = function() {
            ['currentPw','newPw','confirmPw'].forEach(id => { document.getElementById(id).value = ''; document.getElementById(id).type = 'password'; });
            document.querySelectorAll('#changePwModal .pw-toggle i').forEach(i => i.className = 'fas fa-eye');
            document.getElementById('pwStrengthWrap').style.display = 'none';
            const btn = document.getElementById('changePwSaveBtn'); btn.disabled = false; btn.textContent = 'Update Password';
            document.getElementById('changePwModal').classList.add('open');
        }
        window.closeChangePw = function() { document.getElementById('changePwModal').classList.remove('open'); }

        function togglePwField(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Flatpickr for Edit Booking
            window._editDatePicker = flatpickr("#eEventDate", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                minDate: "today",
                disable: [
                    function(date) {
                        // Check 3-day window (today + 2 days)
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        const threeDaysOut = new Date();
                        threeDaysOut.setDate(today.getDate() + 2);
                        threeDaysOut.setHours(23,59,59,999);

                        if (date >= today && date <= threeDaysOut) {
                            // EXCEPT if it's the date already saved for this booking
                            if (window._activeBooking && window._activeBooking.event_date) {
                                const current = new Date(window._activeBooking.event_date);
                                current.setHours(0,0,0,0);
                                if (date.getTime() === current.getTime()) return false;
                            }
                            return true;
                        }
                        return false;
                    }
                ],
                theme: "dark",
                disableMobile: true
            });





            window._editTimePicker = flatpickr("#eEventTime", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "h:i K",
                time_24hr: false,
                theme: "dark",
                disableMobile: true
            });

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
        window.openLogout = function() {
            const modal = document.getElementById('logoutModal');
            if (modal) modal.classList.add('active');
        };

        window.closeLogout = function() {
            const modal = document.getElementById('logoutModal');
            if (modal) modal.classList.remove('active');
        };

        window.doLogout = function() {
            // Redirect to logout script
            window.location.href = 'Auth.php?action=logout';
        };


        // ── Toast ──
        function showToast(msg, isError = false) {
            console.log('🔔 Showing Toast:', msg);
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
        let _userBookings = [];


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
                        const deliveredTime = new Date(order.updated_at.replace(' ', 'T')).getTime();
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

                    // Join socket rooms when order is on the way
                    if (order.status === 'shipped') {
                        if (trackingSocket && trackingSocket.connected) {
                            trackingSocket.emit('join-order', order.id);
                            trackingSocket.emit('join-chat', order.id);
                        }
                        // HTTP fallback: update map markers from polled rider location
                        if (order.rider_lat && order.rider_lng) {
                            const riderLatLng = [order.rider_lat, order.rider_lng];
                            if (customerRiderMarker) {
                                customerRiderMarker.setLatLng(riderLatLng);
                            }
                            if (miniRiderMarker) {
                                miniRiderMarker.setLatLng(riderLatLng);
                            }
                            if (order.delivery_latitude && order.delivery_longitude) {
                                DEST_LAT = order.delivery_latitude;
                                DEST_LNG = order.delivery_longitude;
                            }
                        }
                    }

                    renderOrderCard(section, order);
                    loadChatThreads();
                    return;
                }
            } catch (e) { console.warn('Tracking poll error:', e); }

            if (hasPending) {
                document.getElementById('trackingBlock').style.display = 'block';
                renderGenericPending(section);
            } else {
                document.getElementById('trackingBlock').style.display = 'none';
                renderNoOrder(section);
            }
        }

        function statusToStep(status) {
            if (status === 'delivered') return 3;
            if (['shipped', 'on_the_way', 'picked_up', 'on_route'].includes(status)) return 2;
            return 1;
        }

        function getStatusLabel(status) {
            const map = {
                'pending': 'Pending',
                'confirmed': 'Preparing',
                'processing': 'Processing',
                'shipped': 'On the Way',
                'on_the_way': 'On the Way',
                'picked_up': 'On the Way',
                'on_route': 'On the Way',
                'delivered': 'Delivered',
                'cancelled': 'Cancelled'
            };
            return map[status] || status.replace(/_/g, ' ');
        }

        function renderOrderCard(section, order) {
            document.getElementById('trackingBlock').style.display = 'block';
            const step = statusToStep(order.status);
            const isOnTheWay = (step === 2);
            const statusLabel = getStatusLabel(order.status);
            const fmt  = n => '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
            
            if (isOnTheWay && Number.isFinite(Number(order.delivery_latitude)) && Number.isFinite(Number(order.delivery_longitude))) {
                DEST_LAT = Number(order.delivery_latitude);
                DEST_LNG = Number(order.delivery_longitude);
            }

            section.innerHTML = `
            <div class="order-track-card">
                <div class="order-track-header">
                    <span class="order-track-badge ${isOnTheWay?'live':''}"><span class="dot"></span>${statusLabel}</span>
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
                    <button class="btn-view-map" onclick="window.openMapFullscreen()" style="width:100%; background:linear-gradient(135deg,#C22626,#8B0A1E); border:none; color:#fff; padding:14px; border-radius:10px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
                        <i class="fas fa-map-marked-alt"></i> Track Rider Live
                    </button>
                    <button onclick="window.openChatModal()" style="width:100%; background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.15); color:#fff; padding:12px; border-radius:10px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer; font-family:inherit; font-size:0.9rem;">
                        <i class="fas fa-comment-dots" style="color:#22c55e;"></i> Chat with Rider
                    </button>
                    ` : ''}
                    <button class="btn-received" onclick="window.markAsReceived()" 
                        style="width:100%; padding:14px; border-radius:10px; ${order.status !== 'delivered' ? 'opacity:0.5; cursor:not-allowed; filter:grayscale(1);' : ''}">
                        <i class="fas fa-check-circle"></i> ${order.status === 'delivered' ? 'Mark Received' : 'Waiting for Delivery'}
                    </button>

                    ${(order.status === 'pending' || order.status === 'confirmed') ? `
                    <button class="btn-cancel-order" onclick="window.cancelOrder(${order.id})" style="width:100%; padding:12px;">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
                    ` : order.status === 'shipped' ? `
                    <button class="btn-cancel-order" disabled title="Cannot cancel — rider is already on the way" style="width:100%; padding:12px; opacity:0.3; cursor:not-allowed;">
                        <i class="fas fa-times-circle"></i> Cannot Cancel — Rider On Route
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

                <div class="track-actions" style="margin-top:10px; width:100%; display:flex; flex-direction:column; gap:10px;">
                    <button class="btn-received" disabled style="width:100%; opacity:0.5; cursor:not-allowed; filter:grayscale(1);">
                        <i class="fas fa-check-circle"></i> Waiting for Delivery
                    </button>
                    <button class="btn-cancel-order" onclick="window.cancelOrder()" style="width:100%;">
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
        let customerRoutingControl;
        let miniRoutingControl;


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
                trackingSocket = io('http://localhost:3000', {
                    transports: ['websocket', 'polling'],
                    reconnection: true,
                    reconnectionAttempts: 10,
                    reconnectionDelay: 3000
                });
                trackingSocket.on('connect', () => {
                    console.log('✅ Socket connected! ID: ' + trackingSocket.id);
                    // Use _currentOrder if set, else fall back to localStorage
                    const orderId = (_currentOrder && _currentOrder.id) || localStorage.getItem('order_id');
                    if (orderId) {
                        trackingSocket.emit('join-order', orderId);
                        trackingSocket.emit('join-chat', orderId);
                        console.log('📡 Joined rooms for order:', orderId);
                    }
                });

                // Re-join on reconnect
                trackingSocket.on('reconnect', () => {
                    const orderId = (_currentOrder && _currentOrder.id) || localStorage.getItem('order_id');
                    if (orderId) {
                        trackingSocket.emit('join-order', orderId);
                        trackingSocket.emit('join-chat', orderId);
                    }
                });

                trackingSocket.on('connect_error', (e) => {
                    console.warn('⚠️ Socket error (HTTP fallback active):', e.message);
                });

                trackingSocket.on('receive-location', (data) => {
                    const newLatLng = [data.lat, data.lng];
                    if (customerRiderMarker) {
                        customerRiderMarker.setLatLng(newLatLng);
                        if (DEST_LAT && DEST_LNG) updateCustomerRoute(newLatLng, [DEST_LAT, DEST_LNG]);
                    }
                    if (miniRiderMarker) {
                        miniRiderMarker.setLatLng(newLatLng);
                        if (DEST_LAT && DEST_LNG) updateMiniRoute(newLatLng, [DEST_LAT, DEST_LNG]);
                    }
                });

                trackingSocket.on('new-message', (data) => {
                    const supportRoom = 'support_' + (userData.email.replace(/[^a-zA-Z0-9]/g, '_'));
                    if (data.orderId === supportRoom) {
                        if (data.sender !== 'customer') {
                            appendAdminMessage(data);
                            const adminModal = document.getElementById('adminChatModal');
                            if (adminModal && !adminModal.classList.contains('open')) {
                                showToast('💬 New message from Admin!');
                            }
                        }
                    } else if (_currentOrder && data.orderId == _currentOrder.id) {
                        if (data.sender !== 'customer') {
                            appendCustomerMessage(data);
                            const chatModal = document.getElementById('chatModal');
                            if (chatModal && !chatModal.classList.contains('open')) {
                                showToast('💬 New message from rider!');
                            }
                        }
                    }
                });

                // Real-time order status updates
                trackingSocket.on('order-status-update', (data) => {
                    const myId = (_currentOrder && _currentOrder.id) || localStorage.getItem('order_id');
                    if (myId && data.orderId == myId) {
                        console.log('📦 Real-time status:', data.status);
                        loadOrderTracking();
                    }
                });

            } catch (e) {
                console.warn('Socket.io not available, HTTP polling active.');
            }
        }

        // ── Customer Chat Functions ──
        window.openChatModal = function() {
            if (!_currentOrder) {
                showToast('No active order to chat about.', true);
                return;
            }
            document.getElementById('chatModal').classList.add('open');
            document.body.style.overflow = 'hidden';
            
            // Focus input
            setTimeout(() => document.getElementById('customer-chat-input').focus(), 300);
            
            // Join room if not joined
            if (trackingSocket && trackingSocket.connected) {
                trackingSocket.emit('join-chat', _currentOrder.id);
            } else {
                setupSocketListener();
            }
        };

        window.closeChatModal = function() {
            document.getElementById('chatModal').classList.remove('open');
            document.body.style.overflow = '';
        };

        function sendCustomerMessage() {
            const input = document.getElementById('customer-chat-input');
            const message = input.value.trim();
            if (!message || !_currentOrder || !trackingSocket) return;

            const data = {
                orderId: _currentOrder.id,
                sender: 'customer',
                message: message,
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            };

            trackingSocket.emit('send-message', data);
            input.value = '';
        }

        function appendCustomerMessage(data) {
            const container = document.getElementById('customer-chat-messages');
            if (!container) return;
            
            const div = document.createElement('div');
            const isMe = data.sender === 'customer';
            
            div.style.cssText = `
                max-width: 80%;
                padding: 10px 14px;
                border-radius: 18px;
                font-size: 0.9rem;
                line-height: 1.4;
                align-self: ${isMe ? 'flex-end' : 'flex-start'};
                background: ${isMe ? 'var(--red)' : 'rgba(255,255,255,0.08)'};
                color: #fff;
                border-bottom-${isMe ? 'right' : 'left'}-radius: 4px;
                border: ${isMe ? 'none' : '1px solid rgba(255,255,255,0.05)'};
            `;
            
            div.innerHTML = `
                ${data.message}
                <span style="font-size:0.65rem; opacity:0.6; margin-top:4px; display:block; text-align:right;">${data.timestamp}</span>
            `;
            
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        // Initialize event listeners for customer chat
        document.addEventListener('DOMContentLoaded', () => {
            const sendBtn = document.getElementById('customer-send-btn');
            const input = document.getElementById('customer-chat-input');
            const chatBtn = document.getElementById('customerChatBtn');

            if (sendBtn) sendBtn.onclick = sendCustomerMessage;
            if (input) {
                input.onkeypress = (e) => {
                    if (e.key === 'Enter') sendCustomerMessage();
                };
            }
            if (chatBtn) {
                chatBtn.onclick = window.openChatModal;
            }

            // Admin Chat listeners
            const adminSendBtn = document.getElementById('admin-send-btn');
            const adminInput = document.getElementById('admin-chat-input');
            if (adminSendBtn) adminSendBtn.onclick = sendAdminMessage;
            if (adminInput) {
                adminInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                adminInput.onkeydown = (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendAdminMessage();
                    }
                };
            }
        });

        // ── Admin Chat Functions ──
        window.openAdminChatFullscreen = function() {
            const overlay = document.getElementById('adminChatFullscreen');
            if (!overlay) return;
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('admin-chat-input').focus(), 300);
            
            if (trackingSocket && trackingSocket.connected) {
                const supportRoom = 'support_' + (userData.email.replace(/[^a-zA-Z0-9]/g, '_'));
                trackingSocket.emit('join-chat', supportRoom);
            }
        };

        window.closeAdminChatFullscreen = function() {
            document.getElementById('adminChatFullscreen')?.classList.remove('open');
            document.body.style.overflow = '';
        };

        // ── UNIFIED CHAT LOGIC ──
        let currentChatTarget = null;
        let currentChatOrderId = null;

        window.openAdminChat = function() {
            openChat('admin', 1, null); // Assuming 1 is the primary admin ID or just null for system admin
        };

        window.openRiderChat = function(riderId, orderId) {
            openChat('rider', riderId, orderId);
        };

        window.openChat = async function(targetType, targetId, orderId) {
            currentChatTarget = { type: targetType, id: targetId };
            currentChatOrderId = orderId;

            const overlay = document.getElementById('chatOverlay');
            const nameEl = document.getElementById('chatTargetName');
            const avatarEl = document.getElementById('chatAvatar');
            const messagesContainer = document.getElementById('chatMessages');

            nameEl.textContent = targetType === 'admin' ? "Admin Support" : "Delivery Rider";
            avatarEl.textContent = targetType === 'admin' ? "A" : "R";
            avatarEl.className = `chat-avatar ${targetType}`;
            
            messagesContainer.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            overlay.classList.add('active');
            
            try {
                const url = `chat-api.php?action=get_history&order_id=${orderId || ''}&other_id=${targetId || ''}&other_type=${targetType}`;
                const res = await fetch(url);
                const data = await res.json();
                
                messagesContainer.innerHTML = '';
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => appendToChat(msg));
                } else {
                    messagesContainer.innerHTML = `<div style="text-align:center; padding:20px; color:var(--muted); font-size:0.8rem;">No messages yet. Say hello!</div>`;
                }
                scrollToChatBottom();
            } catch (e) {
                console.error("Chat error:", e);
                messagesContainer.innerHTML = '<div class="empty-state">Error loading history.</div>';
            }
        };

        window.closeChat = function() {
            document.getElementById('chatOverlay').classList.remove('active');
            currentChatTarget = null;
            currentChatOrderId = null;
        };

        function appendToChat(msg) {
            const container = document.getElementById('chatMessages');
            const isMe = msg.sender_type === 'customer';
            
            const div = document.createElement('div');
            div.className = `message ${isMe ? 'customer' : msg.sender_type}`;
            div.innerHTML = `
                ${msg.message}
                <span class="message-time">${msg.timestamp}</span>
            `;
            container.appendChild(div);
        }

        async function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message || !currentChatTarget) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            if (currentChatOrderId) formData.append('order_id', currentChatOrderId);
            formData.append('receiver_id', currentChatTarget.id);
            formData.append('receiver_type', currentChatTarget.type);

            input.value = '';
            input.style.height = 'auto';

            try {
                const res = await fetch('chat-api.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (!data.success) showToast(data.message, true);
                // The socket listener will append the message for us if we want real-time feedback
                // OR we append immediately for responsiveness
                appendToChat({
                    sender_type: 'customer',
                    message: message,
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                });
                scrollToChatBottom();
            } catch (e) {
                showToast("Failed to send message", true);
            }
        }

        function scrollToChatBottom() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
        }

        document.getElementById('chatSendBtn')?.addEventListener('click', sendChatMessage);
        document.getElementById('chatInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });

        function setupChatSocket() {
            if (!window.io) return;
            const socket = io('http://localhost:3000');
            
            socket.on('new-message', (data) => {
                // If it's for current chat
                if (currentChatTarget && 
                   ((data.orderId && data.orderId == currentChatOrderId) || 
                    (!data.orderId && data.sender == currentChatTarget.type && data.senderId == currentChatTarget.id))) {
                    appendToChat(data);
                    scrollToChatBottom();
                } else {
                    // Show notification or dot
                    showChatNotification(data);
                }
            });
        }

        function showChatNotification(data) {
            // Update the thread preview in the messages list
            if (data.sender === 'admin') {
                document.getElementById('admin-last-msg').textContent = data.message;
                document.getElementById('admin-last-time').textContent = data.timestamp;
            }
            // For rider, we might need to refresh the list
            loadChatThreads();
        }

        async function loadChatThreads() {
             // Logic to show active rider thread if shipping
             const riderContainer = document.getElementById('riderChatThread');
             if (_currentOrder && _currentOrder.status === 'shipped' && _currentOrder.rider_id) {
                 riderContainer.innerHTML = `
                    <div class="message-thread-item" onclick="openRiderChat(${_currentOrder.rider_id}, ${_currentOrder.id})">
                        <div class="thread-avatar"><i class="fas fa-motorcycle"></i></div>
                        <div class="thread-info">
                            <div class="thread-header">
                                <span class="thread-name">${_currentOrder.rider_name || 'Your Rider'}</span>
                                <span class="thread-time">Active</span>
                            </div>
                            <div class="thread-preview">Rider is on the way with your order!</div>
                        </div>
                    </div>
                 `;
             } else {
                 riderContainer.innerHTML = '';
             }
        }

        // Call this inside loadOrderTracking
        // And inside init
        setupChatSocket();

        function updateCustomerRoute(startLatLng, endLatLng) {
            if (customerRoutingControl) {
                customerMap.removeControl(customerRoutingControl);
            }

            customerRoutingControl = L.Routing.control({
                waypoints: [
                    L.latLng(startLatLng[0], startLatLng[1]),
                    L.latLng(endLatLng[0], endLatLng[1])
                ],
                lineOptions: {
                    styles: [{color: '#C22626', opacity: 0.8, weight: 6}]
                },
                createMarker: function() { return null; },
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false
            }).addTo(customerMap);
            
            const distKm = getDistance(startLatLng[0], startLatLng[1], endLatLng[0], endLatLng[1]);
            const etaInMinutes = Math.max(1, Math.round((distKm / 20) * 60)); 
            const fsEtaEl = document.getElementById('fsEta');
            if (fsEtaEl) fsEtaEl.textContent = '~' + etaInMinutes + ' mins';
        }


        function updateMiniRoute(startLatLng, endLatLng) {
            if (!miniMap) return;
            if (miniRoutingControl) miniMap.removeControl(miniRoutingControl);

            miniRoutingControl = L.Routing.control({
                waypoints: [
                    L.latLng(startLatLng[0], startLatLng[1]),
                    L.latLng(endLatLng[0], endLatLng[1])
                ],
                lineOptions: {
                    styles: [{color: '#C22626', opacity: 0.8, weight: 4}]
                },
                createMarker: function() { return null; },
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false
            }).addTo(miniMap);
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
        setInterval(loadOrderTracking, 10000);        async function loadBookings() {
            const section = document.getElementById('eventBookingsSection');
            if (!section) return;

            try {
                const res = await fetch('get-bookings.php');
                const data = await res.json();
                
                if (data.success) {
                    _userBookings = data.bookings || [];
                    const activeBookings = _userBookings.filter(b => b.status.toLowerCase() !== 'cancelled');

                    let html = '';
                    if (activeBookings.length > 0) {
                        html += `
                            <div class="booking-list-container" style="background:transparent; border-top:none; border-radius:0 0 16px 16px; overflow:hidden;">
                                ${activeBookings.map((b, i) => `
                                    <div class="booking-item" onclick="window.openBookingDetail(${b.id})" style="padding:20px; cursor:pointer; transition: background 0.2s; position:relative; ${i < activeBookings.length - 1 ? 'border-bottom:1px solid rgba(255,255,255,0.06);' : ''}">
                                        <div class="booking-status-badge" style="position:absolute; top:20px; right:20px; padding:5px 12px; border-radius:30px; font-size:0.7rem; font-weight:700; text-transform:uppercase; background:${getStatusBg(b.status)}; color:#fff;">
                                            ${b.status}
                                        </div>
                                        <div style="display:flex; align-items:center; gap:15px; margin-bottom:12px;">
                                            <div style="width:40px; height:40px; background:rgba(194,38,38,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--red);">
                                                <i class="fa-solid fa-calendar-day" style="font-size:1.1rem;"></i>
                                            </div>
                                            <div>
                                                <div style="font-size:0.95rem; font-weight:700; color:#fff;">${b.event_name}</div>
                                                <div style="font-size:0.8rem; color:rgba(255,255,255,0.5);">${b.event_date} @ ${b.event_time}</div>
                                            </div>
                                        </div>
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem;">
                                            <div style="color:rgba(255,255,255,0.4);"><i class="fa-solid fa-cake-candles" style="margin-right:5px; width:15px;"></i> Type: <span style="color:#fff; text-transform:capitalize;">${b.event_type}</span></div>
                                            <div style="color:rgba(255,255,255,0.4);"><i class="fa-solid fa-users" style="margin-right:5px; width:15px;"></i> Guests: <span style="color:#fff;">${b.num_guests}</span></div>
                                            <div style="color:rgba(255,255,255,0.4); grid-column: span 2; text-overflow:ellipsis; white-space:nowrap; overflow:hidden;"><i class="fa-solid fa-location-dot" style="margin-right:5px; width:15px;"></i> ${b.address}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                    }

                    if (activeBookings.length === 0) {

                        section.innerHTML = `
                            <div class="booking-empty" style="text-align:center; padding: 40px 20px; color:rgba(255,255,255,0.4);">
                                <i class="fa-solid fa-calendar-day" style="font-size:2rem; margin-bottom:15px; display:block; opacity:0.3;"></i>
                                No active bookings found. <a href="bookbar.php" style="color:var(--red); font-weight:700;">Book now!</a>
                            </div>
                        `;
                    } else {
                        section.innerHTML = html;
                    }

                }
                return data; // Return promise result
            } catch (e) {
                console.error('Error loading bookings:', e);
            }
        }


        let _activeBooking = null;

        window.openBookingDetail = function(bookingId) {
            const booking = _userBookings.find(b => b.id == bookingId);
            if (!booking) return;
            _activeBooking = booking;

            // Check restriction (3 days before)
            const bookingDate = new Date(booking.event_date);
            const now = new Date();
            const diffTime = bookingDate - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // 3-day rule: only EDITING is blocked within 3 days
            // Cancellation is always allowed unless already cancelled
            const canEdit = diffDays >= 3 && booking.status !== 'cancelled';
            const canCancel = booking.status !== 'cancelled';
            
            // Populate View Mode
            document.getElementById('bookingDetailStatusBadge').textContent = booking.status.toUpperCase();
            document.getElementById('bookingDetailStatusBadge').style.background = getStatusBg(booking.status);
            document.getElementById('vDetailName').textContent = booking.event_name;
            document.getElementById('vDetailId').textContent = `Booking #BK-${String(booking.id).padStart(3, '0')}`;
            document.getElementById('vDetailDateTime').textContent = `${booking.event_date} @ ${booking.event_time}`;
            document.getElementById('vDetailGuests').textContent = `${booking.num_guests} Persons`;
            document.getElementById('vDetailType').textContent = booking.event_type;
            document.getElementById('vDetailAddress').textContent = booking.address;
            document.getElementById('vDetailNotes').textContent = booking.notes || 'No special requests.';

            // Always show buttons, let the logic handle the click
            document.getElementById('btnEditBooking').style.display = 'flex';
            document.getElementById('btnCancelBooking').style.display = canCancel ? 'flex' : 'none';
            // Show restriction notice if applicable
            document.getElementById('editRestrictionNotice').style.display = (!canEdit && canCancel) ? 'block' : 'none';


            // Show Overlay
            document.getElementById('bookingDetailFullscreen').classList.add('open');
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden'; // Lock HTML as well
            window.toggleEditBooking(false);
        };


        window.closeBookingDetail = function() {
            document.getElementById('bookingDetailFullscreen').classList.remove('open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
        };

        window.toggleEditBooking = function(isEdit) {
            document.getElementById('bookingDetailView').style.display = isEdit ? 'none' : 'block';
            document.getElementById('bookingEditContainer').style.display = isEdit ? 'block' : 'none';

            if (isEdit && _activeBooking) {
                // Update DatePicker restrictions dynamically to allow the CURRENT booking date
                if (window._editDatePicker) {
                    window._editDatePicker.set('disable', [
                        function(date) {
                            const today = new Date();
                            today.setHours(0,0,0,0);
                            const threeDaysOut = new Date();
                            threeDaysOut.setDate(today.getDate() + 2);
                            threeDaysOut.setHours(23,59,59,999);

                            if (date >= today && date <= threeDaysOut) {
                                if (_activeBooking && _activeBooking.event_date) {
                                    const current = new Date(_activeBooking.event_date);
                                    current.setHours(0,0,0,0);
                                    if (date.getTime() === current.getTime()) return false;
                                }
                                return true;
                            }
                            return false;
                        }
                    ]);
                }

                document.getElementById('eEventName').value = _activeBooking.event_name || '';
                
                // Set Flatpickr dates
                if (window._editDatePicker && _activeBooking.event_date) {
                    window._editDatePicker.setDate(_activeBooking.event_date, false);
                    window._editDatePicker.redraw();
                }
                if (window._editTimePicker && _activeBooking.event_time) {
                    window._editTimePicker.setDate(_activeBooking.event_time, false);
                }
                
                // Intelligent Event Type Matching
                const typeEl = document.getElementById('eEventType');
                const rawType = String(_activeBooking.event_type || '').toLowerCase();
                let matchedType = 'other';
                Array.from(typeEl.options).forEach(opt => {
                    if (rawType.includes(opt.value)) matchedType = opt.value;
                });
                typeEl.value = matchedType;
                
                // Robust guest count matching
                let rawGuests = String(_activeBooking.num_guests || '');
                let guestVal = rawGuests.replace(/[^0-9]/g, ''); // Digits only
                // If it's a range like 10-20, take the first number
                if (rawGuests.includes('-')) guestVal = rawGuests.split('-')[0].trim();
                
                // Ensure the value exists in the dropdown, else find closest
                const guestEl = document.getElementById('eNumGuests');
                if (guestVal) {
                    guestEl.value = guestVal;
                }

                document.getElementById('eAddress').value = _activeBooking.address || '';
                document.getElementById('eNotes').value = _activeBooking.notes || '';

            }
        };





        window.saveBookingEdits = async function() {
            if (!_activeBooking) return;
            
            const fields = ['eEventName','eEventDate','eEventTime','eAddress','eEventType','eNumGuests'];
            let hasError = false;
            
            fields.forEach(fid => {
                const el = document.getElementById(fid);
                const err = document.getElementById(fid + 'Error');
                if (!el.value || el.value === 'null' || el.value === '') {
                    el.classList.add('error');
                    if (err) err.classList.add('show');
                    hasError = true;
                } else {
                    el.classList.remove('error');
                    if (err) err.classList.remove('show');
                }
            });

            if (hasError) {
                showToast('Please fill in all required fields.', true);
                return;
            }

            const data = {
                action: 'update_booking',
                booking_id: _activeBooking.id,
                eventName: document.getElementById('eEventName').value.trim(),
                eventDate: document.getElementById('eEventDate').value,
                eventTime: document.getElementById('eEventTime').value,
                eventType: document.getElementById('eEventType').value,
                numGuests: document.getElementById('eNumGuests').value,
                address: document.getElementById('eAddress').value.trim(),
                notes: document.getElementById('eNotes').value.trim()
            };

            // 3-day lead time check: New date must be at least 3 days from today.
            const now = new Date();
            const newDate = new Date(data.eventDate);
            const newDiff = Math.ceil((newDate - now) / (1000 * 60 * 60 * 24));

            if (newDiff < 3 && _activeBooking.status !== 'cancelled') {
                showToast('New event date must be at least 3 days from today.', true);
                const dtEl = document.getElementById('eEventDate');
                dtEl.classList.add('error');
                const dtErr = document.getElementById('eEventDateError');
                if (dtErr) {
                    dtErr.innerText = 'Must be at least 3 days away';
                    dtErr.classList.add('show');
                }
                return;
            }

            try {
                const res = await fetch('adminSide/admin-bookings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    window.toggleEditBooking(false); // Close edit form
                    window.closeBookingDetail(); // Close detail view
                    await loadBookings(); // Refresh list
                } else {
                    showToast(result.message || 'Update failed', true);
                }


            } catch (e) {
                console.error('Update Error:', e);
                showToast('Update error: ' + e.message, true);
            }
        };




        window.openCancelBookingModal = function() {
            if (!_activeBooking) return;
            document.getElementById('cancelBookingConfirmModal').classList.add('open');

            
            document.getElementById('confirmCancelBookingBtn').onclick = async () => {
                const btn = document.getElementById('confirmCancelBookingBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                
                try {
                    const res = await fetch('adminSide/admin-bookings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update_status',
                            booking_id: _activeBooking.id,
                            status: 'cancelled'
                        })
                    });
                    const result = await res.json();
                    if (result.success) {
                        closeCancelBookingModal();
                        closeBookingDetail();
                        await loadBookings();
                    } else {
                        showToast(result.message || 'Cancellation failed', true);
                    }
                } catch (e) {
                    showToast('Network error.', true);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = 'Yes, Cancel Booking';
                }
            };
        };

        window.closeCancelBookingModal = function() {
            document.getElementById('cancelBookingConfirmModal').classList.remove('open');
        };

        function getStatusBg(status) {
            switch(status.toLowerCase()) {
                case 'confirmed': return 'linear-gradient(135deg, #16a34a, #22c55e)';
                case 'cancelled': return 'linear-gradient(135deg, #991b1b, #ef4444)';
                default: return 'linear-gradient(135deg, #9B0A1E, #C22626)'; // pending — matches site red
            }
        }
        
        // ── MAP SELECTION FOR EDITING ──
        const STORE_LOC = { lat: 14.5244, lng: 121.0559 };
        const MAX_RADIUS_KM = 5.5;

        window.initLeafletMapForEdit = function() {
            let map = null;
            let marker = null;
            
            const modalHTML = `
            <div id="mapModalOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:20000;backdrop-filter:blur(8px);">
              <div id="mapModalContent" style="background:#111;border-radius:20px;width:95%;max-width:900px;position:relative;box-shadow:0 0 50px rgba(0,0,0,1);display:flex;flex-direction:column;max-height:85vh;overflow:hidden;border: 1px solid #333;">
                <div style="padding:18px 25px;background:linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%);display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;font-family:'Aclonica',sans-serif;color:#fff;font-size:1.1rem;">📍 Select Event Location</h3>
                    <button id="closeMapBtn" style="background:rgba(0,0,0,0.3);border:none;border-radius:50%;width:32px;height:32px;color:#fff;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                
                <div style="padding:15px 25px;background:#222;border-bottom:1px solid #333;">
                  <div style="position:relative;">
                    <div style="display:flex;gap:10px;">
                        <div style="position:relative;flex:1;">
                            <i class="fas fa-map-marker-alt" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#C22626;font-size:0.9rem;"></i>
                            <input id="mapSearchInput" placeholder="Search for street, city, or venue..."
                                style="width:100%;padding:12px 16px 12px 35px;border-radius:8px;border:1px solid #444;background:#111;color:#fff;font-size:.95rem;outline:none;">
                        </div>
                        <button id="gpsLocationBtn" title="Use my current GPS" style="width:48px;background:linear-gradient(135deg,#C22626,#8B0A1E);border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                    </div>
                    <div id="mapSearchResults" style="display:none;position:absolute;top:100%;left:0;right:58px;background:#222;border:1px solid #444;border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:20001;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>

                  </div>
                </div>

                <div id="mapContainer" style="flex:1;min-height:400px;width:100%;"></div>
                
                <div style="padding:20px 25px;background:#111;border-top:1px solid #222;">
                  <button id="confirmLocationBtn" disabled style="width:100%;padding:15px;background:#333;border:none;border-radius:12px;color:#666;font-weight:700;cursor:not-allowed;">Confirm Location</button>
                </div>
              </div>
            </div>`;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            document.body.style.overflow = 'hidden';

            const closeBtn = document.getElementById('closeMapBtn');
            const confirmBtn = document.getElementById('confirmLocationBtn');
            const gpsBtn = document.getElementById('gpsLocationBtn');
            const overlay = document.getElementById('mapModalOverlay');

            const cleanup = () => {
                overlay.remove();
                document.body.style.overflow = '';
            };

            closeBtn.onclick = cleanup;
            confirmBtn.onclick = () => {
                const addr = document.getElementById('mapSearchInput').value;
                document.getElementById('eAddress').value = addr;
                cleanup();
            };

            gpsBtn.onclick = async () => {
                gpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                navigator.geolocation.getCurrentPosition(async pos => {
                    const { latitude: lat, longitude: lng } = pos.coords;
                    map.setView([lat, lng], 17);
                    if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                    await updateSelectedAddressForEdit(lat, lng);
                    gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                }, () => {
                    alert('Unable to get your location.');
                    gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                });
            };

            setTimeout(() => {
                map = L.map('mapContainer').setView([STORE_LOC.lat, STORE_LOC.lng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                L.circle([STORE_LOC.lat, STORE_LOC.lng], { radius: MAX_RADIUS_KM * 1000, color: '#C22626', fillOpacity: 0.1 }).addTo(map);

                map.on('click', async (e) => {
                    const { lat, lng } = e.latlng;
                    if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                    await updateSelectedAddressForEdit(lat, lng);
                });

                const searchInput = document.getElementById('mapSearchInput');
                const resultsBox = document.getElementById('mapSearchResults');

                searchInput.addEventListener('input', debounce(async () => {
                    const query = searchInput.value.trim();
                    if (query.length < 3) { resultsBox.style.display = 'none'; return; }
                    try {
                        const token = window.LOCATIONIQ_TOKEN || '';
                        const res = await fetch(`https://us1.locationiq.com/v1/autocomplete.php?key=${token}&q=${encodeURIComponent(query)}&limit=5&lat=${STORE_LOC.lat}&lon=${STORE_LOC.lng}`);
                        const data = await res.json();
                        if (Array.isArray(data) && data.length > 0) {
                            resultsBox.innerHTML = data.map(f => `
                                <div class="map-suggestion-item" data-lat="${f.lat}" data-lon="${f.lon}" data-addr="${f.display_name}" style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);color:#ccc;font-size:0.85rem;cursor:pointer;">
                                    <i class="fas fa-map-marker-alt" style="margin-right:8px;color:#C22626;"></i>
                                    ${f.display_name}
                                </div>
                            `).join('');
                            resultsBox.style.display = 'block';
                            resultsBox.querySelectorAll('.map-suggestion-item').forEach(item => {
                                item.onclick = () => {
                                    const lat = +item.dataset.lat; const lon = +item.dataset.lon;
                                    map.setView([lat, lon], 17);
                                    if (marker) marker.setLatLng([lat, lon]); else marker = L.marker([lat, lon]).addTo(map);
                                    searchInput.value = item.dataset.addr;
                                    updateSelectedAddressForEdit(lat, lon);
                                    resultsBox.style.display = 'none';
                                };
                            });
                        } else resultsBox.style.display = 'none';
                    } catch (e) {}
                }, 400));
            }, 100);
        };

        async function updateSelectedAddressForEdit(lat, lng) {
            const btn = document.getElementById('confirmLocationBtn');
            const searchInput = document.getElementById('mapSearchInput');
            if (!btn) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

            const R = 6371;
            const dLat = (lat - STORE_LOC.lat) * Math.PI / 180;
            const dLon = (lng - STORE_LOC.lng) * Math.PI / 180;
            const a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(STORE_LOC.lat*Math.PI/180)*Math.cos(lat*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
            const distKm = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            
            let addr = '';
            try {
                const token = window.LOCATIONIQ_TOKEN || '';
                const res = await fetch(`https://us1.locationiq.com/v1/reverse?key=${token}&lat=${lat}&lon=${lng}&format=json`);
                if (res.ok) {
                    const data = await res.json();
                    addr = data.display_name || '';
                }
            } catch (e) {}

            if (distKm > MAX_RADIUS_KM) {
                btn.innerHTML = 'Outside Delivery Radius';
                btn.style.background = '#333';
                btn.style.color = '#ef4444';
                btn.disabled = true;
            } else {
                btn.innerHTML = 'Confirm Location';
                btn.style.background = 'linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%)';
                btn.style.color = '#fff';
                btn.style.cursor = 'pointer';
                btn.disabled = false;
                if (addr) searchInput.value = addr;
            }
        }

        function debounce(func, wait) {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => func(...args), wait); };
        }

        // ── HISTORY PORTAL ──
        window.openHistory = function() {
            const overlay = document.getElementById('historyFullscreen');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden'; // Lock main scroll
            loadPastOrders(); // Load default tab
        };

        window.closeHistory = function() {
            const overlay = document.getElementById('historyFullscreen');
            overlay.classList.remove('open');
            document.body.style.overflow = ''; // Unlock
        };

        window.switchHistoryTab = function(tabId) {
            // Update Tab Buttons
            document.querySelectorAll('.history-tab').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabId);
            });
            // Update Tab Content
            document.querySelectorAll('.history-tab-content').forEach(content => {
                content.classList.toggle('active', content.id === tabId);
            });

            if (tabId === 'past-orders') loadPastOrders();
            if (tabId === 'past-bookings') loadPastBookings();
        };

        async function loadPastOrders() {
            const list = document.getElementById('pastOrdersList');
            try {
                const res = await fetch('get-order-history.php');
                const data = await res.json();
                if (!data.success) {
                    list.innerHTML = `<div class="empty-state">${data.message}</div>`;
                    return;
                }

                if (data.history.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-solid fa-receipt" style="font-size:2rem; opacity:0.2; display:block; margin-bottom:15px;"></i>
                            No past orders found.
                        </div>`;
                    return;
                }

                list.innerHTML = data.history.map(order => {
                    const date = new Date(order.created_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
                    const statusClass = order.status.toLowerCase();
                    return `
                        <div class="history-item">
                            <div class="hi-left">
                                <div class="hi-icon"><i class="fa-solid fa-box"></i></div>
                                <div class="hi-info">
                                    <h4>Order #ORD-${String(order.id).padStart(4, '0')}</h4>
                                    <div class="hi-meta">
                                        <span><i class="fa-regular fa-calendar"></i> ${date}</span>
                                        <span><i class="fa-solid fa-tag"></i> ${order.items_summary}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hi-right">
                                <div class="hi-price">₱${order.total_amount.toLocaleString()}</div>
                                <span class="hi-status ${statusClass}">${order.status}</span>
                            </div>
                        </div>
                    `;
                }).join('');

            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error loading orders.</div>';
            }
        }

        async function loadPastBookings() {
            const list = document.getElementById('pastBookingsList');
            try {
                const res = await fetch('get-booking-history.php');
                const data = await res.json();
                if (!data.success) {
                    list.innerHTML = `<div class="empty-state">${data.message}</div>`;
                    return;
                }

                if (data.history.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-solid fa-calendar-xmark" style="font-size:2rem; opacity:0.2; display:block; margin-bottom:15px;"></i>
                            No past event bookings found.
                        </div>`;
                    return;
                }

                list.innerHTML = data.history.map(booking => {
                    const date = new Date(booking.event_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
                    const statusClass = booking.status.toLowerCase();
                    return `
                        <div class="history-item">
                            <div class="hi-left">
                                <div class="hi-icon"><i class="fa-solid fa-champagne-glasses"></i></div>
                                <div class="hi-info">
                                    <h4>${booking.event_type}</h4>
                                    <div class="hi-meta">
                                        <span><i class="fa-regular fa-calendar"></i> ${date}</span>
                                        <span><i class="fa-solid fa-users"></i> ${booking.guests} Guests</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hi-right">
                                <div class="hi-price">₱${booking.total_amount.toLocaleString()}</div>
                                <span class="hi-status ${statusClass}">${booking.status}</span>
                            </div>
                        </div>
                    `;
                }).join('');

            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error loading bookings.</div>';
            }
        }

        // ── PROMO PORTAL ──
        window.openPromos = function() {
            const overlay = document.getElementById('promoFullscreen');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            handlePromoSearch(); // Initial load
        };

        window.closePromos = function() {
            const overlay = document.getElementById('promoFullscreen');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        };

        window.handlePromoSearch = async function() {
            const input = document.getElementById('promoSearchInput');
            const list = document.getElementById('promoResultsList');
            const query = input.value.trim().toUpperCase();

            try {
                const res = await fetch('promo-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'search_promos', query: query })
                });
                const data = await res.json();
                
                if (!data.success || data.promos.length === 0) {
                    list.innerHTML = `<div class="empty-state">No promo codes found matching "${query}"</div>`;
                    return;
                }

                list.innerHTML = data.promos.map(promo => `
                    <div class="promo-ticket-item" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; display: flex; overflow: hidden; position: relative; min-height: 110px; margin-bottom: 15px; padding: 0;">
                        <div class="ticket-stub" style="width: 100px; background: var(--red); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; border-right: 2px dashed rgba(255,255,255,0.3); padding: 15px;">
                            <i class="fa-solid fa-ticket" style="font-size: 1.6rem; margin-bottom: 6px;"></i>
                            <span style="font-weight: 800; font-size: 0.95rem;">${promo.discount_percent}%</span>
                        </div>
                        <div class="ticket-main" style="flex: 1; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="font-family: 'Aclonica', sans-serif; color: #fff; margin-bottom: 6px; letter-spacing: 1px; font-size: 1.1rem;">${promo.code}</h4>
                                <div style="color: var(--muted); font-size: 0.78rem; margin-bottom: 4px;">
                                    <i class="fa-solid fa-tag" style="margin-right: 6px; color: var(--red);"></i>Applies to: ${promo.applicable_category}
                                </div>
                                <div style="color: var(--muted); font-size: 0.75rem;">
                                    <i class="fa-solid fa-clock" style="margin-right: 6px;"></i>
                                    ${promo.duration_days ? promo.duration_days + ' Days Validity' : 'No Expiration'}
                                </div>
                            </div>
                            ${promo.is_claimed ? 
                                '<span style="color: #22c55e; font-weight: 800; font-size: 0.8rem; letter-spacing: 1px; padding: 8px 12px; background: rgba(34,197,94,0.1); border-radius: 8px; margin-left: 15px; white-space: nowrap;">CLAIMED</span>' : 
                                `<button class="btn-save" onclick="claimPromo(${promo.id}, '${promo.code}')" style="padding: 12px 24px; font-size: 0.85rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(194,38,38,0.25); margin-left: 15px; white-space: nowrap;">CLAIM</button>`
                            }
                        </div>
                        <!-- Ticket Notches -->
                        <div style="position: absolute; left: 88px; top: -12px; width: 24px; height: 24px; background: #0a0a0a; border-radius: 50%; border: 1px solid rgba(255,255,255,0.08);"></div>
                        <div style="position: absolute; left: 88px; bottom: -12px; width: 24px; height: 24px; background: #0a0a0a; border-radius: 50%; border: 1px solid rgba(255,255,255,0.08);"></div>
                    </div>
                `).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error searching promos.</div>';
            }
        };

        window.claimPromo = async function(id, code) {
            try {
                const res = await fetch('promo-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'claim_promo', promo_id: id })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Promo ${code} claimed!`);
                    localStorage.setItem('active_promo', code);
                    localStorage.setItem('promo_discount', data.discount);
                    localStorage.setItem('promo_category', data.applicable_category);
                    handlePromoSearch();
                } else {
                    showToast(data.message, true);
                }
            } catch (e) {
                showToast('Error claiming promo.', true);
            }
        };

        window.claimNewUserPromo = function() {
            closeModal('newUserPromoModal');
            openPromos();
            document.getElementById('promoSearchInput').value = 'N3WUS3R';
            handlePromoSearch();
        };

        // Check for new user on init
        setTimeout(() => {
            const hasSeen = localStorage.getItem('new_user_promo_seen');
            if (!hasSeen) {
                openModal('newUserPromoModal');
                localStorage.setItem('new_user_promo_seen', 'true');
            }
        }, 3000);