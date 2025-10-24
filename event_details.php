<?php
require 'ClassAutoLoad.php';

// Get event ID from URL
$event_id = (int)($_GET['id'] ?? 0);
if ($event_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch event data
$event = null;
$is_owner = false;
$is_attending = false;
$attendee_status = null;
$user_feedback = null;

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get event with details
    $stmt = $pdo->prepare("
        SELECT e.*, u.fullname as organizer_name,
               COUNT(DISTINCT ea.id) as attendee_count,
               GROUP_CONCAT(DISTINCT ac.name SEPARATOR ', ') as category_names
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN event_attendees ea ON e.id = ea.event_id 
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN attendee_categories ac ON ec.category_id = ac.id
        WHERE e.id = ? AND e.status IN ('upcoming', 'ongoing', 'completed')
        GROUP BY e.id
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $FlashMessageObject->setMsg('msg', 'Event not found or not available', 'danger');
        header("Location: dashboard.php");
        exit;
    }

    // Check if current user is owner
    if (isset($_SESSION['user_id'])) {
        $is_owner = ($event['user_id'] == $_SESSION['user_id']);

        // Check if user is attending and get status
        $stmt = $pdo->prepare("SELECT status FROM event_attendees WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance) {
            $is_attending = true;
            $attendee_status = $attendance['status'];
        }

        // Check if user already left feedback
        $stmt = $pdo->prepare("SELECT rating, comment FROM feedback WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $user_feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    :root {
        --gradient-cosmic: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        --gradient-sunset: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        --gradient-ocean: linear-gradient(135deg, #2af598 0%, #009efd 100%);
        --gradient-dark: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        --gradient-fire: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
        --glass-bg: rgba(255, 255, 255, 0.1);
        --glass-border: rgba(255, 255, 255, 0.2);
        --shadow-glow: 0 8px 32px rgba(102, 126, 234, 0.3);
        --shadow-elevation: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.1) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }

    .container {
        position: relative;
        z-index: 1;
    }

    /* Glassmorphism Card */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glass-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    /* Hero Image with Parallax Effect */
    .event-hero-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        height: 500px;
        margin-bottom: 3rem;
    }

    .event-hero-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .event-hero-wrapper:hover .event-hero-image {
        transform: scale(1.08);
    }

    .event-hero-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, transparent 100%);
        padding: 2rem;
        color: white;
    }

    /* Animated Title */
    .event-title {
        font-size: 3.5rem;
        font-weight: 800;
        background: var(--gradient-cosmic);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: gradient-shift 3s ease infinite;
        background-size: 200% 200%;
    }

    @keyframes gradient-shift {

        0%,
        100% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }
    }

    /* Floating Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.95rem;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255, 255, 255, 0.3);
        animation: float 3s ease-in-out infinite;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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

    .status-badge.upcoming {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }

    .status-badge.ongoing {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        animation: pulse-glow 2s ease-in-out infinite;
    }

    .status-badge.completed {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }

    @keyframes pulse-glow {

        0%,
        100% {
            box-shadow: 0 0 20px rgba(245, 87, 108, 0.5);
        }

        50% {
            box-shadow: 0 0 40px rgba(245, 87, 108, 0.8);
        }
    }

    /* Meta Cards with Hover Effects */
    .meta-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .meta-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: var(--gradient-cosmic);
        opacity: 0;
        transition: all 0.5s ease;
    }

    .meta-card:hover {
        transform: translateY(-5px) scale(1.02);
        border-color: #667eea;
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
    }

    .meta-card:hover::before {
        left: 0;
        opacity: 0.1;
    }

    .meta-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        background: var(--gradient-cosmic);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        margin-right: 1rem;
        transition: all 0.3s ease;
    }

    .meta-card:hover .meta-icon {
        transform: rotate(360deg) scale(1.1);
    }

    /* Category Tags with Animation */
    .category-tag {
        display: inline-flex;
        align-items: center;
        background: var(--gradient-cosmic);
        color: white;
        padding: 0.5rem 1.2rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0.3rem;
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .category-tag:hover {
        transform: translateY(-5px) scale(1.1);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
    }

    /* Action Button with Magnetic Effect */
    .action-btn {
        position: relative;
        padding: 1rem 2rem;
        border-radius: 16px;
        font-weight: 600;
        font-size: 1.1rem;
        border: none;
        cursor: pointer;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .action-btn-primary {
        background: var(--gradient-cosmic);
        color: white;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .action-btn-primary::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .action-btn-primary:hover::before {
        width: 300px;
        height: 300px;
    }

    .action-btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
    }

    /* Sticky Sidebar with Smooth Scroll */
    .sticky-sidebar {
        position: sticky;
        top: 100px;
        transition: all 0.3s ease;
    }

    /* Feedback Card with Slide-in Animation */
    .feedback-item {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        opacity: 0;
        transform: translateX(-30px);
        animation: slideInLeft 0.6s ease forwards;
    }

    @keyframes slideInLeft {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .feedback-item:nth-child(1) {
        animation-delay: 0.1s;
    }

    .feedback-item:nth-child(2) {
        animation-delay: 0.2s;
    }

    .feedback-item:nth-child(3) {
        animation-delay: 0.3s;
    }

    /* Star Rating with Bounce */
    .star-rating {
        display: inline-flex;
        gap: 8px;
    }

    .star-rating label {
        cursor: pointer;
        font-size: 2rem;
        color: #e0e0e0;
        transition: all 0.2s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }

    .star-rating label:hover {
        color: #ffc107;
        transform: scale(1.3) rotate(10deg);
    }

    .star-rating input:checked~label,
    .star-rating label.active {
        color: #ffc107;
        animation: starPop 0.3s ease;
    }

    @keyframes starPop {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.4) rotate(15deg);
        }
    }

    /* Stats Cards with Counter Animation */
    .stat-card {
        background: var(--gradient-cosmic);
        border-radius: 20px;
        padding: 1.5rem;
        text-align: center;
        color: white;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transform: rotate(45deg);
        animation: shine 3s infinite;
    }

    @keyframes shine {
        0% {
            transform: translateX(-100%) translateY(-100%) rotate(45deg);
        }

        100% {
            transform: translateX(100%) translateY(100%) rotate(45deg);
        }
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.05);
        box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5);
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    /* Share Buttons with Ripple Effect */
    .share-btn {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 1rem;
        transition: all 0.3s ease;
        border: 2px solid #e0e0e0;
        background: white;
    }

    .share-btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(102, 126, 234, 0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .share-btn:hover {
        transform: translateY(-5px);
        border-color: #667eea;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .share-btn:hover::after {
        width: 300px;
        height: 300px;
    }

    /* Back Button with Slide Animation */
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 50px;
        background: white;
        color: #333;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .back-btn:hover {
        transform: translateX(-10px);
        background: var(--gradient-cosmic);
        color: white;
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
    }

    /* Similar Event Card with Tilt Effect */
    .similar-event-card {
        background: white;
        border-radius: 20px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        text-decoration: none;
        display: block;
        color: inherit;
    }

    .similar-event-card:hover {
        transform: translateY(-5px) rotateY(5deg);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    }

    /* Avatar with Glow Effect */
    .avatar-glow {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gradient-cosmic);
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        animation: glow-pulse 2s ease-in-out infinite;
    }

    @keyframes glow-pulse {

        0%,
        100% {
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }

        50% {
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.8);
        }
    }

    /* Scroll Reveal Animation */
    .scroll-reveal {
        opacity: 0;
        transform: translateY(50px);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .scroll-reveal.revealed {
        opacity: 1;
        transform: translateY(0);
    }

    /* Loading Skeleton */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .event-title {
            font-size: 2rem;
        }

        .event-hero-wrapper {
            height: 300px;
        }

        .sticky-sidebar {
            position: relative;
            top: 0;
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 12px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--gradient-cosmic);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }
</style>

<div class="container mt-4">
    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <!-- Back Button -->
    <div class="mb-4">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Events</span>
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Event Title Section -->
            <div class="glass-card p-4 mb-4 scroll-reveal">
                <h1 class="event-title mb-3"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="status-badge <?php echo strtolower($event['status']); ?>">
                        <i class="fas fa-<?php
                                            switch ($event['status']) {
                                                case 'upcoming':
                                                    echo 'clock';
                                                    break;
                                                case 'ongoing':
                                                    echo 'broadcast-tower';
                                                    break;
                                                case 'completed':
                                                    echo 'check-circle';
                                                    break;
                                                default:
                                                    echo 'calendar';
                                            }
                                            ?>"></i>
                        <span><?php echo ucfirst($event['status']); ?></span>
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-glow">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <strong class="d-block"><?php echo htmlspecialchars($event['organizer_name']); ?></strong>
                            <small class="text-muted">Organizer</small>
                        </div>
                    </div>
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-users me-2"></i>
                        <?php echo $event['attendee_count']; ?> attending
                    </span>
                </div>
            </div>

            <!-- Event Hero Image -->
            <div class="scroll-reveal">
                <?php if ($event['image']): ?>
                    <div class="event-hero-wrapper">
                        <img src="<?php echo htmlspecialchars($event['image']); ?>"
                            class="event-hero-image"
                            alt="<?php echo htmlspecialchars($event['title']); ?>">
                    </div>
                <?php else: ?>
                    <div class="event-hero-wrapper" style="background: var(--gradient-cosmic); display: flex; align-items: center; justify-content: center;">
                        <div class="text-center text-white">
                            <i class="fas fa-calendar-star" style="font-size: 5rem; opacity: 0.8;"></i>
                            <h3 class="mt-3"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Event Details -->
            <div class="glass-card p-4 mb-4 scroll-reveal">
                <h4 class="mb-4">
                    <i class="fas fa-info-circle me-2" style="color: #667eea;"></i>
                    Event Details
                </h4>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="meta-card">
                            <div class="d-flex align-items-center">
                                <div class="meta-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <strong class="d-block mb-1">Location</strong>
                                    <span class="text-muted"><?php echo htmlspecialchars($event['location']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="meta-card">
                            <div class="d-flex align-items-center">
                                <div class="meta-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <strong class="d-block mb-1">Date & Time</strong>
                                    <span class="text-muted"><?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 🎟️ Ticket Price -->
                    <div class="col-md-6">
                        <div class="meta-card">
                            <div class="d-flex align-items-center">
                                <div class="meta-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div>
                                    <strong class="d-block mb-1">Ticket Price</strong>
                                    <span class="text-muted">
                                        <?php echo $event['ticket_price'] > 0
                                            ? 'KSh ' . number_format($event['ticket_price'], 2)
                                            : 'Free'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="meta-card">
                            <div class="d-flex align-items-center">
                                <div class="meta-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div>
                                    <strong class="d-block mb-1">Tickets</strong>
                                    <span class="text-muted">
                                        <?php echo $event['available_tickets']; ?> / <?php echo $event['total_tickets']; ?> available
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="meta-card">
                            <div class="d-flex align-items-center">
                                <div class="meta-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <strong class="d-block mb-1">Created</strong>
                                    <span class="text-muted"><?php echo date('F j, Y', strtotime($event['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($event['category_names']): ?>
                    <div class="mb-4">
                        <strong class="d-block mb-3">
                            <i class="fas fa-tags me-2" style="color: #667eea;"></i>
                            Categories
                        </strong>
                        <?php
                        $categories = explode(', ', $event['category_names']);
                        foreach ($categories as $category): ?>
                            <span class="category-tag"><?php echo htmlspecialchars(trim($category)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                

                <div>
                    <strong class="d-block mb-3">
                        <i class="fas fa-align-left me-2" style="color: #667eea;"></i>
                        Description
                    </strong>
                    <p style="line-height: 1.8; font-size: 1.05rem;"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
            </div>


            <!-- Feedback Section -->
            <div class="glass-card p-4 scroll-reveal">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2" style="color: #667eea;"></i>
                        Feedback & Reviews
                    </h5>
                    <span class="badge bg-gradient" style="background: var(--gradient-cosmic); padding: 0.5rem 1rem; border-radius: 50px;">
                        <?php
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE event_id = ?");
                            $stmt->execute([$event_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo '0';
                        }
                        ?>
                    </span>
                </div>

                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT f.*, u.fullname 
                        FROM feedback f 
                        JOIN users u ON f.user_id = u.id 
                        WHERE f.event_id = ? 
                        ORDER BY f.created_at DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$event_id]);
                    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($feedbacks)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comment-slash" style="font-size: 4rem; color: #e0e0e0; margin-bottom: 1rem;"></i>
                            <p class="text-muted">No reviews yet. Be the first to leave feedback!</p>
                        </div>
                        <?php else:
                        foreach ($feedbacks as $feedback): ?>
                            <div class="feedback-item">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="avatar-glow" style="width: 45px; height: 45px; font-size: 1rem;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($feedback['fullname']); ?></strong>
                                            <div class="star-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star" style="font-size: 1rem; color: <?php echo $i <= $feedback['rating'] ? '#ffc107' : '#e0e0e0'; ?>;"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if ($feedback['comment']): ?>
                                            <p class="mb-2"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                <?php endforeach;
                    endif;
                } catch (PDOException $e) {
                    echo '<div class="alert alert-warning">Error loading feedback</div>';
                }
                ?>

                <!-- Feedback Form -->
                <?php if ($is_attending && $attendee_status == 'going' && in_array($event['status'], ['ongoing', 'completed'])): ?>
                    <div class="glass-card p-4 mt-4" style="background: rgba(102, 126, 234, 0.05);">
                        <h6 class="mb-3">
                            <i class="fas fa-edit me-2" style="color: #667eea;"></i>
                            <?php echo $user_feedback ? 'Update Your Feedback' : 'Leave Your Feedback'; ?>
                        </h6>

                        <form method="POST" action="event_actions.php">
                            <input type="hidden" name="action" value="submit_feedback">
                            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">

                            <div class="mb-4">
                                <label class="form-label fw-bold">Rating</label>
                                <div class="star-rating" id="starRating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>"
                                            <?php echo ($user_feedback && $user_feedback['rating'] == $i) ? 'checked' : ''; ?> required>
                                        <label for="star<?php echo $i; ?>">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="comment" class="form-label fw-bold">Comment (Optional)</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4"
                                    placeholder="Share your experience..."
                                    style="border-radius: 16px; border: 2px solid #e0e0e0;"><?php echo $user_feedback ? htmlspecialchars($user_feedback['comment']) : ''; ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn action-btn action-btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    <?php echo $user_feedback ? 'Update' : 'Submit'; ?>
                                </button>
                                <?php if ($user_feedback): ?>
                                    <a href="event_actions.php?action=delete_feedback&id=<?php echo $event_id; ?>"
                                        class="btn btn-outline-danger"
                                        style="border-radius: 16px; padding: 1rem 2rem;"
                                        onclick="return confirm('Delete your feedback?')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php elseif ($is_attending && $event['status'] == 'upcoming'): ?>
                    <div class="alert alert-info mt-3" style="border-radius: 16px; border-left: 4px solid #667eea;">
                        <i class="fas fa-clock me-2"></i>
                        Feedback available once the event starts.
                    </div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <div class="text-center mt-4 p-4" style="background: rgba(102, 126, 234, 0.05); border-radius: 20px;">
                        <i class="fas fa-sign-in-alt" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
                        <h6>Sign in to leave feedback</h6>
                        <a href="signin.php" class="btn action-btn action-btn-primary mt-3">Sign In</a>
                    </div>
                <?php elseif (!$is_attending): ?>
                    <div class="alert alert-info mt-3" style="border-radius: 16px;">
                        <i class="fas fa-info-circle me-2"></i>
                        RSVP as "going" to leave feedback.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="sticky-sidebar">
                <!-- Action Card -->
                <div class="glass-card p-4 mb-4 scroll-reveal">
                    <h5 class="mb-4">
                        <i class="fas fa-bolt me-2" style="color: #667eea;"></i>
                        Event Actions
                    </h5>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($is_owner): ?>
                            <div class="d-grid gap-3">
                                <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn action-btn action-btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Event
                                </a>

                                <?php if ($event['status'] == 'upcoming'): ?>
                                    <a href="event_actions.php?action=mark_ongoing&id=<?php echo $event['id']; ?>"
                                        class="btn btn-success" style="border-radius: 16px; padding: 1rem;"
                                        onclick="return confirm('Start this event?')">
                                        <i class="fas fa-play-circle me-2"></i>Start Event
                                    </a>
                                    <a href="event_actions.php?action=cancel&id=<?php echo $event['id']; ?>"
                                        class="btn btn-outline-danger" style="border-radius: 16px; padding: 1rem;"
                                        onclick="return confirm('Cancel this event?')">
                                        <i class="fas fa-times-circle me-2"></i>Cancel
                                    </a>
                                <?php elseif ($event['status'] == 'ongoing'): ?>
                                    <a href="event_actions.php?action=mark_completed&id=<?php echo $event['id']; ?>"
                                        class="btn btn-success" style="border-radius: 16px; padding: 1rem;"
                                        onclick="return confirm('Complete this event?')">
                                        <i class="fas fa-check-circle me-2"></i>Complete
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Stats -->
                            <div class="mt-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-chart-bar me-2" style="color: #667eea;"></i>
                                    Statistics
                                </h6>
                                <div class="row g-3">
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-number"><?php echo $event['attendee_count']; ?></div>
                                            <div style="font-size: 0.8rem;">Attendees</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-number"><?php echo $event['total_tickets'] - $event['available_tickets']; ?></div>
                                            <div style="font-size: 0.8rem;">Sold</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-number"><?php echo $event['available_tickets']; ?></div>
                                            <div style="font-size: 0.8rem;">Available</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="d-grid gap-3">
                                <?php if ($is_attending): ?>
                                    <button class="btn btn-success" style="border-radius: 16px; padding: 1rem; font-weight: 600;" disabled>
                                        <i class="fas fa-check-circle me-2"></i>
                                        You're <?php echo ucfirst($attendee_status); ?>
                                    </button>

                                    <?php if ($event['status'] == 'upcoming' || $event['status'] == 'ongoing'): ?>
                                        <a href="event_actions.php?action=unattend&id=<?php echo $event['id']; ?>"
                                            class="btn btn-outline-danger" style="border-radius: 16px; padding: 1rem;"
                                            onclick="return confirm('Cancel attendance?')">
                                            <i class="fas fa-times me-2"></i>Cancel Attendance
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($event['status'] == 'upcoming' || $event['status'] == 'ongoing'): ?>
                                        <?php if ($event['available_tickets'] > 0 || $event['total_tickets'] == 0): ?>
                                            <a href="event_actions.php?action=attend&id=<?php echo $event['id']; ?>"
                                                class="btn action-btn action-btn-primary">
                                                <i class="fas fa-calendar-check me-2"></i>Attend Event
                                            </a>
                                            <a href="event_actions.php?action=interested&id=<?php echo $event['id']; ?>"
                                                class="btn btn-outline-warning" style="border-radius: 16px; padding: 1rem;">
                                                <i class="fas fa-star me-2"></i>Interested
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" style="border-radius: 16px; padding: 1rem;" disabled>
                                                <i class="fas fa-exclamation-triangle me-2"></i>Event Full
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" style="border-radius: 16px; padding: 1rem;" disabled>
                                            <i class="fas fa-clock me-2"></i>Event Ended
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center p-4" style="background: rgba(102, 126, 234, 0.05); border-radius: 20px;">
                            <i class="fas fa-sign-in-alt" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
                            <h6>Sign in to RSVP</h6>
                            <a href="signin.php" class="btn action-btn action-btn-primary mt-3">Sign In</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Share Card -->
                <div class="glass-card p-4 mb-4 scroll-reveal">
                    <h6 class="mb-3">
                        <i class="fas fa-share-alt me-2" style="color: #667eea;"></i>
                        Share Event
                    </h6>
                    <div class="d-grid gap-2">
                        <button class="share-btn" onclick="shareEvent('facebook')">
                            <i class="fab fa-facebook me-2" style="color: #1877f2;"></i>Facebook
                        </button>
                        <button class="share-btn" onclick="shareEvent('twitter')">
                            <i class="fab fa-twitter me-2" style="color: #1da1f2;"></i>Twitter
                        </button>
                        <button class="share-btn" onclick="shareEvent('whatsapp')">
                            <i class="fab fa-whatsapp me-2" style="color: #25d366;"></i>WhatsApp
                        </button>
                        <button class="share-btn" onclick="shareEvent('linkedin')">
                            <i class="fab fa-linkedin me-2" style="color: #0a66c2;"></i>LinkedIn
                        </button>
                    </div>
                </div>

                <!-- Similar Events -->
                <div class="glass-card p-4 scroll-reveal">
                    <h6 class="mb-3">
                        <i class="fas fa-calendar-plus me-2" style="color: #667eea;"></i>
                        Similar Events
                    </h6>
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT e.id, e.title, e.location, e.event_date, e.image, e.status
                            FROM events e 
                            JOIN event_categories ec ON e.id = ec.event_id 
                            WHERE ec.category_id IN (
                                SELECT category_id FROM event_categories WHERE event_id = ?
                            ) 
                            AND e.id != ? 
                            AND e.status IN ('upcoming', 'ongoing')
                            GROUP BY e.id 
                            ORDER BY e.event_date ASC 
                            LIMIT 3
                        ");
                        $stmt->execute([$event_id, $event_id]);
                        $similar_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($similar_events)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #e0e0e0; margin-bottom: 1rem;"></i>
                                <p class="text-muted small">No similar events found.</p>
                            </div>
                            <?php else:
                            foreach ($similar_events as $similar_event): ?>
                                <a href="event_details.php?id=<?php echo $similar_event['id']; ?>" class="similar-event-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($similar_event['image']): ?>
                                            <img src="<?php echo htmlspecialchars($similar_event['image']); ?>"
                                                style="width: 70px; height: 70px; object-fit: cover; border-radius: 12px;">
                                        <?php else: ?>
                                            <div style="width: 70px; height: 70px; background: var(--gradient-cosmic); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <strong class="d-block mb-1" style="font-size: 0.95rem;"><?php echo htmlspecialchars($similar_event['title']); ?></strong>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($similar_event['location']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M j, Y', strtotime($similar_event['event_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </a>
                    <?php endforeach;
                        endif;
                    } catch (PDOException $e) {
                        echo '<p class="text-muted text-center">Error loading events</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function shareEvent(platform) {
        const url = encodeURIComponent(window.location.href);
        const title = encodeURIComponent("<?php echo addslashes($event['title']); ?>");
        const text = encodeURIComponent("Check out this event: <?php echo addslashes($event['title']); ?>");

        let shareUrl = '';

        switch (platform) {
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                break;
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                break;
            case 'whatsapp':
                shareUrl = `https://wa.me/?text=${text}%20${url}`;
                break;
            case 'linkedin':
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                break;
        }

        window.open(shareUrl, '_blank', 'width=600,height=400');
    }

    // Star rating interaction
    document.addEventListener('DOMContentLoaded', function() {
        const starRating = document.getElementById('starRating');
        if (starRating) {
            const inputs = starRating.querySelectorAll('input');
            const labels = starRating.querySelectorAll('label');

            inputs.forEach((input, index) => {
                input.addEventListener('change', function() {
                    labels.forEach((label, i) => {
                        if (i >= 5 - this.value) {
                            label.classList.add('active');
                        } else {
                            label.classList.remove('active');
                        }
                    });
                });
            });

            // Hover effect
            labels.forEach((label, index) => {
                label.addEventListener('mouseenter', function() {
                    for (let i = labels.length - 1; i >= index; i--) {
                        labels[i].style.color = '#ffc107';
                    }
                });
            });

            starRating.addEventListener('mouseleave', function() {
                const checked = starRating.querySelector('input:checked');
                labels.forEach((label, i) => {
                    if (checked && i >= 5 - checked.value) {
                        label.style.color = '#ffc107';
                    } else {
                        label.style.color = '#e0e0e0';
                    }
                });
            });
        }

        // Scroll reveal animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.scroll-reveal').forEach(el => {
            observer.observe(el);
        });
    });
</script>

<?php
$LayoutObject->footer($conf);
?>