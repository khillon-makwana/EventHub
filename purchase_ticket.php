<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);

if ($event_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch event details
try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT e.*, u.fullname as organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE e.id = ? AND e.status IN ('upcoming', 'ongoing')
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $FlashMessageObject->setMsg('msg', 'Event not found or not available for ticket purchase.', 'danger');
        header("Location: dashboard.php");
        exit;
    }

    // Check if user already has a ticket
    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE event_id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $existing_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ticket'])) {
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Validate phone number (basic Kenyan format)
    if (empty($phone_number) || !preg_match('/^254[0-9]{9}$/', $phone_number)) {
        $FlashMessageObject->setMsg('msg', 'Please enter a valid Kenyan phone number (format: 2547XXXXXXXX).', 'danger');
    } elseif ($existing_ticket) {
        $FlashMessageObject->setMsg('msg', 'You already have a ticket for this event.', 'warning');
    } elseif ($event['available_tickets'] <= 0 && $event['total_tickets'] > 0) {
        $FlashMessageObject->setMsg('msg', 'Sorry, this event is sold out.', 'danger');
    } else {
        try {
            $pdo->beginTransaction();

            // Generate unique ticket code
            $ticket_code = 'TICKET-' . strtoupper(uniqid()) . '-' . $event_id;

            // Create ticket record
            $stmt = $pdo->prepare("INSERT INTO tickets (event_id, user_id, ticket_code) VALUES (?, ?, ?)");
            $stmt->execute([$event_id, $_SESSION['user_id'], $ticket_code]);
            $ticket_id = $pdo->lastInsertId();

            // Simulate M-Pesa payment (create payment record as completed)
            $mpesa_receipt = 'MPESA' . rand(100000, 999999);
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, event_id, ticket_id, amount, payment_method, transaction_id, mpesa_receipt_number, phone_number, status) 
                VALUES (?, ?, ?, ?, 'mpesa', ?, ?, ?, 'completed')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $event_id,
                $ticket_id,
                $event['ticket_price'],
                $mpesa_receipt,
                $mpesa_receipt,
                $phone_number
            ]);

            // Update available tickets
            if ($event['total_tickets'] > 0) {
                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
                $stmt->execute([$event_id]);
            }

            // ✅ Mark user as 'going' in event_attendees table
            $stmt = $pdo->prepare("
                INSERT INTO event_attendees (event_id, user_id, status, category_id)
                VALUES (?, ?, 'going', 1)
                ON DUPLICATE KEY UPDATE status = 'going'
            ");
            $stmt->execute([$event_id, $_SESSION['user_id']]);

            $pdo->commit();

            // Redirect to ticket page
            header("Location: view_ticket.php?ticket_id=" . $ticket_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $FlashMessageObject->setMsg('msg', 'Error processing ticket purchase: ' . $e->getMessage(), 'danger');
        }
    }
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Purchase Ticket</h4>
                </div>
                <div class="card-body">
                    <?php echo $FlashMessageObject->getMsg('msg'); ?>

                    <?php if ($existing_ticket): ?>
                        <div class="alert alert-info">
                            <h5>You already have a ticket for this event!</h5>
                            <p>You can view your ticket using the button below.</p>
                            <a href="view_ticket.php?ticket_id=<?php echo $existing_ticket['id']; ?>" class="btn btn-success">
                                View My Ticket
                            </a>
                        </div>
                    <?php elseif ($event['available_tickets'] <= 0 && $event['total_tickets'] > 0): ?>
                        <div class="alert alert-warning">
                            <h5>Event Sold Out</h5>
                            <p>Sorry, all tickets for this event have been sold.</p>
                        </div>
                    <?php else: ?>
                        <!-- Event Details -->
                        <div class="mb-4 p-3 border rounded">
                            <h5><?php echo htmlspecialchars($event['title']); ?></h5>
                            <p class="mb-1"><strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                            <p class="mb-0"><strong>Ticket Price:</strong> KSh <?php echo number_format($event['ticket_price'], 2); ?></p>
                        </div>

                        <!-- Purchase Form -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">M-Pesa Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                       placeholder="2547XXXXXXXX" required
                                       value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                                <div class="form-text">Enter your M-Pesa registered phone number in format 2547XXXXXXXX</div>
                            </div>

                            <div class="alert alert-info">
                                <h6>Simulated Payment</h6>
                                <p class="mb-0">This is a simulation. No actual payment will be processed. The ticket will be issued immediately.</p>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="purchase_ticket" class="btn btn-primary btn-lg">
                                    Purchase Ticket - KSh <?php echo number_format($event['ticket_price'], 2); ?>
                                </button>
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$LayoutObject->footer($conf);
?>
