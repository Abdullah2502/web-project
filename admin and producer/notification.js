function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
        }

        window.addEventListener('click', function (e) {
            const dropdown = document.getElementById('notificationDropdown');
            const wrapper = document.querySelector('.notification-wrapper');
            if (!wrapper.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });