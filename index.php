<?php
session_start();
include './config/db_connect.php';

// --- 1. SECURITY CHECK ---
// If the user is not logged in, redirect to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// --- 2. FETCH USER DETAILS ---
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; // Retrieved from session set during login
$is_premium = false;

// Check the 'viewers' table to see if they are Premium
// (We use proper error checking just in case)
$sql = "SELECT subscription_plan FROM viewers WHERE user_id = '$user_id'";
if ($result = $conn->query($sql)) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['subscription_plan'] === 'premium') {
            $is_premium = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSP - Movie Streaming Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #020b1f;
            --bg-card: #1f2940;
            --bg-faq: #0b1326;
            --bg-faq-ans: #161d2f;
            --text-main: white;
            --text-sub: #ccc;
            --nav-bg: linear-gradient(to bottom, rgba(2,11,31,0.95), transparent);
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --bg-faq: #ffffff;
            --bg-faq-ans: #e4e6eb;
            --text-main: #1c1e21;
            --text-sub: #65676b;
            --nav-bg: linear-gradient(to bottom, rgba(255,255,255,0.95), transparent);
        }

        /* --- RESET & GLOBAL --- */
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', sans-serif;}
        body {background-color: var(--bg-body); color: var(--text-main); overflow-x:hidden; transition: background 0.3s, color 0.3s;}
        a {text-decoration:none; color:inherit;}

        /* --- NAVBAR --- */
        .navbar {
            display:flex; 
            align-items:center; 
            justify-content:space-between; 
            padding:15px 5%; 
            position:fixed; 
            top:0; 
            width:100%; 
            z-index:1000; 
            background: var(--nav-bg);
            transition: background 0.3s;
        }
        .nav-links {display:flex; list-style:none; gap:25px;}
        .nav-links a {color: var(--text-sub); font-weight:500; font-size:15px;}
        .nav-links a.active {color:#e50914;}
        .nav-icons {display:flex; gap:20px; font-size:18px; align-items: center; color:#ccc;}
        .logo-img {height:35px;}
        
        /* SEARCH BAR INSIDE NAV */
        .search-container {
            display: flex;
            align-items: center;
            position: relative;
        }
        .search-input {
            width: 0;
            opacity: 0;
            background: rgba(255,255,255,0.1);
            border: 1px solid #444;
            color: var(--text-main);
            padding: 5px 0;
            border-radius: 4px;
            outline: none;
            transition: all 0.4s ease;
            font-size: 14px;
        }
        .search-input.active {
            width: 180px;
            opacity: 1;
            padding: 5px 10px;
            margin-right: 10px;
        }
        .fa-magnifying-glass { cursor: pointer; transition: 0.3s; }
        .fa-magnifying-glass:hover { color: #e50914; }

        /* --- USER ICON & LOGOUT --- */
        #user-display-name { font-size: 14px; color: #e50914; font-weight: 600; cursor: pointer; }
        .user-container { position: relative; display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .logout-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            background: var(--bg-faq);
            border: 1px solid var(--bg-card);
            border-radius: 4px;
            display: none;
            z-index: 1001;
            overflow: hidden;
            min-width: 140px;
        }
        /* Updated to style links inside dropdown */
        .logout-dropdown button, .logout-dropdown a {
            display: block;
            background: none;
            border: none;
            color: var(--text-main);
            padding: 10px 20px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-size: 14px;
            transition: 0.3s;
            text-decoration: none; /* For the anchor tag */
        }
        .logout-dropdown button:hover, .logout-dropdown a:hover { background: #e50914; color: white; }

        /* THEME TOGGLE ICON */
        #theme-toggle { cursor: pointer; transition: 0.3s; }
        #theme-toggle:hover { color: #e50914; }

        .hero { position: relative; height: 60vh; width: 100%; overflow: hidden; display: flex; align-items: center; padding-left: 50px; color: white; }
        .hero-slides { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        .slide { position: absolute; width: 100%; height: 100%; background-size: cover; background-position: center; opacity: 0; animation: slideShow 15s infinite; }
        .slide:nth-child(1) { animation-delay: 0s; }
        .slide:nth-child(2) { animation-delay: 5s; }
        .slide:nth-child(3) { animation-delay: 10s; }
        @keyframes slideShow { 0% { opacity: 0; } 10% { opacity: 1; } 33% { opacity: 1; } 43% { opacity: 0; } 100% { opacity: 0; } }
        .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.9)); z-index: 1; }
        .hero-content { position: relative; z-index: 2; max-width: 600px; }
        .hero-title { font-size: 3.5rem; margin-bottom: 10px; }
        .hero-desc { font-size: 1.1rem; margin-bottom: 20px; color: #ccc; }
        .btn-watch { padding: 10px 20px; font-size: 1rem; background-color: #e50914; color: white; border: none; cursor: pointer; border-radius: 4px; }
        
        .container { padding: 40px 5%; }
        .section-title { font-size:24px; margin-bottom:20px; font-weight:600; display:flex; justify-content:space-between; align-items:center; color: var(--text-main);}
        .see-more-btn { color: #e50914; font-size: 14px; cursor: pointer; padding: 5px 10px; border: 1px solid transparent; transition: 0.3s; }
        .see-more-btn:hover { border: 1px solid #e50914; border-radius: 4px; }
        
        .movie-row { display:flex; gap:15px; overflow-x:auto; padding-bottom:20px; scrollbar-width:none; }
        .movie-row::-webkit-scrollbar { display:none; }
        .hidden-row { display: none; margin-top: 20px; animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .card { min-width:170px; aspect-ratio:2/3; border-radius:12px; overflow:hidden; background: var(--bg-card); transition:0.3s; cursor:pointer; border:1px solid var(--bg-card); position:relative; }
        .card img { width: 100%; height: 100%; object-fit: cover; background-color: #333; }
        .card:hover { transform:translateY(-10px); border-color:#e50914; }
        .card .play-btn { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:rgba(0,0,0,0.6); color:white; border:none; font-size:24px; border-radius:50%; padding:15px; display:none; cursor:pointer; }
        .card:hover .play-btn { display:block; }
        .card-caption { padding: 10px; font-size: 14px; text-align: center; position: absolute; bottom: 0; width: 100%; background: linear-gradient(transparent, rgba(0,0,0,0.8)); color: white; }
        
        .faq-title { text-align:center; font-size:28px; margin-bottom:40px; color: var(--text-main); margin-top: 40px;}
        .faq-item { background: var(--bg-faq); border: 1px solid var(--bg-card); border-radius:10px; margin-bottom:12px; overflow: hidden; cursor:pointer; transition: 0.3s; color: var(--text-main);}
        .faq-question { padding:18px 25px; display:flex; justify-content:space-between; align-items:center; }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; background: var(--bg-faq-ans); }
        .faq-answer p { padding: 0 25px 20px 25px; color: var(--text-sub); line-height: 1.6; font-size: 14px; }
        .faq-item.active .faq-answer { max-height: 200px; }
        .faq-item.active .faq-question i { transform: rotate(180deg); color: #e50914; }
        
        .char-row { display:flex; justify-content:center; gap:25px; flex-wrap:wrap; margin-bottom:50px; }
        .char-item { text-align:center; width:100px; }
        .char-img { width:85px; height:85px; border-radius:50%; object-fit:cover; border:2px solid #e50914; transition:0.3s; cursor:pointer; }
        .char-img:hover { transform:scale(1.1); box-shadow:0 0 15px rgba(229,9,20,0.5); }
        .char-name { font-size:12px; margin-top:8px; color: var(--text-sub); }
        
        .footer { background-color: var(--bg-body); padding: 50px 5% 20px; border-top: 1px solid var(--bg-card); margin-top: 50px; }
        .footer-container { max-width: 1000px; margin: 0 auto; }
        .footer-socials { margin-bottom: 25px; }
        .footer-socials a { font-size: 24px; margin-right: 25px; color: var(--text-main); transition: 0.3s; }
        .footer-socials a:hover { color: #e50914; }
        .footer-links ul { display: grid; grid-template-columns: repeat(4, 1fr); list-style: none; gap: 15px; }
        .footer-links a { color: #808080; font-size: 13px; transition: 0.3s; }
        .footer-links a:hover { color: var(--text-main); text-decoration: underline; }
        .copyright { color: #808080; font-size: 11px; margin-top: 20px; }
        @media(max-width:768px){ .nav-links { display:none; } .hero-title { font-size:2.5rem; } .card { min-width:140px; } .footer-links ul { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

    <nav class="navbar">
        <div style="font-size: 30px;"><img src="../assets/logo.png" class="logo-img"></div>
        <ul class="nav-links">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="movie.php">Movies</a></li>
            <li><a href="series.php">Series</a></li>
            <li><a href="forum.php">Forum</a></li>
            <li><a href="subscription.php">Subscribe</a></li>
        </ul>
        <div class="nav-icons">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                <i class="fa-solid fa-magnifying-glass" id="searchIcon"></i>
            </div>
            <i class="fa-regular fa-bell"></i>
            
            <div class="user-container" id="userArea">
                <span id="user-display-name" style="<?php echo $is_premium ? 'color: #FFD700;' : ''; ?>">
                    <?php echo htmlspecialchars($username); ?>
                    <?php if($is_premium): ?>
                        <i class="fa-solid fa-crown" style="font-size: 10px; margin-left: 4px;"></i>
                    <?php endif; ?>
                </span>

                <i class="fa-solid fa-circle-user"></i>
                
                <div class="logout-dropdown" id="logoutDropdown">
                    <button onclick="window.location.href='userDashboard.php'">Dashboard</button>
                    <a href="../actions/logout.php">Log Out</a>
                </div>
            </div>

            <i class="fa-solid fa-moon" id="theme-toggle"></i>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-slides">
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1519074069444-1ba4fff66d16?q=80&w=2544&auto=format&fit=crop');"></div>
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=2670&auto=format&fit=crop');"></div>
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=2525&auto=format&fit=crop');"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title">The Witcher</h1>
            <p class="hero-desc">Geralt of Rivia, a mutated monster-hunter for hire, journeys toward his destiny.</p>
            <button class="btn-watch" onclick="window.location.href='movie.php'">Watch Now</button>
        </div>
    </header>

    <div class="container">
        <div class="section-title">
            Trending Movies 
            <span class="see-more-btn" id="seeMoreBtn">See More</span>
        </div>
        
        <div class="movie-row">
            <div class="card">
                <img src="https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">The Dark Knight</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">Interstellar</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1519074069444-1ba4fff66d16?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">The Witcher</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1594909122845-11baa439b7bf?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">Inception</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
        </div>

        <div class="movie-row hidden-row" id="moreMoviesRow">
            <div class="card">
                <img src="https://images.unsplash.com/photo-1531259683007-016a7b628fc3?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">The Batman</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1621955964441-c173e01c1151?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">Dune</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">Spider-Man</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1509248961158-e54f6934749c?auto=format&fit=crop&w=500&h=750&q=80">
                <div class="card-caption">Joker</div>
                <button class="play-btn"><i class="fa-solid fa-play"></i></button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="section-title">Popular Characters</div>
        <div class="char-row">
            <div class="char-item">
                <img class="char-img" src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=200&h=200&auto=format&fit=crop">
                <div class="char-name">Henry Cavill</div>
            </div>
            <div class="char-item">
                <img class="char-img" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=200&h=200&auto=format&fit=crop">
                <div class="char-name">Anya Chalotra</div>
            </div>
            <div class="char-item">
                <img class="char-img" src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=200&h=200&auto=format&fit=crop">
                <div class="char-name">Margot Robbie</div>
            </div>
            <div class="char-item">
                <img class="char-img" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=200&h=200&auto=format&fit=crop">
                <div class="char-name">Cillian Murphy</div>
            </div>
        </div>
    </div>

    <h2 class="faq-title">Frequently Asked Questions</h2>
    <div style="max-width:800px; margin:0 auto; padding: 0 20px;">
      <div class="faq-item">
        <div class="faq-question">
          <span>What is MSP?</span>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
          <p>MSP is a streaming service that offers a wide variety of award-winning TV shows, movies, anime, documentaries, and more on thousands of internet-connected devices.</p>
        </div>
      </div>
      <div class="faq-item">
        <div class="faq-question">
          <span>How much does MSP cost?</span>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
          <p>Watch MSP on your smartphone, tablet, Smart TV, laptop, or streaming device, all for one fixed monthly fee. Plans range from $9.99 to $19.99 a month.</p>
        </div>
      </div>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-socials">
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-twitter"></i></a>
            </div>
            <div class="footer-links">
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Privacy</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Jobs</a></li>
                </ul>
            </div>
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- THEME TOGGLE LOGIC ---
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;

            themeToggle.addEventListener('click', () => {
                if (body.getAttribute('data-theme') === 'light') {
                    body.removeAttribute('data-theme');
                    themeToggle.classList.replace('fa-sun', 'fa-moon');
                    localStorage.setItem('theme', 'dark');
                } else {
                    body.setAttribute('data-theme', 'light');
                    themeToggle.classList.replace('fa-moon', 'fa-sun');
                    localStorage.setItem('theme', 'light');
                }
            });

            if(localStorage.getItem('theme') === 'light') {
                body.setAttribute('data-theme', 'light');
                themeToggle.classList.replace('fa-moon', 'fa-sun');
            }

            // --- DROPDOWN & INTERACTION LOGIC (Simplified for PHP) ---
            const userArea = document.getElementById('userArea');
            const logoutDropdown = document.getElementById('logoutDropdown');

            userArea.addEventListener('click', function(e) {
                e.stopPropagation();
                if (logoutDropdown.style.display === 'block') {
                    logoutDropdown.style.display = 'none';
                } else {
                    logoutDropdown.style.display = 'block';
                }
            });

            document.addEventListener('click', function() {
                logoutDropdown.style.display = 'none';
            });

            // 2. SEARCH LOGIC
            const searchIcon = document.getElementById('searchIcon');
            const searchInput = document.getElementById('searchInput');

            searchIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.classList.toggle('active');
                if (searchInput.classList.contains('active')) {
                    searchInput.focus();
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    searchInput.classList.remove('active');
                }
            });

            // 3. SEE MORE TOGGLE LOGIC
            const btn = document.getElementById('seeMoreBtn');
            const row = document.getElementById('moreMoviesRow');
            if (btn && row) {
                btn.addEventListener('click', function() {
                    const isHidden = window.getComputedStyle(row).display === "none";
                    if (isHidden) {
                        row.style.display = "flex";
                        this.textContent = "See Less";
                    } else {
                        row.style.display = "none";
                        this.textContent = "See More";
                    }
                });
            }

            // 4. FAQ TOGGLE LOGIC
            const faqs = document.querySelectorAll(".faq-item");
            faqs.forEach(faq => {
                faq.addEventListener("click", () => {
                    faq.classList.toggle("active");
                    faqs.forEach(other => {
                        if (other !== faq) other.classList.remove("active");
                    });
                });
            });
        });
    </script>
</body>
</html>