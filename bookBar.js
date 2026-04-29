document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('navMenu');
    const navLinks = document.querySelectorAll('.nav-menu a');

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

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        });
    });

    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });

    // Initialize Date Picker
    flatpickr("#eventDate", {
        dateFormat: "F j, Y",
        minDate: "today",
        theme: "dark",
        disableMobile: true,
    });

    // Initialize Time Picker
    flatpickr("#eventTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "h:i K",
        time_24hr: false,
        theme: "dark",
        disableMobile: true,
    });

    // Email Validation
    const emailInput = document.getElementById('emailAddress');
    const emailError = document.getElementById('emailError');

    function validateEmail(email) {
        // Check if email is empty
        if (!email || email.trim() === '') {
            return 'Email address is required';
        }
        
        // Check if email contains @
        if (!email.includes('@')) {
            return 'Email must include "@" symbol';
        }
        
        // Split email by @
        const parts = email.split('@');
        
        // Check if there's text before @
        if (parts[0].trim() === '') {
            return 'Email must have a username before "@"';
        }
        
        // Check if there's a domain after @
        if (parts.length < 2 || parts[1].trim() === '') {
            return 'Email must include a domain after "@"';
        }
        
        // Check if domain contains a dot
        if (!parts[1].includes('.')) {
            return 'Email domain must include "." (e.g., gmail.com)';
        }
        
        // Check if there's text after the last dot
        const domainParts = parts[1].split('.');
        if (domainParts[domainParts.length - 1].trim() === '') {
            return 'Email domain must be complete (e.g., .com, .net)';
        }
        
        return null; // No error
    }

    // Phone Number Validation
    const phoneInput = document.getElementById('contactNumber');
    const phoneError = document.getElementById('phoneError');

    function validatePhilippinePhone(phone) {
        // Remove any spaces or dashes
        const cleanPhone = phone.replace(/[\s-]/g, '');
        
        // Check if empty
        if (!cleanPhone || cleanPhone.trim() === '') {
            return 'Contact number is required';
        }
        
        // Check if it contains only numbers
        if (!/^\d+$/.test(cleanPhone)) {
            return 'Contact number must contain only digits';
        }
        
        // Philippine mobile numbers must be 11 digits starting with 09
        if (cleanPhone.length !== 11) {
            return 'Mobile number must be 11 digits (e.g., 09XX XXX XXXX)';
        }
        
        // Must start with 09
        if (!cleanPhone.startsWith('09')) {
            return 'Mobile number must start with 09';
        }
        
        return null; // No error
    }

    // Auto-format phone number as user types
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
        
        // Limit to 11 digits
        if (value.length > 11) {
            value = value.slice(0, 11);
        }
        
        e.target.value = value;
        
        // Clear error on input
        phoneError.classList.remove('show');
        phoneInput.classList.remove('error');
    });

    // Validate phone on blur
    phoneInput.addEventListener('blur', function() {
        const errorMessage = validatePhilippinePhone(this.value);
        
        if (errorMessage) {
            phoneError.textContent = errorMessage;
            phoneError.classList.add('show');
            phoneInput.classList.add('error');
        } else {
            phoneError.classList.remove('show');
            phoneInput.classList.remove('error');
        }
    });

    // Real-time validation on blur
    emailInput.addEventListener('blur', function() {
        const errorMessage = validateEmail(this.value);
        
        if (errorMessage) {
            emailError.textContent = errorMessage;
            emailError.classList.add('show');
            emailInput.classList.add('error');
        } else {
            emailError.classList.remove('show');
            emailInput.classList.remove('error');
        }
    });

    // Clear error on input
    emailInput.addEventListener('input', function() {
        emailError.classList.remove('show');
        emailInput.classList.remove('error');
    });

    // Form submission validation
    const bookingForm = document.getElementById('bookingForm');
    
    bookingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate phone number
        const phoneErrorMessage = validatePhilippinePhone(phoneInput.value);
        if (phoneErrorMessage) {
            phoneError.textContent = phoneErrorMessage;
            phoneError.classList.add('show');
            phoneInput.classList.add('error');
            phoneInput.focus();
            return false;
        }
        
        // Validate email on submit
        const emailErrorMessage = validateEmail(emailInput.value);
        if (emailErrorMessage) {
            emailError.textContent = emailErrorMessage;
            emailError.classList.add('show');
            emailInput.classList.add('error');
            emailInput.focus();
            return false;
        }
        
        // If validation passes, show summary popup
        showSummaryPopup();
    });

    // Function to show summary popup
    function showSummaryPopup() {
        // Get all form values
        const formData = {
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

        // Format event type for display
        const eventTypeLabels = {
            'wedding': 'Wedding',
            'birthday': 'Birthday Party',
            'corporate': 'Corporate Event',
            'anniversary': 'Anniversary',
            'graduation': 'Graduation',
            'reunion': 'Reunion',
            'conference': 'Conference',
            'seminar': 'Seminar/Workshop',
            'teambuilding': 'Team Building',
            'holiday': 'Holiday Party',
            'other': 'Other'
        };

        // Create popup HTML
        const popupHTML = `
            <div class="popup-overlay" id="summaryPopup">
                <div class="popup-content">
                    <h2 class="popup-title">Booking Summary</h2>
                    <div class="summary-section">
                        <h3>Event Details</h3>
                        <div class="summary-row">
                            <span class="summary-label">Event Name:</span>
                            <span class="summary-value">${formData.eventName}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Address:</span>
                            <span class="summary-value">${formData.address}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Date:</span>
                            <span class="summary-value">${formData.eventDate}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Time:</span>
                            <span class="summary-value">${formData.eventTime}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Event Type:</span>
                            <span class="summary-value">${eventTypeLabels[formData.eventType]}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Number of Guests:</span>
                            <span class="summary-value">${formData.numGuests}</span>
                        </div>
                    </div>
                    <div class="summary-section">
                        <h3>Contact Details</h3>
                        <div class="summary-row">
                            <span class="summary-label">Full Name:</span>
                            <span class="summary-value">${formData.fullName}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Contact Number:</span>
                            <span class="summary-value">${formData.contactNumber}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Email:</span>
                            <span class="summary-value">${formData.emailAddress}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Notes/Request:</span>
                            <span class="summary-value">${formData.notes}</span>
                        </div>
                    </div>
                    <div class="popup-buttons">
                        <button type="button" class="popup-btn cancel-btn" onclick="closeSummaryPopup()">Edit</button>
                        <button type="button" class="popup-btn confirm-btn" onclick="confirmSubmission()">Confirm & Submit</button>
                    </div>
                </div>
            </div>
        `;

        // Add popup to body
        document.body.insertAdjacentHTML('beforeend', popupHTML);
    }

    // Global functions for popup buttons
    window.closeSummaryPopup = function() {
        const popup = document.getElementById('summaryPopup');
        if (popup) {
            popup.remove();
        }
    };

    window.confirmSubmission = function() {
        // Close popup
        closeSummaryPopup();
        
        // Check if user is logged in
        if (!window.__isLoggedIn) {
            showNotification('Please log in to submit a booking', 'error');
            return;
        }
        
        // Get form data
        const formData = {
            eventName: document.getElementById('eventName').value,
            address: document.getElementById('address').value,
            eventDate: document.getElementById('eventDate').value,
            eventTime: document.getElementById('eventTime').value,
            eventType: document.getElementById('eventType').value,
            numGuests: document.getElementById('numGuests').value,
            fullName: document.getElementById('fullName').value,
            contactNumber: document.getElementById('contactNumber').value,
            emailAddress: document.getElementById('emailAddress').value,
            notes: document.getElementById('notes').value || 'N/A',
            userEmail: sessionStorage.getItem('user_email') || 'guest@example.com',
            userName: sessionStorage.getItem('user_name') || 'Guest User'
        };
        
        // Validate required fields
        if (!formData.eventName || !formData.fullName || !formData.contactNumber || !formData.emailAddress || !formData.eventDate || !formData.eventTime || !formData.eventType || !formData.numGuests || !formData.address) {
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
        
        // Prepare data for submission
        const submissionData = {
            action: 'create_booking',
            eventName: formData.eventName,
            fullName: formData.fullName,
            contactNumber: formData.contactNumber,
            emailAddress: formData.emailAddress,
            eventDate: formatDateForDB(formData.eventDate),
            eventTime: formData.eventTime,
            eventType: formData.eventType,
            numGuests: formData.numGuests,
            address: formData.address,
            notes: formData.notes,
            userEmail: formData.userEmail,
            userName: formData.userName
        };
        
        console.log('Submitting booking data:', submissionData);
        
        // Send booking data to server
        fetch('admin-bookings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(submissionData)
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message
                showNotification('Booking request sent successfully!', 'success');
                
                // Reset form
                bookingForm.reset();
                
                // Update admin dashboard stats
                updateAdminStats();
                
                // Redirect to confirmation page after delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification(data.message || 'Failed to submit booking', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to submit booking. Please try again.', 'error');
        });
    };

    // Notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#22c55e' : '#ef4444'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Update admin stats function
    function updateAdminStats() {
        // This would typically fetch updated stats from the server
        console.log('Updating admin dashboard stats...');
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .popup-content {
            background: #222;
            border: 1px solid rgba(194,38,38,0.4);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .popup-title {
            color: #fff;
            font-family: 'Aclonica', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary-section {
            margin-bottom: 20px;
        }
        .summary-section h3 {
            color: #C22626;
            font-family: 'Aclonica', sans-serif;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .summary-label {
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }
        .summary-value {
            color: #fff;
            font-weight: 600;
        }
        .popup-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .popup-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .cancel-btn {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .cancel-btn:hover {
            background: rgba(255,255,255,0.1);
        }
        .confirm-btn {
            background: linear-gradient(135deg, #C22626, #8B0A1E);
            color: #fff;
        }
        .confirm-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    `;
    document.head.appendChild(style);
});
