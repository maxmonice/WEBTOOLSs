// =====================================================
//  booking-calendar.js — Color-coded calendar for bookings
//  - Green: Available (< 2 bookings)
//  - Red: Fully booked (2 bookings)
//  - Unselectable when red
// =====================================================

class BookingCalendar {
    constructor(elementId, options = {}) {
        this.element = document.getElementById(elementId);
        this.apiUrl = options.apiUrl || 'booking-api.php';
        this.onDateSelect = options.onDateSelect || null;
        this.multiSelect = !!options.multiSelect;
        /** When true (default), today and past dates are not selectable — only strict future Y-m-d. */
        this.onlyFutureDates = options.onlyFutureDates !== false;
        this.availability = {};
        this.selectedDate = null;
        this.selectedDates = [];
        this.currentMonth = new Date();
        this.init();
    }

    /** Local calendar date as YYYY-MM-DD (avoids UTC off-by-one). */
    getTodayYmdLocal() {
        const t = new Date();
        const y = t.getFullYear();
        const m = String(t.getMonth() + 1).padStart(2, '0');
        const d = String(t.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    isSelectableByDateRule(dateStr) {
        if (!this.onlyFutureDates) return true;
        return dateStr > this.getTodayYmdLocal();
    }

    /** Local Y-m-d for a Date (never use toISOString() for calendar ranges — UTC shifts the day). */
    formatLocalYmd(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    /** Remove today/past from selection; returns whether anything changed. */
    prunePastSelectionsIfNeeded() {
        if (!this.onlyFutureDates) return false;
        const todayStr = this.getTodayYmdLocal();
        const snap = JSON.stringify({ dates: this.selectedDates, one: this.selectedDate });
        this.selectedDates = this.selectedDates.filter((d) => d > todayStr);
        this.selectedDate = this.selectedDates[0] || null;
        if (!this.multiSelect) {
            this.selectedDates = this.selectedDate ? [this.selectedDate] : [];
        }
        return snap !== JSON.stringify({ dates: this.selectedDates, one: this.selectedDate });
    }

    async init() {
        // Load initial availability
        await this.loadAvailability();
        this.renderCalendar();
    }

    async loadAvailability() {
        try {
            const y = this.currentMonth.getFullYear();
            const mo = this.currentMonth.getMonth();
            const startDate = new Date(y, mo, 1);
            const endDate = new Date(y, mo + 1, 0);
            const startStr = this.formatLocalYmd(startDate);
            const endStr = this.formatLocalYmd(endDate);

            const response = await fetch(
                `${this.apiUrl}?action=availability&start_date=${encodeURIComponent(startStr)}&end_date=${encodeURIComponent(endStr)}`
            );
            
            if (!response.ok) throw new Error('Failed to load availability');
            
            const data = await response.json();
            if (data.success) {
                this.availability = data.availability;
            }
        } catch (error) {
            console.error('Error loading availability:', error);
        }
    }

    renderCalendar() {
        if (this.onlyFutureDates) {
            const pruned = this.prunePastSelectionsIfNeeded();
            if (pruned && this.onDateSelect) {
                const d0 = this.selectedDates[0] || '';
                this.onDateSelect(d0, 0, this.selectedDates.slice());
            }
        }

        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth();
        
        // Clear element
        this.element.innerHTML = '';
        
        // Header
        const header = document.createElement('div');
        header.className = 'booking-calendar-header';
        header.innerHTML = `
            <button class="nav-btn prev" onclick="bookingCalendar.previousMonth()">← Prev</button>
            <h3>${this.currentMonth.toLocaleString('default', { month: 'long', year: 'numeric' })}</h3>
            <button class="nav-btn next" onclick="bookingCalendar.nextMonth()">Next →</button>
        `;
        this.element.appendChild(header);
        
        // Weekday headers
        const weekdayHeader = document.createElement('div');
        weekdayHeader.className = 'booking-calendar-weekdays';
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        weekdays.forEach(day => {
            const dayEl = document.createElement('div');
            dayEl.className = 'weekday';
            dayEl.textContent = day;
            weekdayHeader.appendChild(dayEl);
        });
        this.element.appendChild(weekdayHeader);
        
        // Calendar days
        const daysContainer = document.createElement('div');
        daysContainer.className = 'booking-calendar-days';
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Previous month's days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayEl = document.createElement('div');
            dayEl.className = 'day other-month';
            dayEl.textContent = daysInPrevMonth - i;
            daysContainer.appendChild(dayEl);
        }
        
        // Current month's days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEl = document.createElement('div');
            dayEl.className = 'day';
            dayEl.dataset.date = dateStr;

            const availData = this.availability[dateStr];

            if (availData) {
                dayEl.classList.add(`color-${availData.color}`);
                if (!availData.available) {
                    dayEl.classList.add('disabled');
                }
            }

            dayEl.innerHTML = `
                <div class="day-number">${day}</div>
                ${availData ? `<div class="day-count">${availData.count}/2</div>` : ''}
            `;

            const isFuture = this.isSelectableByDateRule(dateStr);
            if (
                isFuture &&
                (this.selectedDates.includes(dateStr) || this.selectedDate === dateStr)
            ) {
                dayEl.classList.add('selected');
            }

            if (!isFuture) {
                dayEl.classList.add('past-or-today');
                dayEl.setAttribute('aria-disabled', 'true');
                dayEl.title = 'Only future dates can be booked';
            } else if (availData && availData.available) {
                dayEl.style.cursor = 'pointer';
            } else if (!availData) {
                dayEl.style.cursor = 'pointer';
            }

            daysContainer.appendChild(dayEl);
        }

        const self = this;
        daysContainer.addEventListener('click', function bookingCalendarDayClick(ev) {
            const cell = ev.target.closest('.day[data-date]');
            if (!cell || !daysContainer.contains(cell)) return;
            if (cell.classList.contains('past-or-today')) return;
            if (cell.classList.contains('disabled')) return;
            const ds = cell.getAttribute('data-date');
            if (!ds || !self.isSelectableByDateRule(ds)) return;
            self.selectDate(ds, cell);
        });
        
        // Next month's days
        const remainingDays = 42 - (firstDay + daysInMonth);
        for (let day = 1; day <= remainingDays; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'day other-month';
            dayEl.textContent = day;
            daysContainer.appendChild(dayEl);
        }
        
        this.element.appendChild(daysContainer);
    }

    selectDate(dateStr, dayEl) {
        if (!this.isSelectableByDateRule(dateStr)) {
            return;
        }

        const availData = this.availability[dateStr];
        
        if (availData && !availData.available) {
            alert('This date is fully booked. Please select another date.');
            return;
        }
        
        if (this.multiSelect) {
            if (this.selectedDates.includes(dateStr)) {
                this.selectedDates = this.selectedDates.filter(d => d !== dateStr);
                dayEl.classList.remove('selected');
            } else {
                this.selectedDates.push(dateStr);
                this.selectedDates.sort();
                dayEl.classList.add('selected');
            }
            this.selectedDate = this.selectedDates[0] || null;
        } else {
            this.selectedDate = dateStr;
            this.selectedDates = [dateStr];

            document.querySelectorAll('.booking-calendar-days .day').forEach(el => {
                el.classList.remove('selected');
            });

            dayEl.classList.add('selected');
        }
        
        if (this.onDateSelect) {
            this.onDateSelect(dateStr, availData?.count || 0, this.selectedDates.slice());
        }
    }

    previousMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
        this.loadAvailability().then(() => this.renderCalendar());
    }

    nextMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
        this.loadAvailability().then(() => this.renderCalendar());
    }
}

// =====================================================
//  STAR RATING COMPONENT
// =====================================================
class StarRating {
    constructor(containerId, onRate = null) {
        this.container = document.getElementById(containerId);
        this.onRate = onRate;
        this.rating = 0;
        this.render();
    }

    render() {
        this.container.innerHTML = '';
        this.container.className = 'star-rating';
        
        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.className = `star ${i <= this.rating ? 'filled' : 'empty'}`;
            star.innerHTML = '★';
            star.style.cursor = 'pointer';
            star.style.fontSize = '1.8rem';
            star.style.marginRight = '8px';
            star.style.transition = 'all 0.2s';
            star.style.color = i <= this.rating ? '#FFD700' : '#ddd';
            
            star.addEventListener('click', () => this.setRating(i));
            star.addEventListener('mouseover', () => {
                star.style.transform = 'scale(1.2)';
                // Highlight up to hovered star
                this.container.querySelectorAll('.star').forEach((s, idx) => {
                    if (idx < i) {
                        s.style.color = '#FFD700';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
            star.addEventListener('mouseout', () => {
                star.style.transform = 'scale(1)';
                this.render();
            });
            
            this.container.appendChild(star);
        }
    }

    setRating(value) {
        this.rating = value;
        this.render();
        if (this.onRate) {
            this.onRate(value);
        }
    }

    getRating() {
        return this.rating;
    }

    reset() {
        this.rating = 0;
        this.render();
    }
}

// =====================================================
//  DELIVERY CONFIRMATION MODAL (Dark Theme)
// =====================================================
class DeliveryRatingModal {
    constructor(orderId, riderId, riderName = '', onSubmit = null) {
        this.orderId = orderId;
        this.riderId = riderId;
        this.riderName = riderName;
        this.onSubmit = onSubmit;
        this.riderRating = 0;
        this.foodRatingInstances = {}; // { menuItemId: StarRating instance }
        this.modalElement = null;
    }

    show(orderItems = []) {
        // Remove any existing modal
        const existing = document.querySelector('.delivery-rating-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.className = 'delivery-rating-modal';
        modal.style.cssText = `
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.82);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: drm-fadeIn 0.25s ease;
        `;
        this.modalElement = modal;

        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        const content = document.createElement('div');
        content.style.cssText = `
            background: #222;
            border: 1px solid rgba(194,38,38,0.4);
            border-radius: 20px;
            padding: 0;
            max-width: 560px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            animation: drm-slideUp 0.3s cubic-bezier(.16,1,.3,1);
            position: relative;
            box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        `;

        // Red accent bar
        const accent = document.createElement('div');
        accent.style.cssText = `
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(to right, #8B0A1E, #C22626);
            border-radius: 20px 20px 0 0;
        `;
        content.appendChild(accent);

        // Header
        const headerWrap = document.createElement('div');
        headerWrap.style.cssText = `
            display: flex; align-items: center; justify-content: space-between;
            padding: 28px 28px 18px; border-bottom: 1px solid rgba(255,255,255,0.07);
        `;
        const header = document.createElement('h2');
        header.style.cssText = `
            margin: 0; color: #fff; font-family: 'Aclonica', sans-serif;
            font-size: 1.25rem; display: flex; align-items: center; gap: 10px;
        `;
        header.innerHTML = '<i class="fa-solid fa-star" style="color:#FFD700;font-size:1rem;"></i> Rate Your Experience';
        headerWrap.appendChild(header);

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        closeBtn.style.cssText = `
            width: 32px; height: 32px; border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.5); cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 0.85rem;
            transition: all 0.2s;
        `;
        closeBtn.addEventListener('click', () => modal.remove());
        closeBtn.addEventListener('mouseover', () => { closeBtn.style.color = '#fff'; closeBtn.style.borderColor = '#fff'; });
        closeBtn.addEventListener('mouseout', () => { closeBtn.style.color = 'rgba(255,255,255,0.5)'; closeBtn.style.borderColor = 'rgba(255,255,255,0.1)'; });
        headerWrap.appendChild(closeBtn);
        content.appendChild(headerWrap);

        const body = document.createElement('div');
        body.style.cssText = 'padding: 24px 28px;';

        // ── Rider Rating Section ──
        const riderSection = document.createElement('div');
        riderSection.style.cssText = `
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; padding: 20px; margin-bottom: 24px;
        `;

        const riderHeader = document.createElement('div');
        riderHeader.style.cssText = 'display: flex; align-items: center; gap: 12px; margin-bottom: 16px;';
        riderHeader.innerHTML = `
            <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#C22626,#8B0A1E);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">🏍️</div>
            <div>
                <div style="font-weight:700;color:#fff;font-size:0.95rem;">Delivery Rider</div>
                <div style="font-size:0.82rem;color:rgba(255,255,255,0.5);">${this.riderName || 'Your Rider'}</div>
            </div>
        `;
        riderSection.appendChild(riderHeader);

        const riderLabel = document.createElement('div');
        riderLabel.style.cssText = 'font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.4);margin-bottom:10px;';
        riderLabel.textContent = 'TAP TO RATE';
        riderSection.appendChild(riderLabel);

        const riderRatingContainer = document.createElement('div');
        riderRatingContainer.id = 'drm-rider-rating';
        riderRatingContainer.style.cssText = 'margin-bottom: 16px;';
        riderSection.appendChild(riderRatingContainer);

        const riderComment = document.createElement('textarea');
        riderComment.id = 'drm-rider-comment';
        riderComment.placeholder = 'Share your experience with the rider (optional)...';
        riderComment.style.cssText = `
            width: 100%; padding: 11px 14px; background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08); border-radius: 8px;
            color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.88rem;
            resize: vertical; min-height: 70px; outline: none; transition: border-color 0.2s;
        `;
        riderComment.addEventListener('focus', () => { riderComment.style.borderColor = 'rgba(194,38,38,0.5)'; });
        riderComment.addEventListener('blur', () => { riderComment.style.borderColor = 'rgba(255,255,255,0.08)'; });
        riderSection.appendChild(riderComment);

        body.appendChild(riderSection);

        // ── Food Rating Section ──
        if (orderItems.length > 0) {
            const foodSection = document.createElement('div');
            foodSection.style.cssText = 'margin-bottom: 8px;';

            const foodTitle = document.createElement('div');
            foodTitle.style.cssText = `
                font-size:0.75rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;
                color:rgba(255,255,255,0.4);margin-bottom:14px;display:flex;align-items:center;gap:8px;
            `;
            foodTitle.innerHTML = '<i class="fa-solid fa-utensils" style="color:#C22626;font-size:0.8rem;"></i> RATE FOOD ITEMS';
            foodSection.appendChild(foodTitle);

            orderItems.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.style.cssText = `
                    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
                    border-radius: 10px; padding: 16px; margin-bottom: 12px;
                `;

                const itemHeader = document.createElement('div');
                itemHeader.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;';
                const itemName = document.createElement('span');
                itemName.textContent = item.name || `Item #${item.id}`;
                itemName.style.cssText = 'font-weight:600;color:#fff;font-size:0.9rem;';
                itemHeader.appendChild(itemName);
                itemDiv.appendChild(itemHeader);

                const ratingContainer = document.createElement('div');
                ratingContainer.id = `drm-food-rating-${item.id}`;
                ratingContainer.style.cssText = 'margin-bottom: 10px;';
                itemDiv.appendChild(ratingContainer);

                const comment = document.createElement('textarea');
                comment.id = `drm-food-comment-${item.id}`;
                comment.placeholder = 'How was this item? (optional)';
                comment.style.cssText = `
                    width: 100%; padding: 9px 12px; background: rgba(255,255,255,0.04);
                    border: 1px solid rgba(255,255,255,0.06); border-radius: 6px;
                    color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.82rem;
                    resize: vertical; min-height: 50px; outline: none; transition: border-color 0.2s;
                `;
                comment.addEventListener('focus', () => { comment.style.borderColor = 'rgba(194,38,38,0.4)'; });
                comment.addEventListener('blur', () => { comment.style.borderColor = 'rgba(255,255,255,0.06)'; });
                itemDiv.appendChild(comment);

                foodSection.appendChild(itemDiv);
            });

            body.appendChild(foodSection);
        }

        content.appendChild(body);

        // ── Action Buttons ──
        const footer = document.createElement('div');
        footer.style.cssText = `
            display: flex; gap: 10px; padding: 0 28px 24px;
        `;

        const cancelBtn2 = document.createElement('button');
        cancelBtn2.textContent = 'Skip';
        cancelBtn2.style.cssText = `
            flex: 1; padding: 12px; background: transparent;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
            color: rgba(255,255,255,0.5); font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        `;
        cancelBtn2.addEventListener('mouseover', () => { cancelBtn2.style.color = '#fff'; cancelBtn2.style.borderColor = 'rgba(255,255,255,0.3)'; });
        cancelBtn2.addEventListener('mouseout', () => { cancelBtn2.style.color = 'rgba(255,255,255,0.5)'; cancelBtn2.style.borderColor = 'rgba(255,255,255,0.1)'; });
        cancelBtn2.addEventListener('click', () => modal.remove());
        footer.appendChild(cancelBtn2);

        const submitBtn = document.createElement('button');
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> Submit Ratings';
        submitBtn.style.cssText = `
            flex: 1; padding: 12px; background: linear-gradient(135deg, #C22626, #8B0A1E);
            border: none; border-radius: 10px; color: #fff;
            font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.88rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s; letter-spacing: 0.02em;
        `;
        submitBtn.addEventListener('mouseover', () => { submitBtn.style.opacity = '0.9'; submitBtn.style.boxShadow = '0 6px 24px rgba(194,38,38,0.45)'; });
        submitBtn.addEventListener('mouseout', () => { submitBtn.style.opacity = '1'; submitBtn.style.boxShadow = 'none'; });
        submitBtn.addEventListener('click', async () => {
            const success = await this.submitRatings(orderItems);
            if (success) modal.remove();
        });
        footer.appendChild(submitBtn);

        content.appendChild(footer);
        modal.appendChild(content);
        document.body.appendChild(modal);

        // ── Initialize Star Ratings ──
        // Rider stars
        new StarRating('drm-rider-rating', (rating) => {
            this.riderRating = rating;
        });

        // Food item stars
        orderItems.forEach(item => {
            const containerId = `drm-food-rating-${item.id}`;
            if (document.getElementById(containerId)) {
                this.foodRatingInstances[item.id] = new StarRating(containerId, (rating) => {
                    // Rating is tracked in the StarRating instance
                });
            }
        });

        // Add animation keyframes
        if (!document.getElementById('drm-animations')) {
            const style = document.createElement('style');
            style.id = 'drm-animations';
            style.textContent = `
                @keyframes drm-fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes drm-slideUp {
                    from { opacity: 0; transform: translateY(28px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    async submitRatings(orderItems = []) {
        if (this.riderRating === 0) {
            // Show inline error instead of alert
            const ratingEl = document.getElementById('drm-rider-rating');
            if (ratingEl) {
                let errEl = ratingEl.parentElement.querySelector('.drm-error');
                if (!errEl) {
                    errEl = document.createElement('div');
                    errEl.className = 'drm-error';
                    errEl.style.cssText = 'color:#ff6b6b;font-size:0.8rem;font-weight:600;margin-top:6px;';
                    ratingEl.parentElement.insertBefore(errEl, ratingEl.nextSibling);
                }
                errEl.textContent = '⚠ Please rate the rider before submitting.';
            }
            return false;
        }

        try {
            // Submit rider rating
            const riderResponse = await fetch('ratings-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'rate-rider',
                    order_id: this.orderId,
                    rider_id: this.riderId,
                    rating: this.riderRating,
                    comment: document.getElementById('drm-rider-comment')?.value || ''
                })
            });

            if (!riderResponse.ok) throw new Error('Failed to submit rider rating');

            // Submit food ratings using tracked StarRating instances
            for (const item of orderItems) {
                const starInstance = this.foodRatingInstances[item.id];
                if (starInstance) {
                    const rating = starInstance.getRating();
                    if (rating > 0) {
                        await fetch('ratings-api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                action: 'rate-food',
                                order_id: this.orderId,
                                menu_item_id: item.id,
                                rating: rating,
                                comment: document.getElementById(`drm-food-comment-${item.id}`)?.value || ''
                            })
                        });
                    }
                }
            }

            if (this.onSubmit) {
                this.onSubmit();
            }

            return true;
        } catch (error) {
            console.error('Error submitting ratings:', error);
            alert('Failed to submit ratings. Please try again.');
            return false;
        }
    }
}
