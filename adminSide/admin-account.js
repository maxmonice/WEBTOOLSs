function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        function toggleNotifications() {
            const menu = document.getElementById('notificationMenu');
            menu.classList.toggle('show');
            
            document.addEventListener('click', function closeNotifications(e) {
                if (!e.target.closest('.notification-dropdown')) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeNotifications);
                }
            });
        }

        function removeNotification(element) {
            const item = element.closest('.notification-item');
            item.style.transform = 'translateX(100%)';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
        }

        function markAllAsRead() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => {
                item.classList.remove('unread');
            });
        }

        function showMessage(message, type = 'success') {
            const container = document.getElementById('messageContainer');
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                padding: 12px 16px;
                border-radius: 8px;
                margin-bottom: 10px;
                color: #fff;
                font-weight: 500;
                animation: slideIn 0.3s ease;
                ${type === 'success' ? 'background: #22c55e;' : 'background: #ef4444;'}
            `;
            messageDiv.innerHTML = `
                <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            container.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => messageDiv.remove(), 300);
            }, 3000);
        }

        function resetForm() {
            document.getElementById('profileForm').reset();
            document.getElementById('name').value = '<?= $adminName ?>';
            document.getElementById('email').value = '<?= $adminEmail ?>';
        }

        // Handle form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            showMessage('Profile updated successfully!', 'success');
        });