// Mobile menu toggle
        const menuToggle = document.getElementById('mobile-menu');
        const navMenu = document.getElementById('navMenu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
        }

        // Password visibility toggle
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Validate passwords match
        function validatePasswords() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('passwordErrorMsg');
            const errorText = document.getElementById('passwordErrorText');

            if (newPassword !== confirmPassword) {
                errorMsg.classList.add('visible');
                errorText.textContent = 'Passwords do not match.';
                return false;
            }

            if (newPassword.length < 8) {
                errorMsg.classList.add('visible');
                errorText.textContent = 'Password must be at least 8 characters long.';
                return false;
            }

            errorMsg.classList.remove('visible');
            return true;
        }