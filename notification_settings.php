<?php
require 'ClassAutoLoad.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Get user preferences
$preferences = [
    'email_new_events' => 1,
    'email_event_reminders' => 1,
    'email_rsvp_confirmation' => 1,
    'email_event_updates' => 1
];

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC) ?: $preferences;
    
} catch (PDOException $e) {
    error_log("Error loading preferences: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_preferences = [
        'email_new_events' => isset($_POST['email_new_events']) ? 1 : 0,
        'email_event_reminders' => isset($_POST['email_event_reminders']) ? 1 : 0,
        'email_rsvp_confirmation' => isset($_POST['email_rsvp_confirmation']) ? 1 : 0,
        'email_event_updates' => isset($_POST['email_event_updates']) ? 1 : 0
    ];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_preferences 
            (user_id, email_new_events, email_event_reminders, email_rsvp_confirmation, email_event_updates) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            email_new_events = VALUES(email_new_events),
            email_event_reminders = VALUES(email_event_reminders),
            email_rsvp_confirmation = VALUES(email_rsvp_confirmation),
            email_event_updates = VALUES(email_event_updates),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $new_preferences['email_new_events'],
            $new_preferences['email_event_reminders'],
            $new_preferences['email_rsvp_confirmation'],
            $new_preferences['email_event_updates']
        ]);
        
        $preferences = $new_preferences;
        $FlashMessageObject->setMsg('msg', 'Notification preferences updated successfully!', 'success');
        
    } catch (PDOException $e) {
        $FlashMessageObject->setMsg('msg', 'Error updating preferences: ' . $e->getMessage(), 'danger');
    }
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
/* Custom CSS for enhanced notification settings */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    --transition-smooth: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

.notification-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    transition: var(--transition-smooth);
    animation: cardSlideUp 0.6s ease-out forwards;
    opacity: 0;
    transform: translateY(20px);
}

.notification-card:nth-child(1) { animation-delay: 0.1s; }
.notification-card:nth-child(2) { animation-delay: 0.3s; }

.notification-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: var(--primary-gradient);
    border: none;
    padding: 1.5rem;
}

.card-header h4 {
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Custom toggle switches */
.form-check-input {
    width: 3rem;
    height: 1.5rem;
    margin-right: 10px;
    background-color: #e9ecef;
    border: none;
    position: relative;
    transition: var(--transition-smooth);
}

.form-check-input:checked {
    background: var(--success-gradient);
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-check-input::before {
    content: '';
    position: absolute;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 50%;
    background: white;
    top: 0.15rem;
    left: 0.15rem;
    transition: var(--transition-smooth);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.form-check-input:checked::before {
    transform: translateX(1.5rem);
}

.form-check-label {
    display: flex;
    flex-direction: column;
    cursor: pointer;
    padding: 0.75rem 0;
    transition: var(--transition-smooth);
    border-radius: 8px;
    padding-left: 10px;
}

.form-check-label:hover {
    background-color: rgba(102, 126, 234, 0.05);
    transform: translateX(5px);
}

/* Enhanced buttons */
.btn {
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition-smooth);
    position: relative;
    overflow: hidden;
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: #667eea;
    border: 1px solid #667eea;
}

.btn-secondary:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* Success message animation */
.alert {
    border: none;
    border-radius: 10px;
    animation: fadeInDown 0.5s ease-out;
}

.alert-success {
    background: var(--success-gradient);
    color: white;
}

/* Keyframe animations */
@keyframes cardSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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

/* Section styling */
.section-title {
    position: relative;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: var(--primary-gradient);
    border-radius: 3px;
}

/* Notification history card */
.history-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: var(--transition-smooth);
}

.history-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
}

/* Ripple effect for buttons */
.btn-ripple {
    position: relative;
    overflow: hidden;
}

.btn-ripple:after {
    content: "";
    display: block;
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    pointer-events: none;
    background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
    background-repeat: no-repeat;
    background-position: 50%;
    transform: scale(10, 10);
    opacity: 0;
    transition: transform .5s, opacity 1s;
}

.btn-ripple:active:after {
    transform: scale(0, 0);
    opacity: .2;
    transition: 0s;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .d-md-flex {
        flex-direction: column;
    }
}
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <!-- Main Notification Settings Card -->
            <div class="card notification-card shadow-lg">
                <div class="card-header text-white">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-bell me-3 fa-2x"></i>
                        <div>
                            <h4 class="mb-0">Notification Settings</h4>
                            <p class="mb-0 mt-1 opacity-75">Customize how you receive notifications</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php echo $FlashMessageObject->getMsg('msg'); ?>
                    
                    <p class="text-muted mb-4">
                        Manage how and when you receive notifications from EventHub. Stay informed without being overwhelmed.
                    </p>
                    
                    <form method="POST" id="notificationForm">
                        <div class="mb-4">
                            <h5 class="section-title">Email Notifications</h5>
                            
                            <div class="form-check form-switch mb-3 notification-item">
                                <input class="form-check-input" type="checkbox" id="email_new_events" 
                                       name="email_new_events" <?php echo $preferences['email_new_events'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_new_events">
                                    <strong>New Events</strong>
                                    <p class="text-muted small mb-0">Get notified when new events are posted in categories you're interested in</p>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3 notification-item">
                                <input class="form-check-input" type="checkbox" id="email_event_reminders" 
                                       name="email_event_reminders" <?php echo $preferences['email_event_reminders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_event_reminders">
                                    <strong>Event Reminders</strong>
                                    <p class="text-muted small mb-0">Receive reminders for events you're attending (24 hours and 1 hour before)</p>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3 notification-item">
                                <input class="form-check-input" type="checkbox" id="email_rsvp_confirmation" 
                                       name="email_rsvp_confirmation" <?php echo $preferences['email_rsvp_confirmation'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_rsvp_confirmation">
                                    <strong>RSVP Confirmations</strong>
                                    <p class="text-muted small mb-0">Get confirmation emails when you RSVP to events</p>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3 notification-item">
                                <input class="form-check-input" type="checkbox" id="email_event_updates" 
                                       name="email_event_updates" <?php echo $preferences['email_event_updates'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_event_updates">
                                    <strong>Event Updates</strong>
                                    <p class="text-muted small mb-0">Receive updates when events you're attending change</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="notifications.php" class="btn btn-secondary me-md-2 btn-ripple">Back to Notifications</a>
                            <button type="submit" class="btn btn-primary btn-ripple" id="saveBtn">
                                <span class="save-text">Save Preferences</span>
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Notification History Card -->
            <div class="history-card mt-4">
                <h5 class="section-title">Notification History</h5>
                <p class="text-muted">
                    You can view all your notifications on the 
                    <a href="notifications.php" class="text-decoration-none fw-bold">notifications page</a>.
                    Notifications are kept for 90 days before being automatically cleared.
                </p>
                <div class="d-flex align-items-center mt-3">
                    <i class="fas fa-history text-primary me-2"></i>
                    <span class="small text-muted">Last updated: <?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to notification items
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach((item, index) => {
        item.style.animationDelay = `${0.1 + index * 0.1}s`;
        item.classList.add('animate__animated', 'animate__fadeInRight');
    });
    
    // Save button animation
    const saveBtn = document.getElementById('saveBtn');
    const saveText = saveBtn.querySelector('.save-text');
    const spinner = saveBtn.querySelector('.spinner-border');
    
    document.getElementById('notificationForm').addEventListener('submit', function(e) {
        // Show loading state
        saveText.textContent = 'Saving...';
        spinner.classList.remove('d-none');
        saveBtn.disabled = true;
        
        // Add pulse animation
        saveBtn.style.animation = 'pulse 0.5s ease-in-out';
        
        // Remove animation after it completes
        setTimeout(() => {
            saveBtn.style.animation = '';
        }, 500);
    });
    
    // Toggle switch animation
    const toggleSwitches = document.querySelectorAll('.form-check-input');
    toggleSwitches.forEach(switchEl => {
        switchEl.addEventListener('change', function() {
            // Add a subtle animation when toggling
            const label = this.closest('.form-check-label');
            label.style.transform = 'scale(1.02)';
            setTimeout(() => {
                label.style.transform = '';
            }, 150);
        });
    });
    
    // Add hover effect to cards
    const cards = document.querySelectorAll('.card, .history-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<?php
$LayoutObject->footer($conf);
?>


