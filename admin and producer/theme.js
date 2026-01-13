document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = localStorage.getItem('theme');
    const sunBtn = document.querySelector('.fa-sun') ? document.querySelector('.fa-sun').closest('button') : document.querySelector('.icon-btn');
    const icon = sunBtn ? sunBtn.querySelector('i') : null;
    const logo = document.querySelector('.logo-img');

    // Function to set Light Mode
    function enableLightMode() {
        document.body.classList.add('light-mode');
        if(icon) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
        if(logo) logo.src = 'logo-dark.png';
        localStorage.setItem('theme', 'light');
    }

    // Function to set Dark Mode
    function enableDarkMode() {
        document.body.classList.remove('light-mode');
        if(icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
        if(logo) logo.src = 'logo.png';
        localStorage.setItem('theme', 'dark');
    }


    if (currentTheme === 'light') {
        enableLightMode();
    }

    if(sunBtn) {
        sunBtn.addEventListener('click', () => {
            if(document.body.classList.contains('light-mode')) {
                enableDarkMode();
            } else {
                enableLightMode();
            }
        });
    }
});


/* --- Toggle Search Input --- */
function toggleSearch() {
    const searchInput = document.getElementById('navSearch');
    searchInput.classList.toggle('active');
    
    // Auto-focus the input when opened
    if (searchInput.classList.contains('active')) {
        searchInput.focus();
    }
}

// Optional: Close search if clicking outside
document.addEventListener('click', function(e) {
    const container = document.querySelector('.search-container');
    const input = document.getElementById('navSearch');
    
    // If click is outside container AND input is active
    if (!container.contains(e.target) && input.classList.contains('active')) {
        // Only close if the input is empty
        if(input.value === '') {
            input.classList.remove('active');
        }
    }
});