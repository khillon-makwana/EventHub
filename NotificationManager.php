<?php
class NotificationManager {
    private $pdo;
    private $conf;
    private $mailer;
    
    public function __construct($conf = null, $mailer = null) {
        // Use provided conf or fall back to global
        if ($conf === null) {
            global $conf;
            $this->conf = $conf;
        } else {
            $this->conf = $conf;
        }
        
        $this->mailer = $mailer;
        
        try {
            $dsn = "mysql:host={$this->conf['db_host']};port={$this->conf['db_port']};dbname={$this->conf['db_name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->conf['db_user'], $this->conf['db_pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database connection failed in NotificationManager: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a notification for a user
     */
    public function createNotification($user_id, $event_id, $title, $message, $type = 'new_event') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, event_id, title, message, type, notification_type) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $event_id, $title, $message, $type, $type]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify all users about a new event
     */
    public function notifyNewEvent($event_id) {
        try {
            // Get event details
            $stmt = $this->pdo->prepare("
                SELECT e.*, GROUP_CONCAT(ac.name SEPARATOR ', ') as categories 
                FROM events e 
                LEFT JOIN event_categories ec ON e.id = ec.event_id 
                LEFT JOIN attendee_categories ac ON ec.category_id = ac.id 
                WHERE e.id = ? 
                GROUP BY e.id
            ");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) return false;
            
            // Get users who want new event notifications
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.email, u.fullname 
                FROM users u 
                JOIN user_notification_preferences unp ON u.id = unp.user_id 
                WHERE unp.email_new_events = 1 AND u.is_verified = 1
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $notification_count = 0;
            $email_count = 0;
            
            foreach ($users as $user) {
                // Create notification
                $title = "New Event: {$event['title']}";
                $message = "A new event '{$event['title']}' has been posted in categories: {$event['categories']}";
                
                $this->createNotification($user['id'], $event_id, $title, $message, 'new_event');
                $notification_count++;
                
                // Send email if mailer is available
                if ($this->mailer) {
                    $this->sendNewEventEmail($user, $event);
                    $email_count++;
                }
            }
            
            return [
                'notifications' => $notification_count,
                'emails' => $email_count
            ];
            
        } catch (PDOException $e) {
            error_log("Error in notifyNewEvent: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send event reminders (to be called by cron job)
     */
    public function sendEventReminders() {
        try {
            // Find events starting in the next 24 hours and 1 hour
            $stmt = $this->pdo->prepare("
                SELECT e.id, e.title, e.event_date, e.location,
                       TIMESTAMPDIFF(HOUR, NOW(), e.event_date) as hours_until
                FROM events e 
                WHERE e.status = 'upcoming' 
                AND e.event_date BETWEEN (NOW() + INTERVAL 1 HOUR) AND (NOW() + INTERVAL 25 HOUR)
            ");
            $stmt->execute();
            $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reminder_count = 0;
            
            foreach ($upcoming_events as $event) {
                // Determine reminder type based on time until event
                $reminder_type = $event['hours_until'] <= 1 ? 'event_reminder_1h' : 'event_reminder_24h';
                $hours_text = $event['hours_until'] <= 1 ? '1 hour' : '24 hours';
                
                // Get attendees who want reminders
                $stmt = $this->pdo->prepare("
                    SELECT u.id, u.email, u.fullname, unp.email_event_reminders
                    FROM users u 
                    JOIN event_attendees ea ON u.id = ea.user_id 
                    LEFT JOIN user_notification_preferences unp ON u.id = unp.user_id 
                    WHERE ea.event_id = ? AND ea.status = 'going' 
                    AND (unp.email_event_reminders = 1 OR unp.email_event_reminders IS NULL)
                ");
                $stmt->execute([$event['id']]);
                $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($attendees as $attendee) {
                    // Create notification
                    $title = "Event Reminder: {$event['title']}";
                    $message = "The event '{$event['title']}' is starting in {$hours_text}. Don't forget to attend!";
                    
                    $this->createNotification($attendee['id'], $event['id'], $title, $message, 'event_reminder');
                    
                    // Send email if enabled
                    if ($this->mailer && $attendee['email_event_reminders']) {
                        $this->sendEventReminderEmail($attendee, $event, $hours_text);
                    }
                    
                    $reminder_count++;
                }
            }
            
            return $reminder_count;
            
        } catch (PDOException $e) {
            error_log("Error in sendEventReminders: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send RSVP confirmation
     */
    public function sendRSVPConfirmation($user_id, $event_id, $status = 'going') {
        try {
            // Get user and event details
            $stmt = $this->pdo->prepare("
                SELECT u.*, e.title, e.event_date, e.location 
                FROM users u 
                JOIN events e ON e.id = ? 
                WHERE u.id = ?
            ");
            $stmt->execute([$event_id, $user_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) return false;
            
            // Check if user wants RSVP confirmations
            $stmt = $this->pdo->prepare("
                SELECT email_rsvp_confirmation 
                FROM user_notification_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $wants_email = $prefs ? $prefs['email_rsvp_confirmation'] : 1;
            
            // Create notification
            $title = "RSVP Confirmation";
            $message = "You have successfully RSVP'd as '{$status}' for '{$data['title']}'";
            
            $this->createNotification($user_id, $event_id, $title, $message, 'rsvp_confirmation');
            
            // Send email if enabled
            if ($this->mailer && $wants_email) {
                $this->sendRSVPEmail($data, $status);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error in sendRSVPConfirmation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Email sending methods
     */
    private function sendNewEventEmail($user, $event) {
        $subject = "New Event: {$event['title']} - {$this->conf['site_name']}";
        $body = "
            <h2>New Event Alert!</h2>
            <p>Hello {$user['fullname']},</p>
            <p>A new event has been posted that might interest you:</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>{$event['title']}</h3>
                <p><strong>Date & Time:</strong> " . date('F j, Y g:i A', strtotime($event['event_date'])) . "</p>
                <p><strong>Location:</strong> {$event['location']}</p>
                <p><strong>Description:</strong> " . substr(strip_tags($event['description']), 0, 200) . "...</p>
            </div>
            
            <p>
                <a href='{$this->conf['site_url']}/event_details.php?id={$event['id']}' 
                   style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                    View Event Details
                </a>
            </p>
            
            <p>Best regards,<br>{$this->conf['site_name']} Team</p>
        ";
        
        $mailContent = [
            'name_from' => $this->conf['site_name'],
            'email_from' => $this->conf['admin_email'],
            'name_to' => $user['fullname'],
            'email_to' => $user['email'],
            'subject' => $subject,
            'body' => $body
        ];
        
        return $this->mailer->Send_Mail($this->conf, $mailContent);
    }
    
    private function sendEventReminderEmail($user, $event, $time_until) {
        $subject = "Reminder: {$event['title']} starts in {$time_until} - {$this->conf['site_name']}";
        $body = "
            <h2>Event Reminder</h2>
            <p>Hello {$user['fullname']},</p>
            <p>This is a reminder for the event you're attending:</p>
            
            <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>{$event['title']}</h3>
                <p><strong>Starts in:</strong> {$time_until}</p>
                <p><strong>Date & Time:</strong> " . date('F j, Y g:i A', strtotime($event['event_date'])) . "</p>
                <p><strong>Location:</strong> {$event['location']}</p>
            </div>
            
            <p>
                <a href='{$this->conf['site_url']}/event_details.php?id={$event['id']}' 
                   style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                    View Event Details
                </a>
            </p>
            
            <p>Best regards,<br>{$this->conf['site_name']} Team</p>
        ";
        
        $mailContent = [
            'name_from' => $this->conf['site_name'],
            'email_from' => $this->conf['admin_email'],
            'name_to' => $user['fullname'],
            'email_to' => $user['email'],
            'subject' => $subject,
            'body' => $body
        ];
        
        return $this->mailer->Send_Mail($this->conf, $mailContent);
    }
    
    private function sendRSVPEmail($data, $status) {
        $subject = "RSVP Confirmation - {$data['title']}";
        $body = "
            <h2>RSVP Confirmation</h2>
            <p>Hello {$data['fullname']},</p>
            <p>Your RSVP has been confirmed with status: <strong>{$status}</strong></p>
            
            <div style='background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>{$data['title']}</h3>
                <p><strong>Date & Time:</strong> " . date('F j, Y g:i A', strtotime($data['event_date'])) . "</p>
                <p><strong>Location:</strong> {$data['location']}</p>
                <p><strong>Your Status:</strong> {$status}</p>
            </div>
            
            <p>
                <a href='{$this->conf['site_url']}/event_details.php?id={$data['id']}' 
                   style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                    View Event Details
                </a>
            </p>
            
            <p>Best regards,<br>{$this->conf['site_name']} Team</p>
        ";
        
        $mailContent = [
            'name_from' => $this->conf['site_name'],
            'email_from' => $this->conf['admin_email'],
            'name_to' => $data['fullname'],
            'email_to' => $data['email'],
            'subject' => $subject,
            'body' => $body
        ];
        
        return $this->mailer->Send_Mail($this->conf, $mailContent);
    }
    
    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
}
?>
