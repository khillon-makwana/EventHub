<?php
require 'ClassAutoLoad.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Get user's events
$events = [];
$event_stats = [
    'total' => 0,
    'upcoming' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'draft' => 0,
    'cancelled' => 0
];

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COUNT(DISTINCT ea.id) as attendee_count,
               COUNT(DISTINCT ec.category_id) as category_count,
               GROUP_CONCAT(ac.name SEPARATOR ', ') as category_names
        FROM events e 
        LEFT JOIN event_attendees ea ON e.id = ea.event_id 
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN attendee_categories ac ON ec.category_id = ac.id
        WHERE e.user_id = ? 
        GROUP BY e.id 
        ORDER BY 
            CASE 
                WHEN e.status = 'ongoing' THEN 1
                WHEN e.status = 'upcoming' THEN 2
                WHEN e.status = 'draft' THEN 3
                WHEN e.status = 'completed' THEN 4
                WHEN e.status = 'cancelled' THEN 5
                ELSE 6
            END,
            e.event_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $event_stats['total'] = count($events);
    foreach ($events as $event) {
        if (isset($event_stats[$event['status']])) {
            $event_stats[$event['status']]++;
        }
    }
    
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error loading events: ' . $e->getMessage(), 'danger');
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    /* My Events Specific Styles */
    .my-events-hero {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%);
        border-radius: 20px;
        padding: 3rem 2rem;
        margin-bottom: 2rem;
        text-align: center;
        animation: fadeInUp 0.8s ease-out;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: var(--shadow-soft);
        transition: var(--transition-smooth);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 500;
    }

    .status-upcoming { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
    .status-ongoing { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; }
    .status-draft { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; }
    .status-completed { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
    .status-cancelled { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }

    .event-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: var(--transition-smooth);
        border: none;
        height: 100%;
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
        z-index: 2;
    }

    .event-card:hover::before {
        transform: scaleX(1);
    }

    .event-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-large);
    }

    .event-image {
        height: 200px;
        object-fit: cover;
        transition: var(--transition-smooth);
    }

    .event-card:hover .event-image {
        transform: scale(1.1);
    }

    .event-status-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 3;
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 50px;
        box-shadow: var(--shadow-soft);
        animation: pulse 2s infinite;
    }

    .event-actions {
        opacity: 0;
        transform: translateY(10px);
        transition: var(--transition-smooth);
    }

    .event-card:hover .event-actions {
        opacity: 1;
        transform: translateY(0);
    }

    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
        animation: fadeInUp 0.8s ease-out;
    }

    .empty-state-icon {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 1.5rem;
        animation: float 3s ease-in-out infinite;
    }

    .filter-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: white;
        border-radius: 16px;
        box-shadow: var(--shadow-soft);
    }

    .filter-tab {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        background: #f8f9fa;
        color: #6b7280;
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition-smooth);
        border: 2px solid transparent;
    }

    .filter-tab:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .filter-tab.active {
        background: var(--primary-color);
        color: white;
        transform: scale(1.05);
    }

    .quick-action-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        margin-bottom: 2rem;
        animation: fadeInUp 0.8s ease-out 0.2s both;
    }

    .action-btn {
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.3);
        color: white;
        border-radius: 12px;
        padding: 1rem 2rem;
        font-weight: 600;
        transition: var(--transition-bounce);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .action-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-3px) scale(1.05);
        color: white;
    }

    .event-meta {
        font-size: 0.875rem;
    }

    .event-meta i {
        width: 16px;
        text-align: center;
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .category-tag {
        display: inline-block;
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary-color);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        margin: 0.1rem;
        transition: var(--transition-smooth);
    }

    .category-tag:hover {
        background: var(--primary-color);
        color: white;
        transform: scale(1.05);
    }

    .attendee-count {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .attendee-count i {
        color: var(--primary-color);
    }

    .event-grid {
        opacity: 0;
        animation: fadeIn 0.8s ease-out 0.4s forwards;
    }

    .event-item {
        animation: staggerFadeIn 0.6s ease-out forwards;
        opacity: 0;
    }

    @keyframes staggerFadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .my-events-hero {
            padding: 2rem 1rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-tabs {
            overflow-x: auto;
            flex-wrap: nowrap;
            justify-content: flex-start;
        }
        
        .event-grid .col {
            animation-delay: calc(var(--index) * 0.1s) !important;
        }
    }
</style>

<div class="container mt-4">
    <!-- Hero Section -->
    <div class="my-events-hero">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h1 class="display-5 fw-bold mb-3">My Events</h1>
                <p class="lead text-muted mb-4">
                    Manage and track all your events in one place. Create, edit, and monitor your event's performance.
                </p>
                
                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event_stats['total']; ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event_stats['upcoming']; ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event_stats['ongoing']; ?></div>
                        <div class="stat-label">Ongoing</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event_stats['draft']; ?></div>
                        <div class="stat-label">Drafts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
        <div>
            <h2 class="h3 mb-1">Manage Your Events</h2>
            <p class="text-muted mb-0">
                <?php if ($event_stats['total'] > 0): ?>
                    You have <?php echo $event_stats['total']; ?> events across <?php echo array_sum(array_slice($event_stats, 1)); ?> different statuses
                <?php else: ?>
                    Ready to create your first amazing event?
                <?php endif; ?>
            </p>
        </div>
        <a href="add_event.php" class="btn btn-primary btn-create">
            <i class="fas fa-plus-circle me-2"></i>Create New Event
        </a>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <!-- Quick Action Card -->
    <?php if ($event_stats['total'] === 0): ?>
        <div class="quick-action-card">
            <h3 class="mb-3">Ready to Create Your First Event?</h3>
            <p class="mb-4 opacity-90">Start your event creation journey and share amazing experiences with your audience.</p>
            <a href="add_event.php" class="action-btn">
                <i class="fas fa-rocket me-2"></i>Launch Your First Event
            </a>
        </div>
    <?php else: ?>
        <div class="quick-action-card">
            <h3 class="mb-3">Event Management Hub</h3>
            <p class="mb-4 opacity-90">Quickly access your events or create new ones to keep the momentum going.</p>
            <div class="d-flex gap-3 flex-wrap justify-content-center">
                <a href="add_event.php" class="action-btn">
                    <i class="fas fa-plus-circle me-2"></i>Create New Event
                </a>
                <a href="dashboard.php" class="action-btn">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <?php if ($event_stats['total'] > 0): ?>
        <div class="filter-tabs animate__animated animate__fadeIn">
            <a href="?filter=all" class="filter-tab <?php echo empty($_GET['filter']) || $_GET['filter'] === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group me-2"></i>All Events (<?php echo $event_stats['total']; ?>)
            </a>
            <a href="?filter=upcoming" class="filter-tab <?php echo ($_GET['filter'] ?? '') === 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-clock me-2"></i>Upcoming (<?php echo $event_stats['upcoming']; ?>)
            </a>
            <a href="?filter=ongoing" class="filter-tab <?php echo ($_GET['filter'] ?? '') === 'ongoing' ? 'active' : ''; ?>">
                <i class="fas fa-play-circle me-2"></i>Ongoing (<?php echo $event_stats['ongoing']; ?>)
            </a>
            <a href="?filter=draft" class="filter-tab <?php echo ($_GET['filter'] ?? '') === 'draft' ? 'active' : ''; ?>">
                <i class="fas fa-edit me-2"></i>Drafts (<?php echo $event_stats['draft']; ?>)
            </a>
            <a href="?filter=completed" class="filter-tab <?php echo ($_GET['filter'] ?? '') === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle me-2"></i>Completed (<?php echo $event_stats['completed']; ?>)
            </a>
            <a href="?filter=cancelled" class="filter-tab <?php echo ($_GET['filter'] ?? '') === 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle me-2"></i>Cancelled (<?php echo $event_stats['cancelled']; ?>)
            </a>
        </div>
    <?php endif; ?>

    <!-- Events Grid -->
    <?php if (empty($events)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <h3 class="mb-3">No Events Yet</h3>
            <p class="text-muted mb-4">
                You haven't created any events yet. Start by creating your first event and share amazing experiences with your audience.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="add_event.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle me-2"></i>Create Your First Event
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 event-grid">
            <?php 
            $filter = $_GET['filter'] ?? 'all';
            $filtered_events = $events;
            
            if ($filter !== 'all') {
                $filtered_events = array_filter($events, function($event) use ($filter) {
                    return $event['status'] === $filter;
                });
            }
            
            if (empty($filtered_events)): 
            ?>
                <div class="col-12">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-filter"></i>
                        </div>
                        <h3 class="mb-3">No Events Found</h3>
                        <p class="text-muted mb-4">
                            No events match the selected filter. Try selecting a different filter or 
                            <a href="?filter=all" class="text-decoration-none">view all events</a>.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_events as $index => $event): ?>
                    <div class="col event-item" style="--index: <?php echo $index; ?>; animation-delay: <?php echo $index * 0.1; ?>s">
                        <div class="card h-100 event-card">
                            <!-- Event Image -->
                            <?php if ($event['image']): ?>
                                <div class="position-relative overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($event['image']); ?>" 
                                         class="card-img-top event-image" 
                                         alt="<?php echo htmlspecialchars($event['title']); ?>">
                                    <!-- Status Badge -->
                                    <span class="event-status-badge status-<?php echo $event['status']; ?>">
                                        <i class="fas fa-<?php 
                                            switch($event['status']) {
                                                case 'upcoming': echo 'clock'; break;
                                                case 'ongoing': echo 'play-circle'; break;
                                                case 'draft': echo 'edit'; break;
                                                case 'completed': echo 'check-circle'; break;
                                                case 'cancelled': echo 'times-circle'; break;
                                                default: echo 'calendar';
                                            }
                                        ?> me-1"></i>
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center text-white position-relative"
                                     style="height: 200px;">
                                    <div class="text-center">
                                        <i class="fas fa-calendar-alt fa-3x mb-2 opacity-75"></i>
                                        <p class="mb-0 small fw-bold"><?php echo htmlspecialchars($event['title']); ?></p>
                                    </div>
                                    <!-- Status Badge -->
                                    <span class="event-status-badge status-<?php echo $event['status']; ?>">
                                        <i class="fas fa-<?php 
                                            switch($event['status']) {
                                                case 'upcoming': echo 'clock'; break;
                                                case 'ongoing': echo 'play-circle'; break;
                                                case 'draft': echo 'edit'; break;
                                                case 'completed': echo 'check-circle'; break;
                                                case 'cancelled': echo 'times-circle'; break;
                                                default: echo 'calendar';
                                            }
                                        ?> me-1"></i>
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <!-- Event Title -->
                                <h5 class="card-title fw-bold text-dark mb-2 line-clamp-2">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </h5>
                                
                                <!-- Event Description -->
                                <p class="card-text text-muted flex-grow-1 mb-3 line-clamp-2">
                                    <?php 
                                    $description = strip_tags($event['description']);
                                    if (strlen($description) > 100) {
                                        $description = substr($description, 0, 100) . '...';
                                    }
                                    echo htmlspecialchars($description);
                                    ?>
                                </p>
                                
                                <!-- Event Metadata -->
                                <div class="event-meta small text-muted mb-3">
                                    <!-- Location -->
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="text-truncate"><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    
                                    <!-- Date & Time -->
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    
                                    <!-- Categories -->
                                    <?php if ($event['category_names']): ?>
                                        <div class="d-flex align-items-center mb-2 flex-wrap">
                                            <i class="fas fa-tags"></i>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                $categories = explode(', ', $event['category_names']);
                                                $display_categories = array_slice($categories, 0, 2);
                                                foreach ($display_categories as $cat): ?>
                                                    <span class="category-tag"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($categories) > 2): ?>
                                                    <span class="category-tag">+<?php echo count($categories) - 2; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Attendees -->
                                    <div class="d-flex align-items-center">
                                        <div class="attendee-count">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo $event['attendee_count']; ?> attendees</span>
                                        </div>
                                        <?php if ($event['total_tickets'] > 0): ?>
                                            <div class="attendee-count ms-3">
                                                <i class="fas fa-ticket-alt"></i>
                                                <span><?php echo $event['available_tickets']; ?> left</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="event-actions">
                                    <div class="btn-group w-100">
                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary flex-fill">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="manage_rsvps.php?event_id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-outline-success flex-fill">
                                            <i class="fas fa-users me-1"></i>Manage RSVP
                                        </a>
                                    </div>
                                    
                                    <!-- Quick Status Actions -->
                                    <?php if ($event['status'] == 'upcoming'): ?>
                                        <div class="btn-group w-100 mt-2">
                                            <a href="event_actions.php?action=cancel&id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger flex-fill" 
                                               onclick="return confirm('Are you sure you want to cancel this event?')">
                                                <i class="fas fa-times me-1"></i>Cancel
                                            </a>
                                            <a href="event_actions.php?action=mark_ongoing&id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning flex-fill"
                                               onclick="return confirm('Mark this event as ongoing?')">
                                                <i class="fas fa-play-circle me-1"></i>Start Event
                                            </a>
                                        </div>
                                    <?php elseif ($event['status'] == 'ongoing'): ?>
                                        <div class="d-grid mt-2">
                                            <a href="event_actions.php?action=mark_completed&id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-outline-info"
                                               onclick="return confirm('Mark this event as completed?')">
                                                <i class="fas fa-check-circle me-1"></i>Complete Event
                                            </a>
                                        </div>
                                    <?php elseif ($event['status'] == 'draft'): ?>
                                        <div class="d-grid mt-2">
                                            <a href="event_actions.php?action=publish&id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-outline-success flex-fill" 
                                               onclick="return confirm('Are you ready to publish this event?')">
                                                <i class="fas fa-rocket me-1"></i>Publish
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Enhanced event management interactions
document.addEventListener('DOMContentLoaded', function() {
    console.log('My Events page loaded with <?php echo count($events); ?> events');
    
    // Add click animation to event cards
    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons or links
            if (e.target.tagName === 'A' || e.target.closest('a') || e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                return;
            }
            
            const viewLink = this.querySelector('a[href*="event_details.php"]');
            if (viewLink) {
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    window.location.href = viewLink.href;
                }, 150);
            }
        });
    });

    // Filter tab interactions
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Add loading animation
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 1000);
        });
    });

    // Add hover effects to category tags
    const categoryTags = document.querySelectorAll('.category-tag');
    categoryTags.forEach(tag => {
        tag.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05) translateY(-2px)';
        });
        tag.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) translateY(0)';
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
    const animatedElements = document.querySelectorAll('.event-item');
    animatedElements.forEach(element => {
        element.style.animationPlayState = 'paused';
        observer.observe(element);
    });

    // Quick status update confirmation
    const statusButtons = document.querySelectorAll('a[href*="event_actions.php"]');
    statusButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.textContent.includes('Start Event') || this.textContent.includes('Complete Event') || this.textContent.includes('Cancel')) {
                // Animation for status change
                const card = this.closest('.event-card');
                if (card) {
                    card.style.transform = 'scale(0.98)';
                    card.style.opacity = '0.8';
                }
            }
        });
    });
});

// Quick filter by status
function filterByStatus(status) {
    const url = new URL(window.location);
    url.searchParams.set('filter', status);
    window.location.href = url.toString();
}

// Quick event actions
function quickAction(eventId, action) {
    if (confirm(`Are you sure you want to ${action} this event?`)) {
        window.location.href = `event_actions.php?action=${action}&id=${eventId}`;
    }
}
</script>

<?php
$LayoutObject->footer($conf);
?>