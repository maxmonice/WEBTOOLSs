document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('navMenu');
    const navLinks = document.querySelectorAll('.nav-menu a');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            navMenu.classList.toggle('active');

            const icon = mobileMenuBtn.querySelector('i');
            if (navMenu.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn?.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (navMenu && !navMenu.contains(e.target) && mobileMenuBtn && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    });

    // ─── Auto-fill Logic ─────────────────────────────────────────────────────────
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('emailAddress');
    
    const storedName = sessionStorage.getItem('user_name');
    const storedEmail = sessionStorage.getItem('user_email');
    
    if (storedName && fullNameInput && !fullNameInput.value) fullNameInput.value = storedName;
    if (storedEmail && emailInput && !emailInput.value) emailInput.value = storedEmail;

    // ─── Date & Time Pickers ────────────────────────────────────────────────────
    flatpickr("#eventDate", {
        dateFormat: "F j, Y",
        minDate: "today",
        theme: "dark",
        disableMobile: true,
        onChange: () => clearError('eventDate')
    });

    flatpickr("#eventTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "h:i K",
        time_24hr: false,
        theme: "dark",
        disableMobile: true,
        onChange: () => clearError('eventTime')
    });

    // ─── Leaflet Map Logic ──────────────────────────────────────────────────────
    let map = null;
    let marker = null;
    const STORE_LOC = { lat: 14.5244, lng: 121.0559 };
    const MAX_RADIUS_KM = 5.5;

    window.initLeafletMap = function() {
        document.getElementById('mapModalOverlay')?.remove();
        initMapModal();
    };

    function initMapModal() {
        const modalHTML = `
        <div id="mapModalOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);">
          <div id="mapModalContent" style="background:#111;border-radius:20px;width:95%;max-width:900px;position:relative;box-shadow:0 0 50px rgba(0,0,0,1);display:flex;flex-direction:column;max-height:85vh;overflow:hidden;border: 1px solid #C22626;">
            <div style="padding:18px 25px;background:linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-family:'Aclonica',sans-serif;color:#fff;font-size:1.1rem;">📍 Select Event Location</h3>
                <button id="closeMapBtn" style="background:rgba(0,0,0,0.3);border:none;border-radius:50%;width:32px;height:32px;color:#fff;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="mapContainer" style="flex:1;min-height:400px;width:100%;"></div>
            <div style="padding:20px 25px;background:#111;border-top:1px solid #222;">
              <button id="confirmLocationBtn" disabled style="width:100%;padding:15px;background:#333;border:none;border-radius:12px;color:#666;font-weight:700;cursor:not-allowed;">Confirm Location</button>
            </div>
          </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        document.getElementById('closeMapBtn').onclick = closeMapModal;
        document.getElementById('confirmLocationBtn').onclick = confirmLocation;

        setTimeout(() => {
            map = L.map('mapContainer').setView([STORE_LOC.lat, STORE_LOC.lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            L.circle([STORE_LOC.lat, STORE_LOC.lng], { radius: MAX_RADIUS_KM * 1000, color: '#C22626', fillOpacity: 0.1 }).addTo(map);

            map.on('click', async (e) => {
                const { lat, lng } = e.latlng;
                if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                await updateSelectedAddress(lat, lng);
            });
        }, 100);
    }

    async function updateSelectedAddress(lat, lng) {
        const btn = document.getElementById('confirmLocationBtn');
        try {
            const res = await fetch(`https://photon.komoot.io/reverse?lat=${lat}&lon=${lng}`);
            const data = await res.json();
            const p = data.features?.[0]?.properties;
            const addr = p ? [p.name, p.street, p.city].filter(Boolean).join(', ') : `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            btn.disabled = false;
            btn.style.background = 'linear-gradient(135deg,#C22626,#8B0A1E)';
            btn.style.color = '#fff';
            btn.style.cursor = 'pointer';
            btn._address = addr;
        } catch (e) { btn.disabled = true; }
    }

    function closeMapModal() { document.getElementById('mapModalOverlay')?.remove(); if (map) map.remove(); map = null; }
    function confirmLocation() {
        const addr = document.getElementById('confirmLocationBtn')._address;
        if (addr) {
            document.getElementById('address').value = addr;
            clearError('address');
        }
        closeMapModal();
    }

    // ─── Validation Logic ───────────────────────────────────────────────────────
    const fields = [
        { id: 'eventName', type: 'text', msg: 'Event name is required' },
        { id: 'address', type: 'text', msg: 'Please provide or select an address' },
        { id: 'eventDate', type: 'text', msg: 'Please select a date' },
        { id: 'eventTime', type: 'text', msg: 'Please select a time' },
        { id: 'eventType', type: 'select', msg: 'Please select an event type' },
        { id: 'numGuests', type: 'select', msg: 'Please select the number of guests' },
        { id: 'fullName', type: 'text', msg: 'Full name is required' },
        { id: 'contactNumber', type: 'phone', msg: 'Valid 11-digit mobile number required' },
        { id: 'emailAddress', type: 'email', msg: 'Valid email address required' }
    ];

    function clearError(id) {
        const input = document.getElementById(id);
        const error = document.getElementById(id + 'Error');
        if (input) input.classList.remove('error');
        if (error) error.classList.remove('show');
    }

    function showError(id, msg) {
        const input = document.getElementById(id);
        const error = document.getElementById(id + 'Error');
        if (input) input.classList.add('error');
        if (error) {
            error.textContent = msg;
            error.classList.add('show');
        }
    }

    // Add real-time listeners to clear errors
    fields.forEach(f => {
        const el = document.getElementById(f.id);
        if (el) {
            el.addEventListener('input', () => clearError(f.id));
            el.addEventListener('change', () => clearError(f.id));
            el.addEventListener('blur', () => {
                const val = el.value.trim();
                if (!val) showError(f.id, f.msg);
                else if (f.type === 'phone' && (val.length !== 11 || !val.startsWith('09'))) showError(f.id, f.msg);
                else if (f.type === 'email' && !val.includes('@')) showError(f.id, f.msg);
            });
        }
    });

    const bookingForm = document.getElementById('bookingForm');
    bookingForm.addEventListener('submit', (e) => {
        e.preventDefault();
        let isValid = true;
        
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            if (!el) return;
            const val = el.value.trim();
            
            let fieldError = false;
            if (!val) fieldError = true;
            else if (f.type === 'phone' && (val.replace(/\D/g,'').length !== 11 || !val.startsWith('09'))) fieldError = true;
            else if (f.type === 'email' && (!val.includes('@') || !val.includes('.'))) fieldError = true;

            if (fieldError) {
                showError(f.id, f.msg);
                isValid = false;
            } else {
                clearError(f.id);
            }
        });

        if (isValid) showSummaryPopup();
        else {
            const firstError = document.querySelector('.error-message.show');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    function showSummaryPopup() {
        const data = {
            eventName: document.getElementById('eventName').value,
            address: document.getElementById('address').value,
            eventDate: document.getElementById('eventDate').value,
            eventTime: document.getElementById('eventTime').value,
            eventType: document.getElementById('eventType').value,
            numGuests: document.getElementById('numGuests').value,
            fullName: document.getElementById('fullName').value,
            contactNumber: document.getElementById('contactNumber').value,
            emailAddress: document.getElementById('emailAddress').value,
            notes: document.getElementById('notes').value || 'N/A'
        };

        const popupHTML = `
            <div class="popup-overlay" id="summaryPopup">
                <div class="popup-content">
                    <h2 class="popup-title">Booking Summary</h2>
                    <div class="summary-section">
                        <h3>Event Details</h3>
                        <div class="summary-row"><span class="summary-label">Name:</span><span class="summary-value">${data.eventName}</span></div>
                        <div class="summary-row"><span class="summary-label">Address:</span><span class="summary-value">${data.address}</span></div>
                        <div class="summary-row"><span class="summary-label">Date:</span><span class="summary-value">${data.eventDate}</span></div>
                        <div class="summary-row"><span class="summary-label">Time:</span><span class="summary-value">${data.eventTime}</span></div>
                        <div class="summary-row"><span class="summary-label">Type:</span><span class="summary-value">${data.eventType}</span></div>
                        <div class="summary-row"><span class="summary-label">Guests:</span><span class="summary-value">${data.numGuests}</span></div>
                    </div>
                    <div class="summary-section">
                        <h3>Contact Details</h3>
                        <div class="summary-row"><span class="summary-label">Full Name:</span><span class="summary-value">${data.fullName}</span></div>
                        <div class="summary-row"><span class="summary-label">Phone:</span><span class="summary-value">${data.contactNumber}</span></div>
                        <div class="summary-row"><span class="summary-label">Email:</span><span class="summary-value">${data.emailAddress}</span></div>
                        <div class="summary-row"><span class="summary-label">Notes:</span><span class="summary-value">${data.notes}</span></div>
                    </div>
                    <div class="popup-buttons">
                        <button class="popup-btn cancel-btn" onclick="closeSummaryPopup()">Edit</button>
                        <button class="popup-btn confirm-btn" onclick="confirmSubmission()">Confirm & Submit</button>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', popupHTML);
    }

    window.closeSummaryPopup = () => document.getElementById('summaryPopup')?.remove();

    window.confirmSubmission = function() {
        closeSummaryPopup();
        if (!window.__isLoggedIn) { showNotification('Please log in first', 'error'); return; }

        const submissionData = {
            action: 'create_booking',
            eventName: document.getElementById('eventName').value,
            fullName: document.getElementById('fullName').value,
            contactNumber: document.getElementById('contactNumber').value,
            emailAddress: document.getElementById('emailAddress').value,
            eventDate: document.getElementById('eventDate').value,
            eventTime: document.getElementById('eventTime').value,
            eventType: document.getElementById('eventType').value,
            numGuests: document.getElementById('numGuests').value,
            address: document.getElementById('address').value,
            notes: document.getElementById('notes').value || 'N/A',
            userEmail: sessionStorage.getItem('user_email'),
            userName: sessionStorage.getItem('user_name')
        };

        fetch('admin-bookings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(submissionData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification('Booking sent!', 'success');
                bookingForm.reset();
                setTimeout(() => {
                    window.location.href = 'account-dashboard.php';
                }, 1500);

            } else {
                showNotification(d.message || 'Error', 'error');
            }
        })
        .catch(() => showNotification('Submission failed', 'error'));
    };

    function showNotification(message, type = 'success') {
        const n = document.createElement('div');
        n.style.cssText = `position:fixed;top:20px;right:20px;background:${type === 'success' ? '#22c55e' : '#ef4444'};color:white;padding:15px 25px;border-radius:12px;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,0.3);font-weight:600;`;
        n.textContent = message;
        document.body.appendChild(n);
        setTimeout(() => n.remove(), 2500);
    }
});
