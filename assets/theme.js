document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = localStorage.getItem('theme');
    const sunBtn = document.querySelector('.fa-sun') ? document.querySelector('.fa-sun').closest('button') : document.querySelector('.icon-btn');
    const icon = sunBtn ? sunBtn.querySelector('i') : null;
    const logo = document.querySelector('.logo-img');

    // Helper: Swaps filename keeping the correct folder path
    function setLogo(filename) {
        if (!logo) return;
        // 1. Get the current full path (e.g., http://localhost/assets/logo.png)
        const currentSrc = logo.src;
        // 2. Remove the file name at the end to get the folder (e.g., http://localhost/assets/)
        const folderPath = currentSrc.substring(0, currentSrc.lastIndexOf('/') + 1);
        // 3. Set new source
        logo.src = folderPath + filename;
    }

    // Function to set Light Mode
    function enableLightMode() {
        document.body.classList.add('light-mode');
        if(icon) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
        
        // SWITCH TO DARK LOGO
        setLogo('logo-dark.png');
        
        localStorage.setItem('theme', 'light');
    }

    // Function to set Dark Mode
    function enableDarkMode() {
        document.body.classList.remove('light-mode');
        if(icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
        
        // SWITCH TO WHITE LOGO
        setLogo('logo.png');
        
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
    if (searchInput) {
        searchInput.classList.toggle('active');
        if (searchInput.classList.contains('active')) {
            searchInput.focus();
        }
    }
}

document.addEventListener('click', function(e) {
    const container = document.querySelector('.search-container');
    const input = document.getElementById('navSearch');
    
    if (container && input && !container.contains(e.target) && input.classList.contains('active')) {
        if(input.value === '') {
            input.classList.remove('active');
        }
    }
});