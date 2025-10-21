<?php
require 'ClassAutoLoad.php';

// Require login for most actions
if (!isset($_SESSION['user_id'])) {
    $FlashMessageObject->setMsg('msg', 'Please sign in to perform this action', 'danger');
    header("Location: signin.php");
    exit;
}

$action = $_GET['action'] ?? '';
$event_id = (int)($_GET['id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    $event_id = (int)($_POST['event_id'] ?? $event_id);
}

if ($action && $event_id > 0) {
    try {
        $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        switch($action) {
            case 'attend':
                // Check if event exists and has available tickets
                $stmt = $pdo->prepare("SELECT available_tickets, status, title FROM events WHERE id = ? AND status IN ('upcoming', 'ongoing')");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($event && ($event['available_tickets'] > 0 || $event['total_tickets'] == 0)) {
                    // Check if already attending
                    $stmt = $pdo->prepare("SELECT id FROM event_attendees WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    if (!$stmt->fetch()) {
                        // Add attendee with 'going' status
                        $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id, status, category_id) VALUES (?, ?, 'going', 1)");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        
                        // Update available tickets if it's a ticketed event
                        if ($event['available_tickets'] > 0) {
                            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
                            $stmt->execute([$event_id]);
                        }
                        
                        // Send RSVP confirmation notification
                        $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, 'going');
                        
                        $FlashMessageObject->setMsg('msg', 'You are now attending this event!', 'success');
                    } else {
                        $FlashMessageObject->setMsg('msg', 'You are already attending this event!', 'info');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event is fully booked or not available', 'danger');
                }
                break;
                
            case 'interested':
                // Mark as interested (doesn't require tickets)
                $stmt = $pdo->prepare("SELECT status, title FROM events WHERE id = ? AND status IN ('upcoming', 'ongoing')");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($event) {
                    // Check if already has an RSVP
                    $stmt = $pdo->prepare("SELECT id, status FROM event_attendees WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    $existing_rsvp = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_rsvp) {
                        // Update existing RSVP
                        $stmt = $pdo->prepare("UPDATE event_attendees SET status = 'interested' WHERE event_id = ? AND user_id = ?");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        
                        // If they were going and it was ticketed, free up a ticket
                        if ($existing_rsvp['status'] == 'going') {
                            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + 1 WHERE id = ?");
                            $stmt->execute([$event_id]);
                        }
                    } else {
                        // Create new interested RSVP
                        $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id, status, category_id) VALUES (?, ?, 'interested', 1)");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                    }
                    
                    // Send RSVP confirmation notification
                    $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, 'interested');
                    
                    $FlashMessageObject->setMsg('msg', 'Marked as interested!', 'success');
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event not available', 'danger');
                }
                break;
                
            case 'change_status':
                $new_status = $_GET['status'] ?? 'going';
                $valid_statuses = ['going', 'interested', 'not going'];
                
                if (in_array($new_status, $valid_statuses)) {
                    // Check if event exists
                    $stmt = $pdo->prepare("SELECT available_tickets, title FROM events WHERE id = ? AND status IN ('upcoming', 'ongoing')");
                    $stmt->execute([$event_id]);
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($event) {
                        // Get current status
                        $stmt = $pdo->prepare("SELECT status FROM event_attendees WHERE event_id = ? AND user_id = ?");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        $current_status = $stmt->fetch(PDO::FETCH_COLUMN);
                        
                        // Handle ticket availability
                        if ($new_status == 'going' && $current_status != 'going') {
                            // Changing to going - need ticket
                            if ($event['available_tickets'] > 0) {
                                $stmt = $pdo->prepare("UPDATE event_attendees SET status = ? WHERE event_id = ? AND user_id = ?");
                                $stmt->execute([$new_status, $event_id, $_SESSION['user_id']]);
                                
                                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
                                $stmt->execute([$event_id]);
                                
                                // Send RSVP confirmation notification
                                $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, $new_status);
                                
                                $FlashMessageObject->setMsg('msg', 'RSVP status updated to Going!', 'success');
                            } else {
                                $FlashMessageObject->setMsg('msg', 'No tickets available for this event', 'danger');
                                break;
                            }
                        } elseif ($current_status == 'going' && $new_status != 'going') {
                            // Changing from going to not going/interested - free up ticket
                            $stmt = $pdo->prepare("UPDATE event_attendees SET status = ? WHERE event_id = ? AND user_id = ?");
                            $stmt->execute([$new_status, $event_id, $_SESSION['user_id']]);
                            
                            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + 1 WHERE id = ?");
                            $stmt->execute([$event_id]);
                            
                            // Send RSVP confirmation notification
                            $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, $new_status);
                            
                            $FlashMessageObject->setMsg('msg', 'RSVP status updated to ' . ucfirst($new_status) . '!', 'success');
                        } else {
                            // Status change that doesn't affect tickets
                            $stmt = $pdo->prepare("UPDATE event_attendees SET status = ? WHERE event_id = ? AND user_id = ?");
                            $stmt->execute([$new_status, $event_id, $_SESSION['user_id']]);
                            
                            // Send RSVP confirmation notification
                            $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, $new_status);
                            
                            $FlashMessageObject->setMsg('msg', 'RSVP status updated to ' . ucfirst($new_status) . '!', 'success');
                        }
                    } else {
                        $FlashMessageObject->setMsg('msg', 'Event not available', 'danger');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Invalid status', 'danger');
                }
                break;
                
            case 'unattend':
                // Remove attendee completely
                $stmt = $pdo->prepare("SELECT status FROM event_attendees WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $current_status = $stmt->fetch(PDO::FETCH_COLUMN);
                
                if ($current_status) {
                    // Remove attendee
                    $stmt = $pdo->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    // Free up ticket if they were going
                    if ($current_status == 'going') {
                        $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + 1 WHERE id = ?");
                        $stmt->execute([$event_id]);
                    }
                    
                    // Also remove any feedback they left
                    $stmt = $pdo->prepare("DELETE FROM feedback WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    $FlashMessageObject->setMsg('msg', 'You are no longer attending this event', 'info');
                } else {
                    $FlashMessageObject->setMsg('msg', 'You were not attending this event', 'info');
                }
                break;
                
            case 'cancel':
                // Cancel event (owner only)
                $stmt = $pdo->prepare("SELECT title FROM events WHERE id = ? AND user_id = ?");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($event) {
                    $stmt = $pdo->prepare("UPDATE events SET status = 'cancelled' WHERE id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Notify attendees about cancellation
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, event_id, title, message, type) 
                            SELECT ea.user_id, ?, 'Event Cancelled', ?, 'event_update'
                            FROM event_attendees ea 
                            WHERE ea.event_id = ?
                        ");
                        $message = "The event '{$event['title']}' has been cancelled by the organizer.";
                        $stmt->execute([$event_id, $message, $event_id]);
                        
                        $FlashMessageObject->setMsg('msg', 'Event cancelled successfully', 'success');
                    } else {
                        $FlashMessageObject->setMsg('msg', 'Event not found or access denied', 'danger');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event not found or access denied', 'danger');
                }
                break;
                
            case 'publish':
                // Change event status to upcoming (publish)
                $stmt = $pdo->prepare("UPDATE events SET status = 'upcoming' WHERE id = ? AND user_id = ? AND status = 'draft'");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $FlashMessageObject->setMsg('msg', 'Event published successfully!', 'success');
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event not found or cannot be published', 'danger');
                }
                break;
                
            case 'submit_feedback':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $rating = (int)($_POST['rating'] ?? 0);
                    $comment = trim($_POST['comment'] ?? '');
                    
                    // Validate rating
                    if ($rating < 1 || $rating > 5) {
                        $FlashMessageObject->setMsg('msg', 'Please provide a valid rating (1-5 stars)', 'danger');
                        break;
                    }
                    
                    // Check if user attended the event and event is ongoing or completed
                    $stmt = $pdo->prepare("
                        SELECT e.status 
                        FROM events e 
                        JOIN event_attendees ea ON e.id = ea.event_id 
                        WHERE e.id = ? AND ea.user_id = ? AND ea.status = 'going'
                    ");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    $event_status = $stmt->fetch(PDO::FETCH_COLUMN);
                    
                    if ($event_status == 'completed' || $event_status == 'ongoing') {
                        // Check if feedback already exists
                        $stmt = $pdo->prepare("SELECT id FROM feedback WHERE event_id = ? AND user_id = ?");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        
                        if ($stmt->fetch()) {
                            // Update existing feedback
                            $stmt = $pdo->prepare("UPDATE feedback SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP WHERE event_id = ? AND user_id = ?");
                            $stmt->execute([$rating, $comment, $event_id, $_SESSION['user_id']]);
                            $FlashMessageObject->setMsg('msg', 'Feedback updated successfully!', 'success');
                        } else {
                            // Create new feedback
                            $stmt = $pdo->prepare("INSERT INTO feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$event_id, $_SESSION['user_id'], $rating, $comment]);
                            $FlashMessageObject->setMsg('msg', 'Thank you for your feedback!', 'success');
                        }
                    } else {
                        $FlashMessageObject->setMsg('msg', 'You can only leave feedback for ongoing or completed events you are attending.', 'danger');
                    }
                }
                break;
                
            case 'delete_feedback':
                // Delete user's feedback
                $stmt = $pdo->prepare("DELETE FROM feedback WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $FlashMessageObject->setMsg('msg', 'Feedback deleted successfully', 'success');
                } else {
                    $FlashMessageObject->setMsg('msg', 'Feedback not found', 'info');
                }
                break;
                
            case 'mark_ongoing':
                // Mark event as ongoing (owner only)
                $stmt = $pdo->prepare("SELECT title FROM events WHERE id = ? AND user_id = ? AND status = 'upcoming'");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($event) {
                    $stmt = $pdo->prepare("UPDATE events SET status = 'ongoing' WHERE id = ? AND user_id = ? AND status = 'upcoming'");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Notify attendees
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, event_id, title, message, type) 
                            SELECT ea.user_id, ?, 'Event Started', ?, 'event_update'
                            FROM event_attendees ea 
                            WHERE ea.event_id = ? AND ea.status = 'going'
                        ");
                        $message = "The event '{$event['title']}' has started!";
                        $stmt->execute([$event_id, $message, $event_id]);
                        
                        $FlashMessageObject->setMsg('msg', 'Event marked as ongoing', 'success');
                    } else {
                        $FlashMessageObject->setMsg('msg', 'Event not found or cannot be marked as ongoing', 'danger');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event not found or cannot be marked as ongoing', 'danger');
                }
                break;
                
            case 'mark_completed':
                // Mark event as completed (owner only)
                $stmt = $pdo->prepare("SELECT title FROM events WHERE id = ? AND user_id = ? AND status IN ('upcoming', 'ongoing')");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($event) {
                    $stmt = $pdo->prepare("UPDATE events SET status = 'completed' WHERE id = ? AND user_id = ? AND status IN ('upcoming', 'ongoing')");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Notify attendees to leave feedback
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, event_id, title, message, type) 
                            SELECT ea.user_id, ?, 'Event Completed', ?, 'event_update'
                            FROM event_attendees ea 
                            WHERE ea.event_id = ? AND ea.status = 'going'
                        ");
                        $message = "The event '{$event['title']}' has completed. Please leave your feedback!";
                        $stmt->execute([$event_id, $message, $event_id]);
                        
                        $FlashMessageObject->setMsg('msg', 'Event marked as completed', 'success');
                    } else {
                        $FlashMessageObject->setMsg('msg', 'Event not found or cannot be marked as completed', 'danger');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Event not found or cannot be marked as completed', 'danger');
                }
                break;
                
            default:
                $FlashMessageObject->setMsg('msg', 'Invalid action', 'danger');
                break;
        }
        
    } catch (PDOException $e) {
        $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
    }
} else {
    $FlashMessageObject->setMsg('msg', 'Invalid request', 'danger');
}

// Redirect back to previous page or events page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;
?>
