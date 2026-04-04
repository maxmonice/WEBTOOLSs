document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
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
        
        // Show success message
        alert('Form submitted successfully! We will contact you soon.');
        
        // Reset form
        bookingForm.reset();
        
        // Here you would normally send the form data to your server
        // bookingForm.submit();
    };
});