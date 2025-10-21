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

// Handle actions
if (isset($_GET['action'])) {
    try {
        $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        switch ($_GET['action']) {
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $FlashMessageObject->setMsg('msg', 'All notifications marked as read', 'success');
                break;
            case 'clear_all':
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $FlashMessageObject->setMsg('msg', 'All notifications cleared', 'success');
                break;
        }
    } catch (PDOException $e) {
        error_log("Error performing action: " . $e->getMessage());
        $FlashMessageObject->setMsg('msg', 'Error performing action', 'danger');
    }
    header("Location: notifications.php");
    exit;
}

// Mark notifications as read when page is loaded (only on first page)
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

<!-- Enhanced CSS with modern design -->
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --dark-gradient: linear-gradient(135deg, #868f96 0%, #596164 100%);
}

/* Modern animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1); opacity: 1; }
}

/* Enhanced element styles */
.page-load-animation {
    animation: slideInUp 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.notification-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 2rem;
    margin: -1px -1px 0 -1px;
}

.badge-glow {
    box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
    animation: pulse 2s infinite;
}

.notification-item {
    animation: slideInUp 0.6s ease-out forwards;
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: none;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 0.5rem;
    border-radius: 15px !important;
    padding: 1.25rem;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    transform: translateX(8px) scale(1.02);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.notification-item.unread {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
    border-left: 4px solid #007bff;
    animation: slideInUp 0.6s ease-out forwards, bounceIn 0.8s 0.3s forwards;
}

.notification-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: all 0.3s ease;
}

.notification-icon.reminder { background: var(--warning-gradient); color: white; }
.notification-icon.new-event { background: var(--success-gradient); color: white; }
.notification-icon.update { background: var(--info-gradient); color: white; }
.notification-icon.confirmation { background: var(--success-gradient); color: white; }
.notification-icon.cancelled { background: var(--warning-gradient); color: white; }

.notification-item:hover .notification-icon {
    transform: scale(1.1) rotate(5deg);
}

.btn-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 25px;
    color: white;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-modern:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-modern:hover:before {
    left: 100%;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.empty-state {
    padding: 4rem 2rem;
    text-align: center;
}

.empty-state-icon {
    font-size: 4rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: float 3s ease-in-out infinite;
    margin-bottom: 1rem;
}

.pagination-modern .page-link {
    border: none;
    border-radius: 10px;
    margin: 0 0.25rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.pagination-modern .page-item.active .page-link {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.pagination-modern .page-link:hover {
    background: rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

/* Stagger animations */
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

/* Responsive design */
@media (max-width: 768px) {
    .notification-header {
        padding: 1.5rem;
        text-align: center;
    }
    
    .notification-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    
    .notification-item:hover {
        transform: none;
    }
}
</style>

<div class="container mt-4 page-load-animation">
    <!-- Enhanced Header -->
    <div class="notification-header text-white mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 mb-2">Your Notifications</h1>
                <p class="mb-0 opacity-75">Stay updated with your event activities</p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-warning badge-glow px-3 py-2">
                        <i class="fas fa-bell me-1"></i>
                        <?php echo $unread_count; ?> unread
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="btn-group">
            <a href="?action=mark_all_read" class="btn btn-success btn-modern">
                <i class="fas fa-check-double me-2"></i>Mark All Read
            </a>
            <a href="?action=clear_all" class="btn btn-outline-danger" 
               onclick="return confirm('Are you sure you want to clear all notifications?')">
                <i class="fas fa-trash me-2"></i>Clear All
            </a>
        </div>
        <a href="notification_settings.php" class="btn btn-outline-primary">
            <i class="fas fa-cog me-2"></i>Notification Settings
        </a>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <!-- Notifications Card -->
    <div class="card glass-card border-0 overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h3 class="text-muted mb-3">All Caught Up!</h3>
                    <p class="text-muted mb-4">You don't have any notifications at the moment.</p>
                    <a href="all_events.php" class="btn btn-modern">
                        <i class="fas fa-calendar-plus me-2"></i>Discover Events
                    </a>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush p-3">
                    <?php foreach ($notifications as $index => $notification): ?>
                        <div class="list-group-item list-group-item-action notification-item p-0 border-0 <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start p-3">
                                <div class="flex-shrink-0 me-3">
                                    <?php
                                    $icon_class = '';
                                    $type_class = '';
                                    switch($notification['notification_type']) {
                                        case 'event_reminder':
                                            $icon_class = 'fa-clock';
                                            $type_class = 'reminder';
                                            break;
                                        case 'new_event':
                                            $icon_class = 'fa-calendar-plus';
                                            $type_class = 'new-event';
                                            break;
                                        case 'event_update':
                                            $icon_class = 'fa-edit';
                                            $type_class = 'update';
                                            break;
                                        case 'rsvp_confirmation':
                                            $icon_class = 'fa-check-circle';
                                            $type_class = 'confirmation';
                                            break;
                                        case 'event_cancelled':
                                            $icon_class = 'fa-times-circle';
                                            $type_class = 'cancelled';
                                            break;
                                        default:
                                            $icon_class = 'fa-bell';
                                            $type_class = 'reminder';
                                    }
                                    ?>
                                    <div class="notification-icon <?php echo $type_class; ?>">
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 <?php echo !$notification['is_read'] ? 'fw-bold text-primary' : 'text-dark'; ?>">
                                            <?php echo $notification['title']; ?>
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary badge-pulse me-2">New</span>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <?php 
                                                $time_ago = time() - strtotime($notification['created_at']);
                                                if ($time_ago < 3600) {
                                                    echo ceil($time_ago / 60) . 'm ago';
                                                } elseif ($time_ago < 86400) {
                                                    echo ceil($time_ago / 3600) . 'h ago';
                                                } else {
                                                    echo date('M j, Y', strtotime($notification['created_at']));
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <p class="text-muted mb-2"><?php echo $notification['message']; ?></p>
                                    <?php if ($notification['event_id']): ?>
                                        <div class="mt-3">
                                            <a href="event_details.php?id=<?php echo $notification['event_id']; ?>" 
                                               class="btn btn-sm btn-modern me-2">
                                                <i class="fas fa-eye me-1"></i>View Event
                                            </a>
                                            <?php if ($notification['notification_type'] == 'event_reminder' && strtotime($notification['event_date']) > time()): ?>
                                                <a href="event_actions.php?action=attend&id=<?php echo $notification['event_id']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i>Confirm Attendance
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Notification pagination" class="mt-5">
            <ul class="pagination pagination-modern justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left me-1"></i>Previous
                    </a>
                </li>
                
                <?php 
                // Smart pagination - show limited pages
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; 
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                        Next<i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced hover effects
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        item.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });

    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn-modern, .btn-outline-primary');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
            `;
            
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Add scroll animation for cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe notification items for scroll animation
    notificationItems.forEach(item => {
        item.style.animationPlayState = 'paused';
        observer.observe(item);
    });
});

// Add ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php
$LayoutObject->footer($conf);
?>