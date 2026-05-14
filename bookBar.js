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
    // ─── Time range pickers (start / end) + optional per-day times ─────────────
    const fpTimeOpts = {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'h:i K',
        time_24hr: false,
        theme: 'dark',
        disableMobile: true,
        defaultHour: 13,
        defaultMinute: 0,
    };

    const fpMainStart = flatpickr('#eventTimeStart', {
        ...fpTimeOpts,
        defaultHour: 13,
        onChange: () => {
            clearError('eventTimeStart');
            clearError('eventTimeEnd');
            syncMainHiddenEventTime();
        },
    });
    const fpMainEnd = flatpickr('#eventTimeEnd', {
        ...fpTimeOpts,
        defaultHour: 20,
        onChange: () => {
            clearError('eventTimeStart');
            clearError('eventTimeEnd');
            syncMainHiddenEventTime();
        },
    });
    fpMainStart.setDate(new Date(2020, 0, 1, 13, 0), false);
    fpMainEnd.setDate(new Date(2020, 0, 1, 20, 0), false);
    syncMainHiddenEventTime();

    function syncMainHiddenEventTime() {
        const s = document.getElementById('eventTimeStart')?.value?.trim() || '';
        const e = document.getElementById('eventTimeEnd')?.value?.trim() || '';
        const h = document.getElementById('eventTime');
        if (h) h.value = s && e ? `${s} – ${e}` : '';
    }

    /** Parse "h:mm AM/PM" to minutes from midnight */
    function parseTimeToMinutes(str) {
        if (!str) return null;
        const m = String(str).trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (!m) return null;
        let h = parseInt(m[1], 10);
        const min = parseInt(m[2], 10);
        const ap = m[3].toUpperCase();
        if (ap === 'PM' && h < 12) h += 12;
        if (ap === 'AM' && h === 12) h = 0;
        return h * 60 + min;
    }

    function validateStartEndOrder(startStr, endStr) {
        const a = parseTimeToMinutes(startStr);
        const b = parseTimeToMinutes(endStr);
        if (a === null || b === null) return { ok: false, msg: 'Please use valid start and end times.' };
        if (b <= a) return { ok: false, msg: 'End time must be after start time.' };
        return { ok: true };
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    let __perDayFlatpickrs = [];

    function destroyPerDayPickers() {
        __perDayFlatpickrs.forEach((fp) => {
            try {
                fp.destroy();
            } catch (_) {}
        });
        __perDayFlatpickrs = [];
        const wrap = document.getElementById('perDayTimeRows');
        if (wrap) wrap.innerHTML = '';
    }

    function renderPerDayTimeRows(dates) {
        destroyPerDayPickers();
        const wrap = document.getElementById('perDayTimeRows');
        if (!wrap) return;
        dates.forEach((ymd) => {
            const label = new Date(ymd + 'T12:00:00').toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
            const idS = `dayTimeStart_${ymd}`;
            const idE = `dayTimeEnd_${ymd}`;
            const row = document.createElement('div');
            row.className = 'per-day-time-row';
            row.style.cssText =
                'display:grid;grid-template-columns:minmax(160px,1fr) 1fr 1fr;gap:12px;align-items:end;margin-bottom:12px;padding:12px;background:rgba(0,0,0,0.25);border-radius:10px;border:1px solid rgba(255,255,255,0.06);';
            row.innerHTML = `
                <div style="font-size:0.85rem;font-weight:600;color:#fff;padding-bottom:4px;">${label}</div>
                <label class="form-label" style="margin:0;"><span style="font-size:0.72rem;color:rgba(255,255,255,0.5);">Start</span>
                    <input type="text" id="${idS}" class="form-input" readonly></label>
                <label class="form-label" style="margin:0;"><span style="font-size:0.72rem;color:rgba(255,255,255,0.5);">End</span>
                    <input type="text" id="${idE}" class="form-input" readonly></label>`;
            wrap.appendChild(row);
            const fpS = flatpickr(`#${idS}`, {
                ...fpTimeOpts,
                onChange: () => {
                    clearError(idS);
                    clearError(idE);
                },
            });
            const fpE = flatpickr(`#${idE}`, {
                ...fpTimeOpts,
                onChange: () => {
                    clearError(idS);
                    clearError(idE);
                },
            });
            const baseStart = fpMainStart.selectedDates[0] || new Date(2020, 0, 1, 13, 0);
            const baseEnd = fpMainEnd.selectedDates[0] || new Date(2020, 0, 1, 20, 0);
            fpS.setDate(baseStart, false);
            fpE.setDate(baseEnd, false);
            __perDayFlatpickrs.push(fpS, fpE);
        });
    }

    function getSelectedDatesArray() {
        return document
            .getElementById('eventDate')
            .value.split(',')
            .map((d) => d.trim())
            .filter(Boolean);
    }

    window.onBookingDatesChanged = function (dates) {
        const sameRow = document.getElementById('sameTimeAllDaysRow');
        const perWrap = document.getElementById('perDayTimeContainer');
        const sameCb = document.getElementById('sameTimeAllDays');
        if (!sameRow || !perWrap) return;
        if (dates.length > 1) {
            sameRow.style.display = 'block';
            const same = sameCb ? sameCb.checked : true;
            if (!same) {
                perWrap.style.display = 'block';
                renderPerDayTimeRows(dates);
            } else {
                perWrap.style.display = 'none';
                destroyPerDayPickers();
            }
        } else {
            sameRow.style.display = 'none';
            perWrap.style.display = 'none';
            destroyPerDayPickers();
        }
    };

    const sameTimeCb = document.getElementById('sameTimeAllDays');
    if (sameTimeCb) {
        sameTimeCb.addEventListener('change', () => {
            window.onBookingDatesChanged(getSelectedDatesArray());
        });
    }

    // Initialize Time Picker (date picker is now handled by BookingCalendar in bookbar.php)

    // ─── Leaflet Map Logic ──────────────────────────────────────────────────────
    let map = null;
    let marker = null;
    const STORE_LOC = { lat: 14.5244, lng: 121.0559 };
    const MAX_RADIUS_KM = 5.5;


    window.continueBooking = function() {
        document.getElementById('duplicateBookingModal').style.display = 'none';
        showSummaryPopup();
    };

    window.closeDuplicateModal = function() {
        document.getElementById('duplicateBookingModal').style.display = 'none';
    };

    // Restore submit button state on modal close or error
    document.addEventListener('click', (e) => {
        if (e.target.closest('.cancel-btn')) {
            const submitBtn = document.querySelector('#bookingForm button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Book Luke\'s Seafood Bar';
            }
        }
    });


    // ── Expose map trigger globally (called from bookbar.php button) ──
    window.initLeafletMap = function () {
        document.getElementById('mapModalOverlay')?.remove();
        // Clear any previous map coordinates when opening a new map
        const addressEl = document.getElementById('address');
        delete addressEl.dataset.confirmedLat;
        delete addressEl.dataset.confirmedLng;
        initMapModal();
    };

    function initMapModal() {

        marker = null;
        const modalHTML = `
        <div id="mapModalOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);">
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
                <div id="mapSearchResults" style="display:none;position:absolute;top:100%;left:0;right:58px;background:#222;border:1px solid #444;border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:10000;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
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

        document.getElementById('closeMapBtn').onclick = closeMapModal;
        document.getElementById('confirmLocationBtn').onclick = confirmLocation;

        const gpsBtn = document.getElementById('gpsLocationBtn');
        gpsBtn.onclick = async () => {
            gpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            navigator.geolocation.getCurrentPosition(async pos => {
                const { latitude: lat, longitude: lng } = pos.coords;
                map.setView([lat, lng], 17);
                if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                await updateSelectedAddress(lat, lng);
                gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
            }, () => {
                alert('Unable to get your location.');
                gpsBtn.innerHTML = '<i class="fas fa-crosshairs"></i>';
            });
        };

        setTimeout(() => {
            map = L.map('mapContainer').setView([STORE_LOC.lat, STORE_LOC.lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

            // 1. Delivery Radius Circle
            L.circle([STORE_LOC.lat, STORE_LOC.lng], { radius: MAX_RADIUS_KM * 1000, color: '#C22626', fillOpacity: 0.1 }).addTo(map);

            // 2. LocationIQ Geocoder Control (Autocomplete)
            const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
            if (typeof L.Control.geocoder === 'function') {
                const geocoder = L.Control.geocoder(token, {
                    placeholder: "Search for street, city, or venue...",
                    expanded: true,
                    position: 'topright'
                }).addTo(map);

                geocoder.on('select', function(e) {
                    const { lat, lng } = e.latlng;
                    const addr = e.feature.name;
                    map.setView([lat, lng], 17);
                    if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                    const searchInput = document.getElementById('mapSearchInput');
                    if (searchInput) searchInput.value = addr;
                    updateSelectedAddress(lat, lng);
                });
            } else {
                console.warn('LocationIQ Geocoder library not loaded yet.');
            }


            map.on('click', async (e) => {
                const { lat, lng } = e.latlng;
                if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
                await updateSelectedAddress(lat, lng);
            });

            const searchInput = document.getElementById('mapSearchInput');
            const resultsBox = document.getElementById('mapSearchResults');

            searchInput.addEventListener('input', debounce(async () => {
                const query = searchInput.value.trim();
                if (query.length < 3) { resultsBox.style.display = 'none'; return; }
                try {
                    const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
                    const res = await fetch(`https://us1.locationiq.com/v1/autocomplete.php?key=${token}&q=${encodeURIComponent(query)}&limit=5&lat=${STORE_LOC.lat}&lon=${STORE_LOC.lng}`);
                    const data = await res.json();

                    if (Array.isArray(data) && data.length > 0) {
                        resultsBox.innerHTML = data.map(f => {
                            const name = f.display_name;
                            return `
                                <div class="map-suggestion-item" data-lat="${f.lat}" data-lon="${f.lon}" data-addr="${name}" style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.05);color:#ccc;font-size:0.85rem;cursor:pointer;transition:all 0.2s;">
                                    <i class="fas fa-map-marker-alt" style="margin-right:8px;color:#C22626;"></i>
                                    ${name}
                                </div>
                            `;
                        }).join('');
                        resultsBox.style.display = 'block';
                        resultsBox.querySelectorAll('.map-suggestion-item').forEach(item => {
                            item.onclick = () => {
                                const lat = +item.dataset.lat; const lon = +item.dataset.lon; const addr = item.dataset.addr;
                                map.setView([lat, lon], 17);
                                if (marker) marker.setLatLng([lat, lon]); else marker = L.marker([lat, lon]).addTo(map);
                                searchInput.value = addr;
                                updateSelectedAddress(lat, lon);
                                resultsBox.style.display = 'none';
                            };
                        });
                    } else resultsBox.style.display = 'none';
                } catch (e) { console.error('LocationIQ search failed', e); }

            }, 400));
        }, 100);
    }

    function debounce(func, wait) {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(() => func(...args), wait); };
    }

    async function validateAddressWithinRadius(address) {
        const addressEl = document.getElementById('address');
        const trimmed = (address || '').trim();
        if (!trimmed) return { ok: false, message: 'Please select or enter an address.' };
        
        // If the address was confirmed from the map, use the stored coordinates
        if (addressEl.dataset.confirmedLat && addressEl.dataset.confirmedLng) {
            console.log('Using stored map coordinates:', {lat: addressEl.dataset.confirmedLat, lng: addressEl.dataset.confirmedLng});
            const lat = parseFloat(addressEl.dataset.confirmedLat);
            const lng = parseFloat(addressEl.dataset.confirmedLng);
            const R = 6371;
            const dLat = (lat - STORE_LOC.lat) * Math.PI / 180;
            const dLon = (lng - STORE_LOC.lng) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(STORE_LOC.lat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const distKm = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            
            console.log('Distance from store:', distKm, 'Max allowed:', MAX_RADIUS_KM);
            if (distKm > MAX_RADIUS_KM) {
                return { ok: false, message: `Location is too far (${distKm.toFixed(1)}km). Max radius is ${MAX_RADIUS_KM}km.` };
            }
            return { ok: true };
        }
        
        console.log('No stored map coordinates, attempting to geocode address:', trimmed);
        // If no map confirmation, validate by geocoding the address text
        if (trimmed.length < 8) return { ok: false, message: 'Please enter a more complete address.' };

        try {
            const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
            const res = await fetch(`https://us1.locationiq.com/v1/search?key=${token}&q=${encodeURIComponent(trimmed)}&format=json&limit=1`);
            const data = await res.json();
            const f = data && data.length ? data[0] : null;
            if (!f) return { ok: false, message: 'Address not recognized. Please use the map.' };

            const lon = parseFloat(f.lon);
            const lat = parseFloat(f.lat);
            const R = 6371;
            const dLat = (lat - STORE_LOC.lat) * Math.PI / 180;
            const dLon = (lon - STORE_LOC.lng) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(STORE_LOC.lat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const distKm = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            if (distKm > MAX_RADIUS_KM) {
                return { ok: false, message: `Location is too far (${distKm.toFixed(1)}km). Max radius is ${MAX_RADIUS_KM}km.` };
            }
            return { ok: true };

        } catch (e) {
            return { ok: false, message: 'Unable to verify address. Please use the map.' };
        }
    }

    async function updateSelectedAddress(lat, lng) {
        const btn = document.getElementById('confirmLocationBtn');
        const searchInput = document.getElementById('mapSearchInput');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

        const R = 6371;
        const dLat = (lat - STORE_LOC.lat) * Math.PI / 180;
        const dLon = (lng - STORE_LOC.lng) * Math.PI / 180;
        const a_dist = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(STORE_LOC.lat*Math.PI/180)*Math.cos(lat*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
        const distKm = R * 2 * Math.atan2(Math.sqrt(a_dist), Math.sqrt(1-a_dist));
        const isOutside = distKm > MAX_RADIUS_KM;

        let addr = '';

        try {
            const token = window.LOCATIONIQ_TOKEN || 'YOUR_API_KEY';
            const res = await fetch(`https://us1.locationiq.com/v1/reverse?key=${token}&lat=${lat}&lon=${lng}&format=json`);
            if (res.ok) {
                const data = await res.json();
                addr = data.display_name || '';
            }
        } catch (e) { console.warn('LocationIQ Reverse failed'); }


        if (!addr) addr = 'Pinned Location';

        if (searchInput) searchInput.value = addr;
        btn._address = addr;
        btn._lat = lat;
        btn._lng = lng;

        if (isOutside) {
            btn.disabled = true;
            btn.style.background = '#444';
            btn.style.color = '#888';
            btn.style.cursor = 'not-allowed';
            btn.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Too Far (${distKm.toFixed(1)}km)`;
        } else {
            btn.disabled = false;
            btn.style.background = 'linear-gradient(135deg,#C22626,#8B0A1E)';
            btn.style.color = '#fff';
            btn.style.cursor = 'pointer';
            btn.innerHTML = 'Confirm Selection';
        }
    }

    function closeMapModal() {
        document.getElementById('mapModalOverlay')?.remove();
        if (map) map.remove();
        map = null;
        document.body.style.overflow = '';
    }
    function confirmLocation() {
        const btn = document.getElementById('confirmLocationBtn');
        const addr = btn._address;
        const lat = btn._lat;
        const lng = btn._lng;
        
        console.log('confirmLocation called with:', { addr, lat, lng });
        
        if (addr) {
            document.getElementById('address').value = addr;
            // Store the coordinates from the map selection
            if (lat !== undefined && lng !== undefined) {
                document.getElementById('address').dataset.confirmedLat = lat;
                document.getElementById('address').dataset.confirmedLng = lng;
                console.log('Stored coordinates:', { lat, lng });
            }
            clearError('address');
        }
        closeMapModal();
    }

    // ─── Validation Logic ───────────────────────────────────────────────────────
    const fields = [
        { id: 'eventName', type: 'text', msg: 'Event name is required' },
        { id: 'address', type: 'text', msg: 'Please provide or select an address' },
        { id: 'eventDate', type: 'text', msg: 'Please select a date' },
        { id: 'eventTimeStart', type: 'text', msg: 'Select a start time' },
        { id: 'eventTimeEnd', type: 'text', msg: 'Select an end time' },
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
            el.addEventListener('input', () => {
                clearError(f.id);
                // If user manually types in address, clear the map coordinates
                if (f.id === 'address') {
                    delete el.dataset.confirmedLat;
                    delete el.dataset.confirmedLng;
                }
            });
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
    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = bookingForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
        }

        let isValid = true;

        // Basic field validation
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            if (!el) return;
            const val = el.value.trim();

            let fieldError = false;
            if (!val) fieldError = true;
            else if (f.type === 'phone' && (val.replace(/\D/g, '').length !== 11 || !val.startsWith('09'))) fieldError = true;
            else if (f.type === 'email' && (!val.includes('@') || !val.includes('.'))) fieldError = true;

            if (fieldError) {
                showError(f.id, f.msg);
                isValid = false;
            } else {
                clearError(f.id);
            }
        });

        const tStart0 = document.getElementById('eventTimeStart')?.value?.trim() || '';
        const tEnd0 = document.getElementById('eventTimeEnd')?.value?.trim() || '';
        const tr0 = validateStartEndOrder(tStart0, tEnd0);
        if (!tr0.ok) {
            showError('eventTimeStart', tr0.msg);
            showError('eventTimeEnd', tr0.msg);
            isValid = false;
        }

        const datesMulti = getSelectedDatesArray();
        const sameAll =
            !document.getElementById('sameTimeAllDays') || document.getElementById('sameTimeAllDays').checked;
        if (isValid && datesMulti.length > 1 && !sameAll) {
            for (const ymd of datesMulti) {
                const ds = document.getElementById(`dayTimeStart_${ymd}`)?.value?.trim();
                const de = document.getElementById(`dayTimeEnd_${ymd}`)?.value?.trim();
                if (!ds || !de) {
                    showNotification(`Select start and end time for each selected day (${ymd}).`, 'error');
                    isValid = false;
                    break;
                }
                const vr = validateStartEndOrder(ds, de);
                if (!vr.ok) {
                    showNotification(`${vr.msg} (${ymd})`, 'error');
                    isValid = false;
                    break;
                }
            }
        }

        // Strict Address/Radius validation (like the cart)
        if (isValid) {
            const addr = document.getElementById('address').value.trim();
            const addrResult = await validateAddressWithinRadius(addr);
            if (!addrResult.ok) {
                showError('address', addrResult.message);
                isValid = false;
            }
        }

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Book Luke\'s Seafood Bar';
        }

        if (isValid) {

            // Check for existing active bookings
            try {
                const res = await fetch('get-bookings.php');
                const data = await res.json();
                if (data.success && data.bookings.length > 0) {
                    const active = data.bookings.filter(b => b.status !== 'cancelled');
                    if (active.length > 0) {
                        document.getElementById('duplicateBookingModal').style.display = 'flex';
                        return; // Stop here, wait for modal choice
                    }
                }
            } catch (e) { console.error('Booking check failed', e); }

            showSummaryPopup();
        }
        else {

            const firstError = document.querySelector('.error-message.show') || document.querySelector('.error');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    function buildEventTimeSummaryHtml() {
        const dates = getSelectedDatesArray();
        const sameAll =
            !document.getElementById('sameTimeAllDays') || document.getElementById('sameTimeAllDays').checked;
        const s = document.getElementById('eventTimeStart')?.value?.trim() || '';
        const e = document.getElementById('eventTimeEnd')?.value?.trim() || '';
        const main = escHtml(`${s} – ${e}`);
        if (dates.length <= 1 || sameAll) return main;
        const parts = dates.map((ymd) => {
            const ds = document.getElementById(`dayTimeStart_${ymd}`)?.value?.trim() || '';
            const de = document.getElementById(`dayTimeEnd_${ymd}`)?.value?.trim() || '';
            const lab = new Date(ymd + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            return `${escHtml(lab)}: ${escHtml(ds)} – ${escHtml(de)}`;
        });
        return parts.join('<br>');
    }

    function showSummaryPopup() {
        const rawDate = document.getElementById('eventDate').value;
        const selectedDates = rawDate.split(',').map(d => d.trim()).filter(Boolean);
        let eventDateLabel = rawDate;
        if (selectedDates.length) {
            eventDateLabel = selectedDates.map(dateStr => {
                const d = new Date(dateStr + 'T12:00:00');
                return isNaN(d.getTime())
                    ? dateStr
                    : d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            }).join(', ');
        } else if (rawDate && /^\d{4}-\d{2}-\d{2}$/.test(rawDate.trim())) {
            const d = new Date(rawDate.trim() + 'T12:00:00');
            if (!isNaN(d.getTime())) {
                eventDateLabel = d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            }
        }
        const timeHtml = buildEventTimeSummaryHtml();
        const data = {
            eventName: escHtml(document.getElementById('eventName').value),
            address: escHtml(document.getElementById('address').value),
            eventDate: escHtml(eventDateLabel),
            eventTimeHtml: timeHtml,
            eventType: escHtml(document.getElementById('eventType').value),
            numGuests: escHtml(document.getElementById('numGuests').value),
            fullName: escHtml(document.getElementById('fullName').value),
            contactNumber: escHtml(document.getElementById('contactNumber').value),
            emailAddress: escHtml(document.getElementById('emailAddress').value),
            notes: escHtml(document.getElementById('notes').value || 'N/A')
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
                        <div class="summary-row"><span class="summary-label">Time:</span><span class="summary-value">${data.eventTimeHtml}</span></div>
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
        document.body.style.overflow = 'hidden';
    }

    window.closeSummaryPopup = () => {
        document.getElementById('summaryPopup')?.remove();
        document.body.style.overflow = '';
    };

    window.confirmSubmission = function () {
        closeSummaryPopup();
        if (!window.__isLoggedIn) { showNotification('Please log in first', 'error'); return; }

        const selectedDates = document.getElementById('eventDate').value.split(',').map(d => d.trim()).filter(Boolean);
        const tStart = document.getElementById('eventTimeStart').value.trim();
        const tEnd = document.getElementById('eventTimeEnd').value.trim();
        const tr = validateStartEndOrder(tStart, tEnd);
        if (!tr.ok) {
            showNotification(tr.msg, 'error');
            return;
        }

        const sameTimeAllDays =
            !document.getElementById('sameTimeAllDays') || document.getElementById('sameTimeAllDays').checked;

        let event_times_by_date = null;
        if (selectedDates.length > 1 && !sameTimeAllDays) {
            event_times_by_date = {};
            for (const ymd of selectedDates) {
                const ds = document.getElementById(`dayTimeStart_${ymd}`)?.value?.trim();
                const de = document.getElementById(`dayTimeEnd_${ymd}`)?.value?.trim();
                if (!ds || !de) {
                    showNotification(`Select start and end time for each day (${ymd}).`, 'error');
                    return;
                }
                const vr = validateStartEndOrder(ds, de);
                if (!vr.ok) {
                    showNotification(`${vr.msg} (${ymd})`, 'error');
                    return;
                }
                event_times_by_date[ymd] = { start: ds, end: de };
            }
        }

        const combinedTime = `${tStart} – ${tEnd}`;
        const formData = {
            eventName: document.getElementById('eventName').value,
            fullName: document.getElementById('fullName').value,
            contactNumber: document.getElementById('contactNumber').value,
            emailAddress: document.getElementById('emailAddress').value,
            eventDate: document.getElementById('eventDate').value,
            eventDates: selectedDates,
            eventTime: combinedTime,
            eventTimeStart: tStart,
            eventTimeEnd: tEnd,
            eventType: document.getElementById('eventType').value,
            numGuests: document.getElementById('numGuests').value,
            address: document.getElementById('address').value,
            notes: document.getElementById('notes').value || 'N/A',
            userEmail: sessionStorage.getItem('user_email'),
            userName: sessionStorage.getItem('user_name')
        };

        if (!formData.eventName || !formData.fullName || !formData.contactNumber || !formData.emailAddress || !formData.eventDate || !formData.eventTimeStart || !formData.eventTimeEnd || !formData.eventType || !formData.numGuests || !formData.address) {
            showNotification('Please fill in all required fields', 'error');
            return;
        }

        // Format date for database (convert from display format to Y-m-d)
        const formatDateForDB = (dateStr) => {
            if (!dateStr) return '';
            // Handle format like "April 15, 2025" or "2025-04-15"
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) {
                // Try parsing as Y-m-d
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    return dateStr; // Already in Y-m-d format
                }
                return '';
            }
            return date.toISOString().split('T')[0]; // Returns Y-m-d
        };

        // Prepare data for the booking API, which enforces the 2-bookings-per-day limit.
        const submissionData = {
            action: 'create',
            booking_data: {
                event_name: formData.eventName,
                full_name: formData.fullName,
                contact_number: formData.contactNumber,
                email_address: formData.emailAddress,
                event_date: formatDateForDB(formData.eventDates[0] || formData.eventDate),
                event_dates: formData.eventDates.map(formatDateForDB).filter(Boolean),
                event_time: combinedTime,
                event_time_start: formData.eventTimeStart,
                event_time_end: formData.eventTimeEnd,
                same_time_all_days: sameTimeAllDays,
                event_times_by_date: event_times_by_date,
                event_type: formData.eventType,
                num_guests: formData.numGuests,
                address: formData.address,
                notes: formData.notes,
                user_email: formData.userEmail,
                user_name: formData.userName
            }
        };

        console.log('Submitting booking data:', submissionData);

        // Send booking data to server
        fetch('booking-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify(submissionData)
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || data.message || `HTTP error! status: ${response.status}`);
            }

            return data;
        })
        .then(data => {
            if (data.success) {
                showNotification('Booking sent!', 'success');
                bookingForm.reset();
                fpMainStart.setDate(new Date(2020, 0, 1, 13, 0), false);
                fpMainEnd.setDate(new Date(2020, 0, 1, 20, 0), false);
                syncMainHiddenEventTime();
                if (document.getElementById('sameTimeAllDays')) document.getElementById('sameTimeAllDays').checked = true;
                destroyPerDayPickers();
                if (window.bookingCalendar && typeof window.bookingCalendar.loadAvailability === 'function') {
                    window.bookingCalendar.loadAvailability().then(() => window.bookingCalendar.renderCalendar());
                }
                openInfoModal(
                    "WE CUSTOMIZE ACCORDING TO YOUR PREFERENCE AND BUDGET.",
                    "After booking, our team will call you to confirm your preferences, total budget, and booking details."
                );
                const okayBtn = document.querySelector('.info-btn-okay');
                if (okayBtn) {
                    okayBtn.onclick = () => {
                        window.location.href = 'account-dashboard.php';
                    };
                }
            } else {
                showNotification(data.error || data.message || 'Error', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(error.message || 'Failed to submit booking. Please try again.', 'error');
        });
    };

    // ─── Info Modal Logic ───────────────────────────────────────────────────────
    const infoModal = document.getElementById('infoModal');
    const helpIcon = document.getElementById('formHelpIcon');

    window.openInfoModal = (customTitle, customBody) => {
        // If called via event listener, ignore the event object
        if (customTitle instanceof Event) customTitle = null;

        if (infoModal) {
            const titleEl = infoModal.querySelector('h3');
            const bodyEl = infoModal.querySelector('.info-modal-body p');

            if (customTitle) {
                titleEl.textContent = customTitle;
            } else {
                titleEl.textContent = "WE CUSTOMIZE ACCORDING TO YOUR PREFERENCE AND BUDGET";
            }

            if (customBody) {
                bodyEl.innerHTML = customBody;
            } else {
                bodyEl.innerHTML = "After booking, our team will call you to confirm your preferences, total budget, and booking details. If you have any questions before booking, please contact us at <strong>09392999912</strong>.";
            }

            // Reset the button action to default close (in case it was changed by booking)
            const okayBtn = infoModal.querySelector('.info-btn-okay');
            if (!customBody && okayBtn) {
                okayBtn.onclick = closeInfoModal;
            }

            infoModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeInfoModal = () => {
        if (infoModal) {
            infoModal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    if (helpIcon) {
        helpIcon.addEventListener('click', openInfoModal);
    }

    // Auto-show logic (Once only)
    const infoShown = localStorage.getItem('booking_info_shown');
    if (!infoShown) {
        setTimeout(openInfoModal, 1000); // Show after 1s delay for better impact
        localStorage.setItem('booking_info_shown', 'true');
    }

    // Live Address Validation
    const addrInput = document.getElementById('address');
    if (addrInput) {
        addrInput.addEventListener('input', debounce(async function () {
            const addr = this.value.trim();
            if (!addr) { clearError('address'); return; }
            if (addr.length < 8) return; // Wait for more typing

            const result = await validateAddressWithinRadius(addr);
            if (!result.ok) {
                // Show error but don't block hard yet (handled on submit)
                const errorEl = document.getElementById('address-error');
                if (errorEl) errorEl.textContent = result.message;
            } else {
                clearError('address');
            }
        }, 800));
    }

    function showNotification(message, type = 'success') {
        const n = document.createElement('div');
        // Premium Top-Center Design (Slim & Pill-shaped)
        n.style.cssText = `
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

        n.innerHTML = `
            <div style="display:flex; align-items:center; gap:8px;">
                <i class="fas ${iconClass}" style="color: ${iconColor}; font-size: 1rem;"></i>
                <span style="font-size: 0.85rem; font-weight: 500; letter-spacing: 0.01em;">${message}</span>
            </div>
        `;



        document.body.appendChild(n);

        // Animate in
        requestAnimationFrame(() => {
            n.style.opacity = '1';
            n.style.transform = 'translate(-50%, 0) scale(1)';
        });

        // Auto-hide
        setTimeout(() => {
            n.style.opacity = '0';
            n.style.transform = 'translate(-50%, -20px) scale(0.9)';
            setTimeout(() => {
                n.remove();
            }, 400);
        }, 3500);

    }

});
