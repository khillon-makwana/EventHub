<?php
require 'ClassAutoLoad.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$page = (int)($_GET['page'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

// Mark notifications as read when page is loaded
if ($page == 1) {
    try {
        $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
    }
}

// Get user's notifications
$notifications = [];
$total_notifications = 0;

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_notifications = $stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $limit);
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT n.*, e.title as event_title, e.id as event_id, e.event_date,
               CASE 
                 WHEN n.notification_type = 'event_reminder' THEN 'Event Reminder'
                 WHEN n.notification_type = 'new_event' THEN 'New Event'
                 WHEN n.notification_type = 'event_update' THEN 'Event Update'
                 WHEN n.notification_type = 'rsvp_confirmation' THEN 'RSVP Confirmation'
                 WHEN n.notification_type = 'event_cancelled' THEN 'Event Cancelled'
                 ELSE 'Notification'
               END as type_display
        FROM notifications n 
        LEFT JOIN events e ON n.event_id = e.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count for badge
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error loading notifications: ' . $e->getMessage(), 'danger');
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<!-- Custom CSS for animations -->
<style>
/* Page load animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
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

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    60% {
        transform: translateY(-5px);
    }
}

@keyframes slideInFromTop {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

/* Element animations */
.page-load-animation {
    animation: fadeInUp 0.6s ease-out forwards;
}

.card-animation {
    animation: fadeInUp 0.5s ease-out forwards;
    opacity: 0;
}

.notification-item {
    animation: fadeInLeft 0.5s ease-out forwards;
    opacity: 0;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.notification-item:hover {
    transform: translateX(5px);
    border-left-color: #007bff;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.notification-item.unread {
    animation: fadeInLeft 0.5s ease-out forwards, pulse 1s 0.5s;
    background: linear-gradient(90deg, rgba(248,249,250,1) 0%, rgba(248,249,250,1) 94%, rgba(13,110,253,0.1) 100%);
}

.badge-pulse {
    animation: pulse 2s infinite;
}

.dropdown-menu {
    animation: slideInFromTop 0.3s ease-out;
}

.btn-hover-animate {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-hover-animate:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-hover-animate:after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn-hover-animate:focus:after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(20, 20);
        opacity: 0;
    }
}

.pagination .page-item {
    transition: all 0.3s ease;
}

.pagination .page-item:hover {
    transform: translateY(-2px);
}

.empty-state-animation {
    animation: fadeInUp 0.8s ease-out forwards, bounce 1s 1s;
}

.icon-animation {
    transition: all 0.3s ease;
}

.icon-animation:hover {
    transform: scale(1.1) rotate(5deg);
}

/* Stagger animations for notification items */
.notification-item:nth-child(1) { animation-delay: 0.1s; }
.notification-item:nth-child(2) { animation-delay: 0.15s; }
.notification-item:nth-child(3) { animation-delay: 0.2s; }
.notification-item:nth-child(4) { animation-delay: 0.25s; }
.notification-item:nth-child(5) { animation-delay: 0.3s; }
.notification-item:nth-child(6) { animation-delay: 0.35s; }
.notification-item:nth-child(7) { animation-delay: 0.4s; }
.notification-item:nth-child(8) { animation-delay: 0.45s; }
.notification-item:nth-child(9) { animation-delay: 0.5s; }
.notification-item:nth-child(10) { animation-delay: 0.55s; }
.notification-item:nth-child(11) { animation-delay: 0.6s; }
.notification-item:nth-child(12) { animation-delay: 0.65s; }
.notification-item:nth-child(13) { animation-delay: 0.7s; }
.notification-item:nth-child(14) { animation-delay: 0.75s; }
.notification-item:nth-child(15) { animation-delay: 0.8s; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .notification-item:hover {
        transform: none;
    }
}
</style>

<div class="container mt-4 page-load-animation">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            Notifications 
            <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger ms-2 badge-pulse"><?php echo $unread_count; ?> unread</span>
            <?php endif; ?>
        </h2>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle btn-hover-animate" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-cog icon-animation"></i> Settings
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item btn-hover-animate" href="notification_settings.php">Notification Preferences</a></li>
                <li><a class="dropdown-item btn-hover-animate" href="notifications.php?action=mark_all_read">Mark All as Read</a></li>
                <li><a class="dropdown-item text-danger btn-hover-animate" href="notifications.php?action=clear_all" onclick="return confirm('Are you sure you want to clear all notifications?')">Clear All</a></li>
            </ul>
        </div>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <div class="card shadow-sm card-animation">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 empty-state-animation">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3 icon-animation"></i>
                    <h4 class="text-muted">No Notifications</h4>
                    <p class="text-muted">You don't have any notifications yet.</p>
                    <a href="all_events.php" class="btn btn-primary btn-hover-animate">Browse Events</a>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $index => $notification): ?>
                        <div class="list-group-item list-group-item-action notification-item <?php echo !$notification['is_read'] ? 'unread bg-light' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0 me-3">
                                    <?php
                                    $icon_class = '';
                                    $icon = '';
                                    switch($notification['notification_type']) {
                                        case 'event_reminder':
                                            $icon_class = 'text-warning';
                                            $icon = 'fa-clock';
                                            break;
                                        case 'new_event':
                                            $icon_class = 'text-primary';
                                            $icon = 'fa-calendar-plus';
                                            break;
                                        case 'event_update':
                                            $icon_class = 'text-info';
                                            $icon = 'fa-edit';
                                            break;
                                        case 'rsvp_confirmation':
                                            $icon_class = 'text-success';
                                            $icon = 'fa-check-circle';
                                            break;
                                        case 'event_cancelled':
                                            $icon_class = 'text-danger';
                                            $icon = 'fa-times-circle';
                                            break;
                                        default:
                                            $icon_class = 'text-secondary';
                                            $icon = 'fa-bell';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon; ?> fa-lg <?php echo $icon_class; ?> icon-animation"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                            <?php echo $notification['title']; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php 
                                            $time_ago = time() - strtotime($notification['created_at']);
                                            if ($time_ago < 3600) {
                                                echo ceil($time_ago / 60) . 'm ago';
                                            } elseif ($time_ago < 86400) {
                                                echo ceil($time_ago / 3600) . 'h ago';
                                            } else {
                                                echo ceil($time_ago / 86400) . 'd ago';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo $notification['message']; ?></p>
                                    <?php if ($notification['event_id']): ?>
                                        <div class="mt-2">
                                            <a href="event_details.php?id=<?php echo $notification['event_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary btn-hover-animate">
                                                View Event
                                            </a>
                                            <?php if ($notification['notification_type'] == 'event_reminder' && strtotime($notification['event_date']) > time()): ?>
                                                <a href="event_actions.php?action=attend&id=<?php echo $notification['event_id']; ?>" 
                                                   class="btn btn-sm btn-success btn-hover-animate">
                                                    Confirm Attendance
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <div class="flex-shrink-0 ms-3">
                                        <span class="badge bg-primary badge-pulse">New</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Notification pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link btn-hover-animate" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link btn-hover-animate" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link btn-hover-animate" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
// Add some JavaScript to enhance animations
document.addEventListener('DOMContentLoaded', function() {
    // Add click animation to buttons
    const buttons = document.querySelectorAll('.btn-hover-animate');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add a temporary active class for click feedback
            this.classList.add('active');
            setTimeout(() => {
                this.classList.remove('active');
            }, 300);
        });
    });
    
    // Add subtle animation to notification icons on hover
    const notificationIcons = document.querySelectorAll('.notification-item .icon-animation');
    notificationIcons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.2) rotate(10deg)';
        });
        icon.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
        });
    });
    
    // Add parallax effect to card on scroll
    const card = document.querySelector('.card');
    if (card) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.1;
            card.style.transform = `translateY(${rate}px)`;
        });
    }
});
</script>

<?php
$LayoutObject->footer($conf);
?>
