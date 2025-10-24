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
                // Check if event exists and is available
                $stmt = $pdo->prepare("
                    SELECT id, status, total_tickets, available_tickets, ticket_price 
                    FROM events 
                    WHERE id = ? AND status IN ('upcoming', 'ongoing')
                ");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$event) {
                    $FlashMessageObject->setMsg('msg', 'Event not found or not available.', 'danger');
                    break;
                }

                // Check if already attending
                $stmt = $pdo->prepare("
                    SELECT id, status 
                    FROM event_attendees 
                    WHERE event_id = ? AND user_id = ?
                ");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $attendee = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($attendee && $attendee['status'] === 'going') {
                    $FlashMessageObject->setMsg('msg', 'You are already attending this event!', 'info');
                    header("Location: event_details.php?id=" . $event_id);
                    exit;
                }

                // If event requires payment, redirect to ticket purchase page
                if ($event['ticket_price'] > 0) {
                    header("Location: purchase_ticket.php?event_id=" . $event_id);
                    exit;
                }

                // Handle free event attendance
                if ($event['available_tickets'] <= 0 && $event['total_tickets'] > 0) {
                    $FlashMessageObject->setMsg('msg', 'Sorry, this event is fully booked.', 'danger');
                    header("Location: event_details.php?id=" . $event_id);
                    exit;
                }

                // Register attendance for free events WITH QUANTITY
                $stmt = $pdo->prepare("
                    INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) 
                    VALUES (?, ?, 'going', 1, 1) 
                    ON DUPLICATE KEY UPDATE status = 'going', quantity = quantity + 1
                ");
                $stmt->execute([$event_id, $_SESSION['user_id']]);

                // Reduce available tickets if event has a limit
                if ($event['total_tickets'] > 0) {
                    $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
                    $stmt->execute([$event_id]);
                }

                // Send confirmation notification
                $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, 'going');

                $FlashMessageObject->setMsg('msg', 'You are now attending this event!', 'success');
                header("Location: event_details.php?id=" . $event_id);
                exit;

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
                        // Update existing RSVP - SET QUANTITY TO 0
                        $stmt = $pdo->prepare("UPDATE event_attendees SET status = 'interested', quantity = 0 WHERE event_id = ? AND user_id = ?");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        
                        // If they were going and it was ticketed, free up tickets
                        if ($existing_rsvp['status'] == 'going') {
                            // Count how many tickets they have for this event
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as ticket_count 
                                FROM payment_tickets pt
                                JOIN tickets t ON pt.ticket_id = t.id
                                JOIN payments p ON pt.payment_id = p.id
                                WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                            ");
                            $stmt->execute([$event_id, $_SESSION['user_id']]);
                            $ticket_count = $stmt->fetch(PDO::FETCH_COLUMN);
                            
                            if ($ticket_count > 0) {
                                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                                $stmt->execute([$ticket_count, $event_id]);
                                
                                // Cancel all active tickets
                                $stmt = $pdo->prepare("
                                    UPDATE tickets t
                                    JOIN payment_tickets pt ON t.id = pt.ticket_id
                                    JOIN payments p ON pt.payment_id = p.id
                                    SET t.status = 'cancelled' 
                                    WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                                ");
                                $stmt->execute([$event_id, $_SESSION['user_id']]);
                            } else {
                                // Free event - just free up one spot
                                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + 1 WHERE id = ?");
                                $stmt->execute([$event_id]);
                            }
                        }
                    } else {
                        // Create new interested RSVP WITH QUANTITY 0
                        $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) VALUES (?, ?, 'interested', 1, 0)");
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
                    $stmt = $pdo->prepare("SELECT available_tickets, title, ticket_price FROM events WHERE id = ? AND status IN ('upcoming', 'ongoing')");
                    $stmt->execute([$event_id]);
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($event) {
                        // Get current status AND QUANTITY
                        $stmt = $pdo->prepare("SELECT status, quantity FROM event_attendees WHERE event_id = ? AND user_id = ?");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        $current_rsvp = $stmt->fetch(PDO::FETCH_ASSOC);
                        $current_status = $current_rsvp['status'] ?? null;
                        $current_quantity = $current_rsvp['quantity'] ?? 0;
                        
                        // Handle ticket availability
                        if ($new_status == 'going' && $current_status != 'going') {
                            // Changing to going - need ticket
                            if ($event['ticket_price'] > 0) {
                                // Paid event - redirect to purchase
                                header("Location: purchase_ticket.php?event_id=" . $event_id);
                                exit;
                            } else {
                                // Free event - check availability
                                if ($event['available_tickets'] > 0) {
                                    // Use INSERT ... ON DUPLICATE KEY to handle both new and existing records
                                    $stmt = $pdo->prepare("
                                        INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) 
                                        VALUES (?, ?, 'going', 1, 1) 
                                        ON DUPLICATE KEY UPDATE status = 'going', quantity = 1
                                    ");
                                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                                    
                                    $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
                                    $stmt->execute([$event_id]);
                                    
                                    // Send RSVP confirmation notification
                                    $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, $new_status);
                                    
                                    $FlashMessageObject->setMsg('msg', 'RSVP status updated to Going!', 'success');
                                } else {
                                    $FlashMessageObject->setMsg('msg', 'No tickets available for this event', 'danger');
                                    break;
                                }
                            }
                        } elseif ($current_status == 'going' && $new_status != 'going') {
                            // Changing from going to not going/interested - free up tickets AND SET QUANTITY TO 0
                            
                            // Count how many paid tickets they have for this event
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as ticket_count 
                                FROM payment_tickets pt
                                JOIN tickets t ON pt.ticket_id = t.id
                                JOIN payments p ON pt.payment_id = p.id
                                WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                            ");
                            $stmt->execute([$event_id, $_SESSION['user_id']]);
                            $ticket_count = $stmt->fetch(PDO::FETCH_COLUMN);
                            
                            // Update attendee with new status and QUANTITY 0
                            $stmt = $pdo->prepare("UPDATE event_attendees SET status = ?, quantity = 0 WHERE event_id = ? AND user_id = ?");
                            $stmt->execute([$new_status, $event_id, $_SESSION['user_id']]);
                            
                            // Free up ticket slots
                            if ($ticket_count > 0) {
                                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                                $stmt->execute([$ticket_count, $event_id]);
                                
                                // Cancel all active paid tickets
                                $stmt = $pdo->prepare("
                                    UPDATE tickets t
                                    JOIN payment_tickets pt ON t.id = pt.ticket_id
                                    JOIN payments p ON pt.payment_id = p.id
                                    SET t.status = 'cancelled' 
                                    WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                                ");
                                $stmt->execute([$event_id, $_SESSION['user_id']]);
                            } else {
                                // Free event - free up spots based on previous quantity
                                $spots_to_free = $current_quantity > 0 ? $current_quantity : 1;
                                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                                $stmt->execute([$spots_to_free, $event_id]);
                            }
                            
                            // Send RSVP confirmation notification
                            $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $event_id, $new_status);
                            
                            $FlashMessageObject->setMsg('msg', 'RSVP status updated to ' . ucfirst($new_status) . '!', 'success');
                        } else {
                            // Status change that doesn't affect tickets - SET QUANTITY TO 0 for non-going statuses
                            $new_quantity = ($new_status == 'going') ? 1 : 0;
                            $stmt = $pdo->prepare("UPDATE event_attendees SET status = ?, quantity = ? WHERE event_id = ? AND user_id = ?");
                            $stmt->execute([$new_status, $new_quantity, $event_id, $_SESSION['user_id']]);
                            
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
                $stmt = $pdo->prepare("SELECT status, quantity FROM event_attendees WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $_SESSION['user_id']]);
                $current_rsvp = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_status = $current_rsvp['status'] ?? null;
                $current_quantity = $current_rsvp['quantity'] ?? 0;
                
                if ($current_status) {
                    // Count how many paid tickets they have for this event
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as ticket_count 
                        FROM payment_tickets pt
                        JOIN tickets t ON pt.ticket_id = t.id
                        JOIN payments p ON pt.payment_id = p.id
                        WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                    ");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    $ticket_count = $stmt->fetch(PDO::FETCH_COLUMN);
                    
                    // Remove attendee
                    $stmt = $pdo->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    // Free up ticket slots if they were going
                    if ($current_status == 'going') {
                        if ($ticket_count > 0) {
                            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                            $stmt->execute([$ticket_count, $event_id]);
                            
                            // Cancel all active paid tickets
                            $stmt = $pdo->prepare("
                                UPDATE tickets t
                                JOIN payment_tickets pt ON t.id = pt.ticket_id
                                JOIN payments p ON pt.payment_id = p.id
                                SET t.status = 'cancelled' 
                                WHERE t.event_id = ? AND t.user_id = ? AND p.status = 'completed' AND t.status = 'active'
                            ");
                            $stmt->execute([$event_id, $_SESSION['user_id']]);
                        } else {
                            // Free event - free up spots based on quantity
                            $spots_to_free = $current_quantity > 0 ? $current_quantity : 1;
                            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                            $stmt->execute([$spots_to_free, $event_id]);
                        }
                    }
                    
                    // Also remove any feedback they left
                    $stmt = $pdo->prepare("DELETE FROM feedback WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$event_id, $_SESSION['user_id']]);
                    
                    $FlashMessageObject->setMsg('msg', 'You are no longer attending this event', 'info');
                } else {
                    $FlashMessageObject->setMsg('msg', 'You were not attending this event', 'info');
                }
                break;
                
            // ... rest of the cases remain the same (cancel, publish, submit_feedback, etc.)
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
                        
                        // Cancel all active tickets for this event
                        $stmt = $pdo->prepare("
                            UPDATE tickets 
                            SET status = 'cancelled' 
                            WHERE event_id = ? AND status = 'active'
                        ");
                        $stmt->execute([$event_id]);
                        
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

            case 'mark_ticket_used':
                // Mark ticket as used (organizer only)
                $ticket_id = (int)($_GET['ticket_id'] ?? 0);
                
                if ($ticket_id > 0) {
                    // Verify organizer owns the event
                    $stmt = $pdo->prepare("
                        SELECT e.id 
                        FROM events e 
                        JOIN tickets t ON e.id = t.event_id 
                        WHERE t.id = ? AND e.user_id = ?
                    ");
                    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($event) {
                        $stmt = $pdo->prepare("UPDATE tickets SET status = 'used' WHERE id = ?");
                        $stmt->execute([$ticket_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $FlashMessageObject->setMsg('msg', 'Ticket marked as used successfully', 'success');
                        } else {
                            $FlashMessageObject->setMsg('msg', 'Ticket not found or already used', 'warning');
                        }
                    } else {
                        $FlashMessageObject->setMsg('msg', 'Access denied or ticket not found', 'danger');
                    }
                } else {
                    $FlashMessageObject->setMsg('msg', 'Invalid ticket ID', 'danger');
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