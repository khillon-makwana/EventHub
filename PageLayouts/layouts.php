<?php
class layouts
{
    public function head($conf)
    {
?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="description" content="EventHub - Your premier platform for discovering and managing events">
            <meta name="author" content="EventHub Team">
            <meta name="generator" content="EventHub v1.0">
            <title><?php echo $conf['site_name']; ?> - Connect Through Events</title>
            
            <!-- Preload critical resources -->
            <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
            <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
            
            <!-- Primary CSS -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
            
            <!-- Google Fonts -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Pacifico&display=swap" rel="stylesheet">

            <style>
                :root {
                    --primary-color: #4F46E5;
                    --primary-hover: #7C3AED;
                    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
                    --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.12);
                    --shadow-large: 0 20px 50px rgba(0, 0, 0, 0.15);
                    --transition-smooth: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
                    --transition-bounce: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Inter', sans-serif;
                    opacity: 0;
                    animation: fadeIn 0.8s ease-out forwards;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    min-height: 100vh;
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes slideInDown {
                    from { transform: translateY(-100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }

                @keyframes slideInUp {
                    from { transform: translateY(100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }

                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }

                @keyframes float {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }

                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }

                .bd-placeholder-img {
                    font-size: 1.125rem;
                    text-anchor: middle;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    user-select: none;
                }

                @media (min-width: 768px) {
                    .bd-placeholder-img-lg {
                        font-size: 3.5rem;
                    }
                }

                /* Enhanced Event Card Styles */
                .event-card {
                    transition: var(--transition-smooth);
                    border: none;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: var(--shadow-soft);
                    background: white;
                    position: relative;
                }
                
                .event-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: var(--gradient-primary);
                    transform: scaleX(0);
                    transition: var(--transition-smooth);
                    transform-origin: left;
                }
                
                .event-card:hover::before {
                    transform: scaleX(1);
                }
                
                .event-card:hover {
                    transform: translateY(-12px) scale(1.02);
                    box-shadow: var(--shadow-large) !important;
                }
                
                .card-img-top {
                    transition: var(--transition-smooth);
                }
                
                .event-card:hover .card-img-top {
                    transform: scale(1.1);
                }
                
                .line-clamp-2 {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                
                .line-clamp-3 {
                    display: -webkit-box;
                    -webkit-line-clamp: 3;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                
                .bg-gradient-primary {
                    background: var(--gradient-primary);
                }
                
                .event-meta div {
                    line-height: 1.4;
                }
                
                .text-truncate {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                /* Enhanced Header Styles */
                header {
                    background-color: rgba(255, 255, 255, 0.95);
                    border-bottom: 1px solid #e5e7eb;
                    backdrop-filter: blur(10px);
                    animation: slideInDown 0.8s ease-out;
                    position: sticky;
                    top: 0;
                    z-index: 1000;
                    box-shadow: var(--shadow-soft);
                }

                .brand-logo {
                    font-family: 'Pacifico', cursive;
                    font-size: 1.8rem;
                    background: var(--gradient-primary);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    transition: var(--transition-smooth);
                    animation: float 3s ease-in-out infinite;
                }

                .brand-logo:hover {
                    animation: pulse 0.6s ease, float 3s ease-in-out infinite;
                    transform: scale(1.05);
                }

                header nav ul li a {
                    color: #374151;
                    font-weight: 500;
                    transition: var(--transition-smooth);
                    position: relative;
                    padding: 0.5rem 1rem !important;
                    border-radius: 8px;
                }

                header nav ul li a::before {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    width: 0;
                    height: 2px;
                    background: var(--primary-color);
                    transition: var(--transition-smooth);
                    transform: translateX(-50%);
                }

                header nav ul li a:hover {
                    color: var(--primary-color);
                    transform: translateY(-2px);
                }

                header nav ul li a:hover::before {
                    width: 80%;
                }

                header nav ul li a.active {
                    color: var(--primary-color);
                    font-weight: 600;
                }

                header nav ul li a.active::before {
                    width: 80%;
                }

                .user-section {
                    transition: var(--transition-smooth);
                    padding: 0.5rem 1rem;
                    border-radius: 12px;
                    background: rgba(79, 70, 229, 0.05);
                }

                .user-section:hover {
                    background: rgba(79, 70, 229, 0.1);
                    transform: translateY(-2px);
                }

                .sign-out-btn {
                    background: var(--gradient-primary);
                    color: #ffffff;
                    border: none;
                    border-radius: 10px;
                    padding: 0.5rem 1.2rem;
                    font-size: 0.9rem;
                    font-weight: 600;
                    transition: var(--transition-bounce);
                    box-shadow: var(--shadow-soft);
                }

                .sign-out-btn:hover {
                    background: var(--gradient-secondary);
                    transform: translateY(-3px) scale(1.05);
                    box-shadow: var(--shadow-medium);
                    color: #ffffff;
                }

                /* Enhanced Footer Styles */
                footer {
                    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
                    animation: slideInUp 0.8s ease-out;
                }

                .footer-logo {
                    font-family: 'Pacifico', cursive;
                    font-size: 1.8rem;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .social-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.1);
                    color: #cbd5e0;
                    transition: var(--transition-smooth);
                    text-decoration: none;
                }

                .social-icon:hover {
                    background: var(--primary-color);
                    color: white;
                    transform: translateY(-3px) rotate(5deg);
                    box-shadow: var(--shadow-soft);
                }

                /* Loading animation for images */
                .card-img-top {
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: loading 1.5s infinite;
                }

                @keyframes loading {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }

                /* Enhanced button animations */
                .btn {
                    transition: var(--transition-smooth);
                    position: relative;
                    overflow: hidden;
                }

                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-medium);
                }

                .btn-primary {
                    background: var(--gradient-primary);
                    border: none;
                }

                .btn-primary:hover {
                    background: var(--gradient-secondary);
                    transform: translateY(-3px);
                }

                /* Banner animations */
                .banner-section {
                    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
                    backdrop-filter: blur(10px);
                    position: relative;
                    overflow: hidden;
                }

                .banner-section::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
                    animation: shimmer 3s infinite;
                }

                @keyframes shimmer {
                    0% { left: -100%; }
                    100% { left: 100%; }
                }

                /* Alert/Message animations */
                .alert {
                    animation: slideInDown 0.5s ease-out;
                    border: none;
                    border-radius: 12px;
                    box-shadow: var(--shadow-soft);
                }

                /* Form enhancements */
                .form-control {
                    transition: var(--transition-smooth);
                    border-radius: 10px;
                    border: 2px solid #e2e8f0;
                }

                .form-control:focus {
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
                    transform: translateY(-2px);
                }

                /* Badge animations */
                .badge {
                    transition: var(--transition-smooth);
                }

                .badge:hover {
                    transform: scale(1.1) rotate(5deg);
                }

                /* Back to top button */
                .back-to-top {
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background: var(--gradient-primary);
                    color: white;
                    border: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    visibility: hidden;
                    transition: var(--transition-smooth);
                    box-shadow: var(--shadow-medium);
                    z-index: 1000;
                }

                .back-to-top.visible {
                    opacity: 1;
                    visibility: visible;
                }

                .back-to-top:hover {
                    transform: translateY(-5px) scale(1.1);
                    background: var(--gradient-secondary);
                }
            </style>
        </head>

        <body class="bg-light">
<?php
    }

    public function header($conf)
{
    // Get unread notification count
    global $NotificationManager;
    $unread_count = 0;
    if (isset($_SESSION['user_id'])) {
        $unread_count = $NotificationManager->getUnreadCount($_SESSION['user_id']);
    }
    ?>
        <header class="w-100">
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Logo on the left -->
                    <div class="brand-section">
                        <a href="dashboard.php" class="text-decoration-none">
                            <h1 class="brand-logo mb-0">
                                <i class="fas fa-calendar-star me-2"></i><?php echo $conf['site_name']; ?>
                            </h1>
                        </a>
                    </div>

                    <!-- Navigation centered -->
                    <nav class="d-none d-md-block mx-auto">
                        <ul class="nav mb-0 justify-content-center">
                            <li class="nav-item">
                                <a href="dashboard.php" class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">HOME</a>
                            </li>
                            <li class="nav-item">
                                <a href="all_events.php" class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'all_events.php' ? 'active' : ''; ?>">EVENTS</a>
                            </li>
                            <li class="nav-item">
                                <a href="my_events.php" class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'my_events.php' ? 'active' : ''; ?>">MY EVENTS</a>
                            </li>
                            <li class="nav-item">
                                <a href="my_profile.php" class="nav-link px-3 <?php echo basename($_SERVER['PHP_SELF']) == 'my_profile.php' ? 'active' : ''; ?>">MY PROFILE</a>
                            </li>
                        </ul>
                    </nav>

                    <!-- User info and sign out on the right -->
                    <div class="user-section d-flex align-items-center gap-3">
                        <!-- Notifications Icon -->
                        <a href="notifications.php" class="nav-link position-relative p-0 me-2 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bell fa-lg text-muted"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-user-circle fa-lg text-primary"></i>
                            <span class="fw-medium"><?php echo htmlspecialchars($_SESSION['user_fullname'] ?? 'Guest'); ?></span>
                        </div>
                        <a href="signout.php" class="btn sign-out-btn">
                            <i class="fas fa-sign-out-alt me-2"></i>Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Navigation -->
        <div class="d-md-none fixed-bottom bg-white border-top py-2">
            <div class="container">
                <ul class="nav justify-content-around">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link text-center <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home d-block mb-1"></i>
                            <small>Home</small>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="all_events.php" class="nav-link text-center <?php echo basename($_SERVER['PHP_SELF']) == 'all_events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt d-block mb-1"></i>
                            <small>Events</small>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_events.php" class="nav-link text-center <?php echo basename($_SERVER['PHP_SELF']) == 'my_events.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list d-block mb-1"></i>
                            <small>My Events</small>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="notifications.php" class="nav-link text-center position-relative <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bell d-block mb-1"></i>
                            <small>Notifications</small>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="signout.php" class="nav-link text-center text-danger">
                            <i class="fas fa-sign-out-alt d-block mb-1"></i>
                            <small>Sign Out</small>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Back to top button -->
        <button class="back-to-top" onclick="scrollToTop()">
            <i class="fas fa-chevron-up"></i>
        </button>
    <?php
}


    public function banner($FlashMessageObject)
    {
?>
            <section class="py-5 text-center banner-section border-bottom">
                <div class="container">
                    <?php echo $FlashMessageObject->getMsg('msg'); ?>
                    <h2 class="mb-3 animate__animated animate__fadeInUp">Welcome, <?php echo htmlspecialchars($_SESSION['user_fullname'] ?? ''); ?>!</h2>
                    <p class="lead text-muted animate__animated animate__fadeInUp animate__delay-1s">
                        EventHub is a premium platform where you can discover amazing events and RSVP to experiences you love.
                    </p>
                </div>
            </section>
<?php
    }

    public function events($conf)
    {
        try {
            $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Fetch published events with categories and attendee count
            $stmt = $pdo->prepare("
                SELECT e.*, 
                       u.fullname as organizer_name,
                       COUNT(DISTINCT ea.id) as attendee_count,
                       COUNT(DISTINCT ec.category_id) as category_count,
                       GROUP_CONCAT(DISTINCT ac.name SEPARATOR ', ') as category_names
                FROM events e 
                LEFT JOIN users u ON e.user_id = u.id 
                LEFT JOIN event_attendees ea ON e.id = ea.event_id 
                LEFT JOIN event_categories ec ON e.id = ec.event_id
                LEFT JOIN attendee_categories ac ON ec.category_id = ac.id
                WHERE e.status IN ('upcoming', 'ongoing')
                GROUP BY e.id 
                ORDER BY e.event_date ASC
                LIMIT 9
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $events = [];
            error_log("Database error in events(): " . $e->getMessage());
        }
        ?>
            
            <section class="py-5 text-center bg-white">
                <div class="container">
                    <div class="row py-4">
                        <div class="col-lg-6 col-md-8 mx-auto">
                            <h1 class="fw-light mb-3 animate__animated animate__fadeIn">Upcoming Events</h1>
                            <p class="lead text-muted animate__animated animate__fadeIn animate__delay-1s">
                                Discover and join amazing events happening around you. From concerts to workshops, 
                                there's always something exciting to experience.
                            </p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <p class="animate__animated animate__fadeIn animate__delay-2s">
                                    <a href="add_event.php" class="btn btn-primary my-2 me-2">
                                        <i class="fas fa-plus-circle me-2"></i>Create New Event
                                    </a>
                                    <a href="all_events.php" class="btn btn-outline-primary my-2">
                                        <i class="fas fa-calendar-alt me-2"></i>View All Events
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="album py-5 bg-light">
                <div class="container">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5 bg-white rounded animate__animated animate__fadeIn">
                            <div class="mb-4">
                                <i class="fas fa-calendar-times fa-4x text-muted opacity-50"></i>
                            </div>
                            <h3 class="text-muted mb-3">No Upcoming Events</h3>
                            <p class="text-muted mb-4">There are no events scheduled at the moment. Check back later for new events!</p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="add_event.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Create Your First Event
                                </a>
                            <?php else: ?>
                                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                                    <a href="signin.php" class="btn btn-primary btn-lg px-4 gap-3">
                                        <i class="fas fa-sign-in-alt me-2"></i>Sign In to Create Events
                                    </a>
                                    <a href="all_events.php" class="btn btn-outline-primary btn-lg px-4">
                                        <i class="fas fa-calendar me-2"></i>Browse All Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                            <?php foreach ($events as $index => $event): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm event-card position-relative animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                        <!-- Event Image -->
                                        <?php if ($event['image']): ?>
                                            <img src="<?php echo htmlspecialchars($event['image']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($event['title']); ?>"
                                                 style="height: 200px; object-fit: cover;"
                                                 onload="this.style.animation = 'none';">
                                        <?php else: ?>
                                            <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center text-white" 
                                                 style="height: 200px;">
                                                <div class="text-center">
                                                    <i class="fas fa-calendar-alt fa-3x mb-2 opacity-75"></i>
                                                    <p class="mb-0 small fw-bold">EVENT</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Badge -->
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <span class="badge bg-<?php 
                                                echo $event['status'] == 'ongoing' ? 'warning' : 'success';
                                            ?> px-3 py-2">
                                                <i class="fas fa-<?php echo $event['status'] == 'ongoing' ? 'play-circle' : 'clock'; ?> me-1"></i>
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="card-body d-flex flex-column pb-2">
                                            <!-- Event Title -->
                                            <h5 class="card-title fw-bold text-dark mb-2 line-clamp-2" style="min-height: 3rem;">
                                                <?php echo htmlspecialchars($event['title']); ?>
                                            </h5>
                                            
                                            <!-- Event Description -->
                                            <p class="card-text text-muted flex-grow-1 mb-3 line-clamp-3" style="min-height: 4.5rem;">
                                                <?php 
                                                $description = strip_tags($event['description']);
                                                if (strlen($description) > 120) {
                                                    $description = substr($description, 0, 120) . '...';
                                                }
                                                echo htmlspecialchars($description);
                                                ?>
                                            </p>
                                            
                                            <!-- Event Metadata -->
                                            <div class="event-meta small text-muted mb-3">
                                                <!-- Location -->
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-primary me-2" style="width: 16px;"></i>
                                                    <span class="text-truncate"><?php echo htmlspecialchars($event['location']); ?></span>
                                                </div>
                                                
                                                <!-- Date & Time -->
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-calendar-alt text-success me-2" style="width: 16px;"></i>
                                                    <span><?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                                </div>
                                                
                                                <!-- Organizer -->
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-user text-info me-2" style="width: 16px;"></i>
                                                    <span class="text-truncate">By <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                                </div>
                                                
                                                <!-- Categories -->
                                                <?php if ($event['category_names']): ?>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-tags text-warning me-2" style="width: 16px;"></i>
                                                        <span class="text-truncate">
                                                            <?php 
                                                            $categories = explode(', ', $event['category_names']);
                                                            $display_categories = array_slice($categories, 0, 2);
                                                            echo htmlspecialchars(implode(', ', $display_categories));
                                                            if (count($categories) > 2) echo '...';
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Attendees & Tickets -->
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-users text-secondary me-2" style="width: 16px;"></i>
                                                    <span>
                                                        <?php echo $event['attendee_count']; ?> attending
                                                        <?php if ($event['total_tickets'] > 0): ?>
                                                            • <?php echo $event['available_tickets']; ?> tickets left
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons & Time -->
                                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                                <div class="btn-group">
                                                    <a href="event_details.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['user_id']): ?>
                                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-edit me-1"></i>Edit
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-<?php 
                                                    $event_date = strtotime($event['event_date']);
                                                    $now = time();
                                                    $diff = $event_date - $now;
                                                    
                                                    if ($diff < 0) {
                                                        echo 'success fw-bold"><i class="fas fa-play-circle me-1"></i>Live';
                                                    } elseif ($diff < 3600) {
                                                        echo 'danger fw-bold"><i class="fas fa-clock me-1"></i>' . ceil($diff / 60) . 'm';
                                                    } elseif ($diff < 86400) {
                                                        echo 'warning"><i class="fas fa-clock me-1"></i>' . ceil($diff / 3600) . 'h';
                                                    } else {
                                                        echo 'muted"><i class="fas fa-clock me-1"></i>' . ceil($diff / 86400) . 'd';
                                                    }
                                                ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- View All Events Section -->
                        <div class="text-center mt-5 pt-4 border-top animate__animated animate__fadeIn">
                            <h4 class="text-muted mb-3">Want to see more?</h4>
                            <a href="all_events.php" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-calendar-alt me-2"></i>View All Events
                            </a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="add_event.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Create New Event
                                </a>
                                <p class="text-muted mt-3">
                                    Can't find what you're looking for? 
                                    <a href="add_event.php" class="text-decoration-none fw-bold">Create your own event</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Back to top functionality
                function scrollToTop() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                window.addEventListener('scroll', function() {
                    const backToTop = document.querySelector('.back-to-top');
                    if (window.pageYOffset > 300) {
                        backToTop.classList.add('visible');
                    } else {
                        backToTop.classList.remove('visible');
                    }
                });

                // Add animation to elements when they come into view
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };

                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animation = 'fadeIn 0.8s ease-out forwards';
                            observer.unobserve(entry.target);
                        }
                    });
                }, observerOptions);

                // Observe all cards for animation
                document.addEventListener('DOMContentLoaded', function() {
                    const cards = document.querySelectorAll('.event-card');
                    cards.forEach(card => {
                        observer.observe(card);
                    });
                });
            </script>
<?php
    }

    public function form_content($conf, $FormObject, $FlashMessageObject)
    {
?>
            <!-- Outer container with a smooth gradient background -->
            <div class="d-flex justify-content-center align-items-center vh-100">

                <!-- Card container with a subtle shadow and rounded edges -->
                <div class="col-md-6 col-lg-5">
                    <div class="h-100 p-5 rounded-4 shadow-lg text-white animate__animated animate__fadeIn"
                        style="background: linear-gradient(160deg, #84898cff, #2c3e50);">

                        <!-- Optional header -->
                        <h2 class="mb-4 text-center fw-bold">Welcome to EventHub</h2>

                        <?php
                        $page = basename($_SERVER['PHP_SELF']);

                        if ($page == 'signup.php') {
                            $FormObject->signup($conf, $FlashMessageObject);
                        } elseif ($page == 'signin.php') {
                            $FormObject->signin($conf, $FlashMessageObject);
                        }
                        ?>

                    </div>
                </div>
            </div>
<?php
    }

    public function footer($conf)
    {
?>
            <footer class="bg-dark text-white py-4">
                <div class="container">
                    <div class="d-flex flex-column align-items-center text-center">
                        <h3 class="h5 mb-2 footer-logo"><?php echo $conf['site_name']; ?></h3>
                        <p class="text-secondary mb-3" style="max-width: 500px; font-size: 0.85rem;">
                            Your all-in-one platform for events, connecting people with amazing experiences.
                        </p>
                        <div class="mb-3">
                            <a href="dashboard.php" class="text-secondary text-decoration-none me-3 hover-effect" style="font-size: 0.9rem;">Home</a>
                            <a href="all_events.php" class="text-secondary text-decoration-none me-3 hover-effect" style="font-size: 0.9rem;">Events</a>
                            <a href="my_events.php" class="text-secondary text-decoration-none hover-effect" style="font-size: 0.9rem;">My Events</a>
                        </div>
                        <div class="mb-3">
                            <a href="#" class="social-icon me-2">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-icon me-2">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <a href="#" class="social-icon me-2">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="#" class="social-icon">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                        <div class="border-top border-secondary w-100 mt-3 pt-3">
                            <p class="text-secondary text-sm mb-0" style="font-size: 0.85rem;">
                                &copy; <?php echo date('Y'); ?> <?php echo $conf['site_name']; ?>. All rights reserved.
                            </p>
                        </div>
                    </div>
                </div>
            </footer>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        </body>

        </html>
<?php
    }
}
?>