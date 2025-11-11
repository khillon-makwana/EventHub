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
                    display: flex;
                    flex-direction: column;
                }

                .main-content {
                    flex: 1 0 auto;
                }

                .footer-section {
                    flex-shrink: 0;
                }

                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes slideInDown {
                    from {
                        transform: translateY(-100%);
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                @keyframes slideInUp {
                    from {
                        transform: translateY(100%);
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                @keyframes pulse {
                    0% {
                        transform: scale(1);
                    }

                    50% {
                        transform: scale(1.05);
                    }

                    100% {
                        transform: scale(1);
                    }
                }

                @keyframes float {

                    0%,
                    100% {
                        transform: translateY(0);
                    }

                    50% {
                        transform: translateY(-10px);
                    }
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

                /* Enhanced Header Styles */
                .main-header {
                    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%);
                    border-bottom: 1px solid rgba(229, 231, 235, 0.8);
                    backdrop-filter: blur(20px);
                    animation: slideInDown 0.8s ease-out;
                    position: sticky;
                    top: 0;
                    z-index: 1000;
                    box-shadow: var(--shadow-soft);
                    transition: var(--transition-smooth);
                }

                .main-header.scrolled {
                    background: rgba(255, 255, 255, 0.98);
                    box-shadow: var(--shadow-medium);
                }

                .brand-logo {
                    font-family: 'Pacifico', cursive;
                    font-size: 1.8rem;
                    background: var(--gradient-primary);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    transition: var(--transition-smooth);
                }

                .brand-logo:hover {
                    transform: scale(1.05);
                    background: var(--gradient-secondary);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .nav-link-custom {
                    color: #374151 !important;
                    font-weight: 500;
                    transition: var(--transition-smooth);
                    position: relative;
                    padding: 0.75rem 1.25rem !important;
                    border-radius: 12px;
                    margin: 0 0.25rem;
                }

                .nav-link-custom::before {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    width: 0;
                    height: 3px;
                    background: var(--gradient-primary);
                    transition: var(--transition-smooth);
                    transform: translateX(-50%);
                    border-radius: 3px;
                }

                .nav-link-custom:hover {
                    color: var(--primary-color) !important;
                    background: rgba(79, 70, 229, 0.05);
                    transform: translateY(-2px);
                }

                .nav-link-custom:hover::before {
                    width: 60%;
                }

                .nav-link-custom.active {
                    color: var(--primary-color) !important;
                    font-weight: 600;
                    background: rgba(79, 70, 229, 0.08);
                }

                .nav-link-custom.active::before {
                    width: 60%;
                }

                .user-section {
                    transition: var(--transition-smooth);
                    padding: 0.75rem 1rem;
                    border-radius: 16px;
                    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.05) 100%);
                    border: 1px solid rgba(79, 70, 229, 0.1);
                }

                .user-section:hover {
                    background: linear-gradient(135deg, rgba(79, 70, 229, 0.12) 0%, rgba(124, 58, 237, 0.08) 100%);
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-soft);
                }

                .notification-badge {
                    font-size: 0.65rem;
                    padding: 0.25em 0.5em;
                    animation: pulse 2s infinite;
                }

                .sign-out-btn {
                    background: var(--gradient-primary);
                    color: #ffffff;
                    border: none;
                    border-radius: 12px;
                    padding: 0.6rem 1.4rem;
                    font-size: 0.9rem;
                    font-weight: 600;
                    transition: var(--transition-bounce);
                    box-shadow: var(--shadow-soft);
                    position: relative;
                    overflow: hidden;
                }

                .sign-out-btn::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                    transition: var(--transition-smooth);
                }

                .sign-out-btn:hover {
                    background: var(--gradient-secondary);
                    transform: translateY(-3px) scale(1.05);
                    box-shadow: var(--shadow-medium);
                    color: #ffffff;
                }

                .sign-out-btn:hover::before {
                    left: 100%;
                }

                /* Enhanced Footer Styles */
                .main-footer {
                    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
                    animation: slideInUp 0.8s ease-out;
                    position: relative;
                    overflow: hidden;
                }

                .main-footer::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: var(--gradient-primary);
                }

                .footer-logo {
                    font-family: 'Pacifico', cursive;
                    font-size: 1.8rem;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .footer-links {
                    display: flex;
                    gap: 2rem;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .footer-link {
                    color: #cbd5e0 !important;
                    text-decoration: none;
                    transition: var(--transition-smooth);
                    padding: 0.5rem 1rem;
                    border-radius: 8px;
                    position: relative;
                }

                .footer-link::before {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    width: 0;
                    height: 2px;
                    background: var(--gradient-primary);
                    transition: var(--transition-smooth);
                    transform: translateX(-50%);
                }

                .footer-link:hover {
                    color: white !important;
                    background: rgba(255, 255, 255, 0.05);
                    transform: translateY(-2px);
                }

                .footer-link:hover::before {
                    width: 80%;
                }

                .social-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.08);
                    color: #cbd5e0;
                    transition: var(--transition-bounce);
                    text-decoration: none;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }

                .social-icon:hover {
                    background: var(--gradient-primary);
                    color: white;
                    transform: translateY(-3px) rotate(8deg);
                    box-shadow: var(--shadow-medium);
                    border-color: transparent;
                }

                /* Mobile Navigation */
                .mobile-nav {
                    background: rgba(255, 255, 255, 0.98);
                    backdrop-filter: blur(20px);
                    border-top: 1px solid rgba(229, 231, 235, 0.8);
                    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
                }

                .mobile-nav-item {
                    transition: var(--transition-smooth);
                    padding: 0.75rem 0;
                    border-radius: 12px;
                    color: #374151;
                    text-decoration: none;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.25rem;
                }

                .mobile-nav-item:hover,
                .mobile-nav-item.active {
                    color: var(--primary-color);
                    background: rgba(79, 70, 229, 0.05);
                    transform: translateY(-2px);
                }

                .mobile-nav-item.active {
                    font-weight: 600;
                }

                /* Back to top button */
                .back-to-top {
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    width: 56px;
                    height: 56px;
                    border-radius: 50%;
                    background: var(--gradient-primary);
                    color: white;
                    border: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    visibility: hidden;
                    transition: var(--transition-bounce);
                    box-shadow: var(--shadow-medium);
                    z-index: 1000;
                    font-size: 1.2rem;
                }

                .back-to-top.visible {
                    opacity: 1;
                    visibility: visible;
                }

                .back-to-top:hover {
                    transform: translateY(-5px) scale(1.1) rotate(180deg);
                    background: var(--gradient-secondary);
                }

                /* Enhanced Event Card Styles (keeping your existing styles) */
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

                /* Action buttons section - only show once */
                .action-buttons-section {
                    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 2rem;
                    margin: 2rem auto;
                    max-width: 800px;
                    box-shadow: var(--shadow-soft);
                    border: 1px solid rgba(255, 255, 255, 0.8);
                }
            </style>
        </head>

        <body class="bg-light">
            <div class="main-content">
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
                <header class="main-header w-100">
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
                                        <a href="dashboard.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-home me-2"></i>HOME
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="all_events.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'all_events.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-calendar-alt me-2"></i>EVENTS
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="my_events.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'my_events.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-list me-2"></i>MY EVENTS
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="my_profile.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'my_profile.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-user me-2"></i>MY PROFILE
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="my_tickets.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'my_tickets.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-ticket-alt me-2"></i>TICKETS
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="reports_analytics.php" class="nav-link nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'reports_analytics.php' ? 'active' : ''; ?>">
                                            <i class="fas fa-chart-line-alt me-2"></i>ANALYTICS
                                        </a>
                                    </li>
                                </ul>
                            </nav>

                            <!-- User info and sign out on the right -->
                            <div class="user-section d-flex align-items-center gap-3">
                                <!-- Notifications Icon -->
                                <a href="notifications.php" class="nav-link position-relative p-0 me-2 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-bell fa-lg text-muted"></i>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
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
                <div class="d-md-none mobile-nav fixed-bottom py-2">
                    <div class="container">
                        <ul class="nav justify-content-around mb-0">
                            <li class="nav-item">
                                <a href="dashboard.php" class="mobile-nav-item text-center <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home fa-lg d-block mb-1"></i>
                                    <small>Home</small>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="all_events.php" class="mobile-nav-item text-center <?php echo basename($_SERVER['PHP_SELF']) == 'all_events.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt fa-lg d-block mb-1"></i>
                                    <small>Events</small>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="my_events.php" class="mobile-nav-item text-center <?php echo basename($_SERVER['PHP_SELF']) == 'my_events.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-list fa-lg d-block mb-1"></i>
                                    <small>My Events</small>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="notifications.php" class="mobile-nav-item text-center position-relative <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-bell fa-lg d-block mb-1"></i>
                                    <small>Notifications</small>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="font-size: 0.6rem;">
                                            <?php echo $unread_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="my_profile.php" class="mobile-nav-item text-center <?php echo basename($_SERVER['PHP_SELF']) == 'my_profile.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user fa-lg d-block mb-1"></i>
                                    <small>Profile</small>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Back to top button -->
                <button class="back-to-top" onclick="scrollToTop()">
                    <i class="fas fa-chevron-up"></i>
                </button>

                <script>
                    // Header scroll effect
                    window.addEventListener('scroll', function() {
                        const header = document.querySelector('.main-header');
                        const backToTop = document.querySelector('.back-to-top');

                        if (window.pageYOffset > 100) {
                            header.classList.add('scrolled');
                        } else {
                            header.classList.remove('scrolled');
                        }

                        if (window.pageYOffset > 300) {
                            backToTop.classList.add('visible');
                        } else {
                            backToTop.classList.remove('visible');
                        }
                    });

                    function scrollToTop() {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }

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
                        COALESCE(SUM(ea.quantity), 0) as attendee_count,
                        COUNT(DISTINCT ec.category_id) as category_count,
                        GROUP_CONCAT(DISTINCT ac.name SEPARATOR ', ') as category_names
                    FROM events e 
                    LEFT JOIN users u ON e.user_id = u.id 
                    LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.status = 'going'
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
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Action Buttons Section - Only show this once -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="container">
                        <div class="action-buttons-section text-center animate__animated animate__fadeInUp">
                            <h4 class="mb-4">Ready to Get Started?</h4>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <a href="add_event.php" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-plus-circle me-2"></i>Create New Event
                                </a>
                                <a href="all_events.php" class="btn btn-outline-primary btn-lg px-4">
                                    <i class="fas fa-calendar-alt me-2"></i>View All Events
                                </a>
                            </div>
                            <p class="text-muted mt-3 mb-0">
                                Can't find what you're looking for? Create your own event and invite others to join!
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

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

                                                    <!-- Ticket Price -->
                                                    <?php if (!empty($event['ticket_price'])): ?>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fas fa-ticket-alt"></i>
                                                            <span> Price: KSh <?php echo number_format($event['ticket_price'], 2); ?></span>
                                                        </div>
                                                    <?php endif; ?>

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
                                                        <a href="manage_rsvps.php?event_id=<?php echo $event['id']; ?>"
                                                            class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-users me-1"></i>Manage RSVPs
                                                        </a>

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
                    <?php endif; ?>
                </div>
            </div>
        <?php
        }

        public function form_content($conf, $FormObject, $FlashMessageObject)
        {
        ?>
            <!-- Outer container with a smooth gradient background -->
            <div class=" d-flex justify-content-center align-items-center vh-100">

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
                                            </div> <!-- Close main-content -->

                                            <footer class="main-footer text-white py-5 footer-section">
                                                <div class="container">
                                                    <div class="row align-items-center">
                                                        <div class="col-lg-4 text-center text-lg-start mb-3 mb-lg-0">
                                                            <h3 class="h4 mb-2 footer-logo"><?php echo $conf['site_name']; ?></h3>
                                                            <p class="text-secondary mb-0" style="font-size: 0.9rem;">
                                                                Connecting people through unforgettable experiences.
                                                            </p>
                                                        </div>

                                                        <div class="col-lg-4 text-center mb-3 mb-lg-0">
                                                            <div class="footer-links">
                                                                <a href="dashboard.php" class="footer-link">Home</a>
                                                                <a href="all_events.php" class="footer-link">Events</a>
                                                                <a href="my_events.php" class="footer-link">My Events</a>
                                                                <a href="my_profile.php" class="footer-link">Profile</a>
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-4 text-center text-lg-end">
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
                                                            <p class="text-secondary mb-0" style="font-size: 0.85rem;">
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