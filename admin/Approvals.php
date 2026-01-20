<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. HANDLE FORM SUBMISSIONS (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Handle User (Producer) Decision
    if (isset($_POST['user_action'])) {
        // FIX 1: Ensure we get the ID from the form (matches hidden input name)
        $profile_id = intval($_POST['producer_id']); 
        $status = ($_POST['user_action'] === 'Approve') ? 'verified' : 'rejected'; // ENUM is 'verified', not 'approved'
        
        // FIX 2: Use 'profile_id' in WHERE clause
        $stmt = $conn->prepare("UPDATE producers SET verification_status = ? WHERE profile_id = ?");
        $stmt->bind_param("si", $status, $profile_id);
        $stmt->execute();
        
        // If verified, update users table role to 'producer'
        if ($status === 'verified') {
            // FIX 3: Use 'profile_id' in subquery
            $stmt_role = $conn->prepare("UPDATE users SET role = 'producer' WHERE user_id = (SELECT user_id FROM producers WHERE profile_id = ?)");
            $stmt_role->bind_param("i", $profile_id);
            $stmt_role->execute();
        }
        
        echo "<script>alert('Producer " . $status . " successfully!'); window.location.href='Approvals.php';</script>";
    }

    // B. Handle Content (Movie/Series) Decision
    if (isset($_POST['content_action'])) {
        $content_type = $_POST['content_type']; 
        $content_id = intval($_POST['content_id']);
        $status = ($_POST['content_action'] === 'Approve') ? 'approved' : 'rejected';
        
        if ($content_type === 'movie') {
            $stmt = $conn->prepare("UPDATE movies SET approval_status = ? WHERE movie_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE series SET approval_status = ? WHERE series_id = ?");
        }
        
        $stmt->bind_param("si", $status, $content_id);
        $stmt->execute();
        
        echo "<script>alert('Content " . $status . " successfully!'); window.location.href='Approvals.php';</script>";
    }
}

// --- 2. FETCH PENDING DATA ---

// FIX 4: Use 'p.profile_id' in SELECT
$sql_producers = "SELECT p.profile_id, u.username, u.email, p.website, p.company_name, u.created_at 
                  FROM producers p 
                  JOIN users u ON p.user_id = u.user_id 
                  WHERE p.verification_status = 'pending'";
$result_producers = $conn->query($sql_producers);

// Fetch Pending Movies
$sql_movies = "SELECT m.movie_id, m.title, m.genre, m.duration_minutes, u.username as uploader 
               FROM movies m 
               JOIN users u ON m.uploaded_by = u.user_id 
               WHERE m.approval_status = 'pending'";
$result_movies = $conn->query($sql_movies);

// Fetch Pending Series
$sql_series = "SELECT s.series_id, s.title, s.genre, s.release_year, u.username as uploader 
               FROM series s 
               JOIN users u ON s.uploaded_by = u.user_id 
               WHERE s.approval_status = 'pending'";
$result_series = $conn->query($sql_series);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/theme.js"></script>
</head>
<body>

    <div id="userReviewModal" class="modal-overlay" onclick="closeModalOnOverlay(event, 'userReviewModal')">
        <div class="modal-form-container">
            <div class="modal-header">
                <h3>Applicant Details</h3>
                <button class="close-icon-btn" onclick="closeModal('userReviewModal')">&times;</button>
            </div>
            
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:25px;">
                <div style="width:70px; height:70px; background:#1f2940; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; color:var(--accent-color); border: 2px solid var(--border-color);">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <h2 id="uName" style="margin:0; font-size:22px; color:var(--text-main);">-</h2>
                    <span style="background:rgba(34, 142, 229, 0.1); color:var(--accent-color); padding:3px 10px; border-radius:12px; font-size:11px; font-weight:bold; border:1px solid var(--accent-color);">Producer Applicant</span>
                </div>
            </div>

            <div style="background:var(--bg-element); padding:15px; border-radius:8px; border:1px solid var(--border-color); margin-bottom:20px;">
                <p style="margin-bottom:10px; font-size:13px; color:var(--text-muted); display:flex; justify-content:space-between;">
                    <span>Email:</span> <span id="uEmail" style="color:var(--text-main); font-weight:500;">-</span>
                </p>
                <p style="margin-bottom:10px; font-size:13px; color:var(--text-muted); display:flex; justify-content:space-between;">
                    <span>Company:</span> <span id="uCompany" style="color:var(--text-main);">-</span>
                </p>
                <p style="font-size:13px; color:var(--text-muted); display:flex; justify-content:space-between; margin:0;">
                    <span>Website:</span> 
                    <a id="uWebsite" href="#" target="_blank" style="color:var(--accent-color); text-decoration:underline;">View Website</a>
                </p>
            </div>

            <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <input type="hidden" name="producer_id" id="hiddenProducerId">
                <button type="submit" name="user_action" value="Approve" class="btn-approve-large" style="width:100%; justify-content:center;">Approve</button>
                <button type="submit" name="user_action" value="Reject" class="btn-reject-large" style="width:100%; justify-content:center;">Reject</button>
            </form>
        </div>
    </div>

    <div id="approvalModal" class="modal-overlay" onclick="closeApprovalModal(event)">
        <div class="modal-container">
            <button class="modal-close-btn" onclick="closeApprovalModal(null)">&times;</button>
            <div id="modalHero" class="modal-hero">
                <div class="modal-hero-overlay"></div>
                <div class="modal-hero-content">
                    <span class="status-badge-pending">Pending Approval</span>
                    <h1 id="mTitle" class="movie-title-large" style="font-size: 40px;">Loading...</h1>
                    <div class="movie-meta"><span id="mYear">0000</span><span id="mDuration">0h 0m</span><span id="mLang">EN</span></div>
                </div>
            </div>
            <div class="modal-content-body">
                <div class="detail-section"><h2 class="detail-heading">Description</h2><p id="mOverview" class="movie-description">Loading...</p></div>
                <div class="detail-section"><h2 class="detail-heading">Genres</h2><div id="mGenres" class="genre-tags"></div></div>
                <div class="detail-section"><h2 class="detail-heading">Cast</h2><div id="mCast" class="cast-scroller"></div></div>
                <div class="admin-action-section">
                    <h3 class="admin-action-title">Admin Decision</h3>
                    <form method="POST" class="admin-btn-container">
                        <input type="hidden" name="content_id" id="hiddenContentId">
                        <input type="hidden" name="content_type" id="hiddenContentType">
                        <button type="submit" name="content_action" value="Approve" class="btn-approve-large"><i class="fa-solid fa-check"></i> Approve</button>
                        <button type="submit" name="content_action" value="Reject" class="btn-reject-large"><i class="fa-solid fa-xmark"></i> Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow"><img src="../assets/logo.png" alt="Logo" class="logo-img"></a>
            <ul class="nav-links">
                <li><a href="Dashboard.php">Dashboard</a></li>
                <li><a href="Users.html">Users</a></li>
                <li><a href="Movies.php">Movies</a></li>
                <li><a href="Series.php">Series</a></li>
                <li><a href="Wallet.html">Wallet</a></li>
            </ul>
            <div class="nav-icons">
               <a href="AdminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
               <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        
        <section id="pending-users" class="section-container blue-border">
            <div class="section-tab">Pending Producers</div>
            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_producers->num_rows > 0): ?>
                            <?php while($row = $result_producers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                <td>
                                    <button class="btn-details" 
                                        onclick="openUserModal(
                                            '<?php echo htmlspecialchars($row['username']); ?>', 
                                            '<?php echo htmlspecialchars($row['email']); ?>', 
                                            '<?php echo htmlspecialchars($row['company_name']); ?>', 
                                            '<?php echo htmlspecialchars($row['website']); ?>',
                                            '<?php echo $row['profile_id']; ?>'
                                        )">Review</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#666;">No pending producer requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="pending-movies" class="section-container blue-border">
            <div class="section-tab">Pending Movies</div>
            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr><th>Uploader</th><th>Movie Name</th><th>Genre</th><th>Duration</th><th>Review</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($result_movies->num_rows > 0): ?>
                            <?php while($row = $result_movies->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['uploader']); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['genre']); ?></td>
                                <td><?php echo $row['duration_minutes']; ?>m</td>
                                <td><button class="btn-details" onclick="openApprovalModal('movie', '<?php echo $row['title']; ?>', <?php echo $row['movie_id']; ?>)">View Details</button></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#666;">No pending movies.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="pending-series" class="section-container blue-border">
            <div class="section-tab">Pending Series</div>
            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr><th>Uploader</th><th>Series Name</th><th>Genre</th><th>Year</th><th>Review</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($result_series->num_rows > 0): ?>
                            <?php while($row = $result_series->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['uploader']); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['genre']); ?></td>
                                <td><?php echo $row['release_year']; ?></td>
                                <td><button class="btn-details" onclick="openApprovalModal('series', '<?php echo $row['title']; ?>', <?php echo $row['series_id']; ?>)">View Details</button></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#666;">No pending series.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <script>
        const API_KEY = '96878691f0272aade53fca27ac2a739f'; 
        const IMG_BASE = 'https://image.tmdb.org/t/p/original';
        const IMG_POSTER = 'https://image.tmdb.org/t/p/w500';

        // JS FIX: Corrected variable mapping for Company/Website
        function openUserModal(name, email, company, website, id) {
            document.getElementById('uName').innerText = name;
            document.getElementById('uEmail').innerText = email;
            document.getElementById('uCompany').innerText = company;
            document.getElementById('uWebsite').href = website;
            document.getElementById('hiddenProducerId').value = id;
            document.getElementById('userReviewModal').style.display = 'flex';
        }

        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        function closeModalOnOverlay(event, modalId) { if (event.target.id === modalId) closeModal(modalId); }

        async function openApprovalModal(type, title, dbId) {
            document.getElementById('hiddenContentId').value = dbId;
            document.getElementById('hiddenContentType').value = type;
            const modal = document.getElementById('approvalModal');
            const searchType = (type === 'series') ? 'tv' : 'movie';
            const url = `https://api.themoviedb.org/3/search/${searchType}?api_key=${API_KEY}&query=${encodeURIComponent(title)}`;

            try {
                const res = await fetch(url);
                const searchData = await res.json();
                if (!searchData.results || searchData.results.length === 0) {
                    document.getElementById('mTitle').innerText = title;
                    document.getElementById('mOverview').innerText = "Description not available from external API.";
                    modal.style.display = 'flex';
                    return;
                }
                const data = searchData.results[0];
                const tmdbId = data.id;
                const detailsUrl = `https://api.themoviedb.org/3/${searchType}/${tmdbId}?api_key=${API_KEY}&append_to_response=credits`;
                const fullRes = await fetch(detailsUrl);
                const fullData = await fullRes.json();

                if (fullData.backdrop_path) document.getElementById('modalHero').style.backgroundImage = `url('${IMG_BASE + fullData.backdrop_path}')`;
                document.getElementById('mTitle').innerText = (type === 'series') ? fullData.name : fullData.title;
                document.getElementById('mOverview').innerText = fullData.overview;
                document.getElementById('mLang').innerText = fullData.original_language.toUpperCase();

                if (type === 'series') {
                    document.getElementById('mYear').innerText = fullData.first_air_date ? fullData.first_air_date.split('-')[0] : 'N/A';
                    document.getElementById('mDuration').innerText = `${fullData.number_of_seasons} Season(s)`;
                } else {
                    document.getElementById('mYear').innerText = fullData.release_date ? fullData.release_date.split('-')[0] : 'N/A';
                    document.getElementById('mDuration').innerText = `${fullData.runtime}m`;
                }

                const gContainer = document.getElementById('mGenres');
                gContainer.innerHTML = '';
                fullData.genres.forEach(g => {
                    const span = document.createElement('span');
                    span.className = 'genre-tag';
                    span.innerText = g.name;
                    gContainer.appendChild(span);
                });

                const cContainer = document.getElementById('mCast');
                cContainer.innerHTML = '';
                if(fullData.credits && fullData.credits.cast) {
                    fullData.credits.cast.slice(0, 8).forEach(p => {
                        if (p.profile_path) {
                            const div = document.createElement('div');
                            div.className = 'cast-card';
                            div.innerHTML = `<img src="${IMG_POSTER + p.profile_path}" class="cast-img"><div class="cast-name">${p.name}</div>`;
                            cContainer.appendChild(div);
                        }
                    });
                }
                modal.style.display = 'flex';
            } catch (error) {
                alert("Error loading details. You can still approve.");
                modal.style.display = 'flex';
            }
        }

        function closeApprovalModal(event) {
            if (event === null || event.target.id === 'approvalModal') document.getElementById('approvalModal').style.display = 'none';
        }
    </script>
</body>
</html>