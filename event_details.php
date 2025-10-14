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
    /* Event Details Specific Styles */
    .event-hero-image {
        height: 500px;
        object-fit: cover;
        border-radius: 20px;
        transition: transform 0.6s ease;
    }

    .event-hero-image:hover {
        transform: scale(1.02);
    }

    .event-header {
        background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.8) 100%);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        animation: slideInDown 0.8s ease-out;
    }

    .event-meta-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-soft);
        transition: var(--transition-smooth);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .event-meta-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .action-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-soft);
        position: sticky;
        top: 100px;
    }

    .status-badge {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        animation: pulse 2s infinite;
    }

    .feedback-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: var(--transition-smooth);
    }

    .feedback-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-medium);
    }

    .star-rating {
        display: inline-flex;
        gap: 2px;
    }

    .star-rating input {
        display: none;
    }

    .star-rating label {
        cursor: pointer;
        font-size: 1.5rem;
        color: #e0e0e0;
        transition: var(--transition-smooth);
    }

    .star-rating label:hover,
    .star-rating input:checked ~ label {
        color: #ffc107;
    }

    .similar-event-card {
        transition: var(--transition-smooth);
        border-radius: 12px;
        overflow: hidden;
    }

    .similar-event-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .share-btn {
        transition: var(--transition-bounce);
        border-radius: 10px;
    }

    .share-btn:hover {
        transform: translateY(-3px) scale(1.05);
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .floating-alert {
        animation: slideInDown 0.5s ease-out, shake 0.5s ease-in-out 0.5s;
    }

    .event-description {
        line-height: 1.8;
        font-size: 1.1rem;
    }

    .meta-icon {
        width: 20px;
        text-align: center;
        margin-right: 10px;
        color: var(--primary-color);
    }

    .category-tag {
        display: inline-block;
        background: var(--gradient-primary);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        margin: 0.2rem;
        transition: var(--transition-smooth);
    }

    .category-tag:hover {
        transform: scale(1.1) rotate(3deg);
    }

    .back-btn {
        transition: var(--transition-smooth);
        border-radius: 10px;
        padding: 0.5rem 1rem;
    }

    .back-btn:hover {
        transform: translateX(-5px);
        background: var(--primary-color);
        color: white;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-card {
        animation: fadeInUp 0.6s ease-out forwards;
    }

    .stagger-animate > * {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
</style>

<div class="container mt-4">
    <?php echo $FlashMessageObject->getMsg('msg'); ?>
    
    <!-- Enhanced Back Button -->
    <div class="mb-4 animate__animated animate__fadeIn">
        <a href="dashboard.php" class="btn btn-outline-secondary back-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Events
        </a>
    </div>
    
    <div class="row stagger-animate">
        <div class="col-lg-8">
            <!-- Event Hero Section -->
            <div class="event-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($event['title']); ?></h1>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="status-badge badge bg-<?php 
                                switch($event['status']) {
                                    case 'upcoming': echo 'success'; break;
                                    case 'ongoing': echo 'primary'; break;
                                    case 'completed': echo 'secondary'; break;
                                    case 'cancelled': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <i class="fas fa-<?php 
                                    switch($event['status']) {
                                        case 'upcoming': echo 'clock'; break;
                                        case 'ongoing': echo 'play-circle'; break;
                                        case 'completed': echo 'check-circle'; break;
                                        case 'cancelled': echo 'times-circle'; break;
                                        default: echo 'calendar';
                                    }
                                ?> me-1"></i>
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                            <span class="text-muted">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $event['attendee_count']; ?> attending
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="organizer-info">
                            <div class="d-flex align-items-center justify-content-md-end gap-2">
                                <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <strong class="d-block"><?php echo htmlspecialchars($event['organizer_name']); ?></strong>
                                    <small class="text-muted">Organizer</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Image -->
            <?php if ($event['image']): ?>
                <div class="card border-0 shadow-lg mb-4 animate-card" style="animation-delay: 0.1s;">
                    <img src="<?php echo htmlspecialchars($event['image']); ?>" 
                         class="event-hero-image" 
                         alt="<?php echo htmlspecialchars($event['title']); ?>">
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-lg mb-4 animate-card bg-gradient-primary text-white" style="animation-delay: 0.1s; height: 300px;">
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <i class="fas fa-calendar-star fa-5x mb-3 opacity-75"></i>
                            <h3 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Event Details Card -->
            <div class="card border-0 shadow-sm mb-4 animate-card" style="animation-delay: 0.2s;">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-info-circle text-primary me-2"></i>Event Details
                    </h4>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="event-meta-card">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-map-marker-alt meta-icon"></i>
                                    <div>
                                        <strong class="d-block">Location</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="event-meta-card">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-calendar-alt meta-icon"></i>
                                    <div>
                                        <strong class="d-block">Date & Time</strong>
                                        <span class="text-muted"><?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="event-meta-card">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-ticket-alt meta-icon"></i>
                                    <div>
                                        <strong class="d-block">Tickets</strong>
                                        <span class="text-muted">
                                            <?php echo $event['available_tickets']; ?> available / 
                                            <?php echo $event['total_tickets']; ?> total
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="event-meta-card">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-clock meta-icon"></i>
                                    <div>
                                        <strong class="d-block">Created</strong>
                                        <span class="text-muted"><?php echo date('F j, Y', strtotime($event['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($event['category_names']): ?>
                        <div class="mb-4">
                            <strong class="d-block mb-2">
                                <i class="fas fa-tags text-primary me-2"></i>Categories
                            </strong>
                            <?php
                            $categories = explode(', ', $event['category_names']);
                            foreach ($categories as $category): ?>
                                <span class="category-tag"><?php echo htmlspecialchars(trim($category)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="event-description">
                        <strong class="d-block mb-3">
                            <i class="fas fa-align-left text-primary me-2"></i>Description
                        </strong>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Enhanced Comments/Feedback Section -->
            <div class="card border-0 shadow-sm feedback-card animate-card" style="animation-delay: 0.3s;">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-comments text-primary me-2"></i>Feedback & Reviews
                        <span class="badge bg-primary ms-2">
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE event_id = ?");
                                $stmt->execute([$event_id]);
                                $feedback_count = $stmt->fetchColumn();
                                echo $feedback_count;
                            } catch (PDOException $e) {
                                echo '0';
                            }
                            ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch feedback for this event
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
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No reviews yet. Be the first to leave feedback!</p>
                            </div>
                        <?php else: 
                            foreach ($feedbacks as $index => $feedback): ?>
                                <div class="border-bottom pb-3 mb-3 animate__animated animate__fadeIn" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-placeholder bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 35px; height: 35px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <strong><?php echo htmlspecialchars($feedback['fullname']); ?></strong>
                                        </div>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
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
                            <?php endforeach;
                        endif;
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-warning">Error loading feedback</div>';
                    }
                    ?>

                    <!-- Enhanced Add/Edit Feedback Form -->
                    <?php if ($is_attending && $attendee_status == 'going' && in_array($event['status'], ['ongoing', 'completed'])): ?>
                        <div class="mt-4 p-4 bg-light rounded-3">
                            <h6 class="mb-3">
                                <i class="fas fa-edit text-primary me-2"></i>
                                <?php echo $user_feedback ? 'Update Your Feedback' : 'Leave Your Feedback'; ?>
                            </h6>
                            
                            <?php if ($event['status'] == 'ongoing'): ?>
                                <div class="alert alert-info alert-dismissible fade show floating-alert" role="alert">
                                    <i class="fas fa-broadcast-tower me-2"></i> 
                                    <strong>Live Feedback!</strong> Share your experience while the event is happening.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="event_actions.php" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="submit_feedback">
                                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Rating</label>
                                    <div class="star-rating" id="starRating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                                   <?php echo ($user_feedback && $user_feedback['rating'] == $i) ? 'checked' : ''; ?>
                                                   required>
                                            <label for="star<?php echo $i; ?>">
                                                <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="invalid-feedback">Please select a rating</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comment" class="form-label fw-bold">Comment (Optional)</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="4" 
                                              placeholder="Share your experience with this event..."
                                              style="border-radius: 12px;"><?php echo $user_feedback ? htmlspecialchars($user_feedback['comment']) : ''; ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        <?php echo $user_feedback ? 'Update Feedback' : 'Submit Feedback'; ?>
                                    </button>
                                    <?php if ($user_feedback): ?>
                                        <a href="event_actions.php?action=delete_feedback&id=<?php echo $event_id; ?>" 
                                           class="btn btn-outline-danger px-4"
                                           onclick="return confirm('Are you sure you want to delete your feedback?')">
                                            <i class="fas fa-trash me-2"></i>Delete Feedback
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($is_attending && $attendee_status == 'going' && $event['status'] == 'upcoming'): ?>
                        <div class="alert alert-info animate__animated animate__pulse">
                            <i class="fas fa-clock me-2"></i> 
                            You can leave feedback once the event starts or after it's completed.
                        </div>
                    <?php elseif ($is_attending && $attendee_status != 'going'): ?>
                        <div class="alert alert-warning animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-circle me-2"></i> 
                            Only attendees who are "going" can leave feedback.
                        </div>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info text-center py-4">
                            <i class="fas fa-sign-in-alt fa-2x mb-3 text-primary"></i>
                            <h6>Sign in to leave feedback</h6>
                            <a href="signin.php" class="btn btn-primary mt-2">Sign In</a>
                        </div>
                    <?php elseif (!$is_attending): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> 
                            You must RSVP as "going" to leave feedback.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Event Actions Card -->
            <div class="action-card animate-card" style="animation-delay: 0.4s;">
                <h5 class="card-title mb-4">
                    <i class="fas fa-bolt text-primary me-2"></i>Event Actions
                </h5>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($is_owner): ?>
                        <!-- Owner Actions -->
                        <div class="d-grid gap-3">
                            <a href="edit_event.php?id=<?php echo $event['id']; ?>" 
                               class="btn btn-primary btn-lg">
                                <i class="fas fa-edit me-2"></i>Edit Event
                            </a>
                            <a href="my_events.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>My Events
                            </a>
                            
                            <?php if ($event['status'] == 'upcoming'): ?>
                                <a href="event_actions.php?action=mark_ongoing&id=<?php echo $event['id']; ?>" 
                                   class="btn btn-success"
                                   onclick="return confirm('Mark this event as ongoing/started?')">
                                    <i class="fas fa-play-circle me-2"></i>Start Event
                                </a>
                                <a href="event_actions.php?action=cancel&id=<?php echo $event['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   onclick="return confirm('Are you sure you want to cancel this event?')">
                                    <i class="fas fa-times-circle me-2"></i>Cancel Event
                                </a>
                            <?php elseif ($event['status'] == 'ongoing'): ?>
                                <a href="event_actions.php?action=mark_completed&id=<?php echo $event['id']; ?>" 
                                   class="btn btn-success"
                                   onclick="return confirm('Mark this event as completed?')">
                                    <i class="fas fa-check-circle me-2"></i>Complete Event
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Event Statistics for Owner -->
                        <div class="mt-4 p-3 bg-light rounded-3">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-bar text-primary me-2"></i>Event Statistics
                            </h6>
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="stat-card">
                                        <div class="stat-number"><?php echo $event['attendee_count']; ?></div>
                                        <div class="stat-label">Attendees</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-card">
                                        <div class="stat-number"><?php echo $event['total_tickets'] - $event['available_tickets']; ?></div>
                                        <div class="stat-label">Tickets Sold</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-card">
                                        <div class="stat-number"><?php echo $event['available_tickets']; ?></div>
                                        <div class="stat-label">Available</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Non-Owner Actions -->
                        <div class="d-grid gap-3">
                            <?php if ($is_attending): ?>
                                <button class="btn btn-success btn-lg" disabled>
                                    <i class="fas fa-check-circle me-2"></i> 
                                    You're <?php echo ucfirst($attendee_status); ?>
                                </button>
                                
                                <?php if ($event['status'] == 'upcoming' || $event['status'] == 'ongoing'): ?>
                                    <div class="btn-group-vertical" role="group">
                                        <a href="event_actions.php?action=unattend&id=<?php echo $event['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to cancel your attendance?')">
                                            <i class="fas fa-times me-2"></i>Cancel Attendance
                                        </a>
                                        <?php if ($event['status'] == 'upcoming'): ?>
                                            <a href="event_actions.php?action=change_status&id=<?php echo $event['id']; ?>&status=interested" 
                                               class="btn btn-outline-warning">
                                                <i class="fas fa-star me-2"></i>Mark as Interested
                                            </a>
                                            <a href="event_actions.php?action=change_status&id=<?php echo $event['id']; ?>&status=not going" 
                                               class="btn btn-outline-secondary">
                                                <i class="fas fa-ban me-2"></i>Not Going
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($event['status'] == 'upcoming' || $event['status'] == 'ongoing'): ?>
                                    <?php if ($event['available_tickets'] > 0 || $event['total_tickets'] == 0): ?>
                                        <a href="event_actions.php?action=attend&id=<?php echo $event['id']; ?>" 
                                           class="btn btn-primary btn-lg">
                                            <i class="fas fa-calendar-check me-2"></i>Attend Event
                                        </a>
                                        <a href="event_actions.php?action=interested&id=<?php echo $event['id']; ?>" 
                                           class="btn btn-outline-warning">
                                            <i class="fas fa-star me-2"></i>Interested
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-lg" disabled>
                                            <i class="fas fa-exclamation-triangle me-2"></i>Event Full
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg" disabled>
                                        <i class="fas fa-clock me-2"></i>Event Ended
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- RSVP Status Info -->
                        <?php if ($is_attending): ?>
                            <div class="mt-4 p-3 bg-light rounded-3">
                                <h6 class="mb-2">
                                    <i class="fas fa-user-check text-primary me-2"></i>Your RSVP Status
                                </h6>
                                <p class="mb-1 fw-bold text-<?php 
                                    switch($attendee_status) {
                                        case 'going': echo 'success'; break;
                                        case 'interested': echo 'warning'; break;
                                        case 'not going': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($attendee_status); ?>
                                </p>
                                <small class="text-muted">
                                    <?php if ($attendee_status == 'going'): ?>
                                        <i class="fas fa-check-circle text-success me-1"></i>
                                        You're confirmed to attend this event.
                                        <?php if (in_array($event['status'], ['ongoing', 'completed'])): ?>
                                            <br><strong class="text-success">You can now leave feedback!</strong>
                                        <?php endif; ?>
                                    <?php elseif ($attendee_status == 'interested'): ?>
                                        <i class="fas fa-star text-warning me-1"></i>
                                        You're interested in this event.
                                    <?php elseif ($attendee_status == 'not going'): ?>
                                        <i class="fas fa-ban text-danger me-1"></i>
                                        You're not planning to attend.
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center py-4">
                        <i class="fas fa-sign-in-alt fa-2x mb-3 text-primary"></i>
                        <h6>Sign in to RSVP</h6>
                        <a href="signin.php" class="btn btn-primary mt-2">Sign In</a>
                    </div>
                <?php endif; ?>
                
                <!-- Ticket Information -->
                <?php if ($event['status'] == 'upcoming' || $event['status'] == 'ongoing'): ?>
                    <?php if ($event['available_tickets'] > 0): ?>
                        <div class="mt-3 alert alert-success">
                            <i class="fas fa-ticket-alt me-2"></i>
                            <strong><?php echo $event['available_tickets']; ?> tickets available</strong>
                        </div>
                    <?php else: ?>
                        <div class="mt-3 alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Event is fully booked</strong>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Share Event Card -->
            <div class="card border-0 shadow-sm mt-4 animate-card" style="animation-delay: 0.5s;">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-share-alt text-primary me-2"></i>Share this event
                    </h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary share-btn" onclick="shareEvent('facebook')">
                            <i class="fab fa-facebook me-2"></i> Facebook
                        </button>
                        <button class="btn btn-outline-info share-btn" onclick="shareEvent('twitter')">
                            <i class="fab fa-twitter me-2"></i> Twitter
                        </button>
                        <button class="btn btn-outline-success share-btn" onclick="shareEvent('whatsapp')">
                            <i class="fab fa-whatsapp me-2"></i> WhatsApp
                        </button>
                        <button class="btn btn-outline-primary share-btn" onclick="shareEvent('whatsapp')">
                            <i class="fab fa-instagram me-2"></i> Instagram
                        </button>
                        <button class="btn btn-outline-info share-btn" onclick="shareEvent('whatsapp')">
                            <i class="fab fa-linkedin me-2"></i> LinkedIn
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Similar Events Card -->
            <div class="card border-0 shadow-sm mt-4 animate-card" style="animation-delay: 0.6s;">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-calendar-plus text-primary me-2"></i>Similar Events
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
                            <div class="text-center py-3">
                                <i class="fas fa-calendar-times text-muted fa-2x mb-2"></i>
                                <p class="text-muted small mb-0">No similar events found.</p>
                            </div>
                        <?php else: 
                            foreach ($similar_events as $index => $similar_event): ?>
                                <a href="event_details.php?id=<?php echo $similar_event['id']; ?>" 
                                   class="text-decoration-none similar-event-card d-block mb-3 p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center">
                                        <?php if ($similar_event['image']): ?>
                                            <img src="<?php echo htmlspecialchars($similar_event['image']); ?>" 
                                                 class="rounded me-3" 
                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded me-3 d-flex align-items-center justify-content-center"
                                                 style="width: 60px; height: 60px;">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <strong class="small d-block text-dark"><?php echo htmlspecialchars($similar_event['title']); ?></strong>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($similar_event['location']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M j, Y', strtotime($similar_event['event_date'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $similar_event['status'] == 'ongoing' ? 'warning' : 'success';
                                        ?> ms-2">
                                            <?php echo ucfirst($similar_event['status']); ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach;
                        endif;
                    } catch (PDOException $e) {
                        echo '<div class="text-center py-3">';
                        echo '<i class="fas fa-exclamation-triangle text-muted fa-2x mb-2"></i>';
                        echo '<p class="text-muted small mb-0">Error loading similar events</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced share function with better UX
function shareEvent(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent("<?php echo addslashes($event['title']); ?>");
    const text = encodeURIComponent("Check out this amazing event: <?php echo addslashes($event['title']); ?>");
    
    let shareUrl = '';
    let windowFeatures = 'width=600,height=400,menubar=no,toolbar=no,location=no,status=no';
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${text}%20${url}`;
            break;
    }
    
    // Add animation to the clicked button
    const button = event.target.closest('button');
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
        button.style.transform = '';
    }, 150);
    
    window.open(shareUrl, '_blank', windowFeatures);
}

// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Star rating interaction
document.addEventListener('DOMContentLoaded', function() {
    const starInputs = document.querySelectorAll('#starRating input');
    starInputs.forEach(input => {
        input.addEventListener('change', function() {
            const labels = document.querySelectorAll('#starRating label');
            labels.forEach(label => label.style.color = '#e0e0e0');
            
            for (let i = 1; i <= this.value; i++) {
                const label = document.querySelector(`#starRating label[for="star${i}"]`);
                if (label) {
                    label.style.color = '#ffc107';
                }
            }
        });
    });
});

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe all animated elements
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.animate-card');
    animatedElements.forEach(element => {
        element.style.animationPlayState = 'paused';
        observer.observe(element);
    });
});
</script>

<?php
$LayoutObject->footer($conf);
?>