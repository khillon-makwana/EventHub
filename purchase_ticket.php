<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get event details
    $stmt = $pdo->prepare("
        SELECT e.*, u.fullname as organizer_name 
        FROM events e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.id = ? AND e.status IN ('upcoming', 'ongoing')
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $FlashMessageObject->setMsg('msg', 'Event not found or not available.', 'danger');
        header("Location: events.php");
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_tickets'])) {
        $quantity = (int)$_POST['quantity'];
        $total_amount = $event['ticket_price'] * $quantity;
        
        // Check if enough tickets available
        if ($event['available_tickets'] < $quantity) {
            $FlashMessageObject->setMsg('msg', "Only {$event['available_tickets']} tickets available.", 'danger');
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Create payment record first (single payment for all tickets)
                $transaction_id = 'MPESA_' . time() . '_' . uniqid();
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, event_id, amount, quantity, payment_method, transaction_id, status) 
                    VALUES (?, ?, ?, ?, 'mpesa', ?, 'pending')
                ");
                $stmt->execute([$_SESSION['user_id'], $event_id, $total_amount, $quantity, $transaction_id]);
                $payment_id = $pdo->lastInsertId();
                
                // Create tickets and link them to payment
                $ticket_ids = [];
                for ($i = 0; $i < $quantity; $i++) {
                    $ticket_code = generateUniqueTicketCode();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (event_id, user_id, ticket_code, status) 
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmt->execute([$event_id, $_SESSION['user_id'], $ticket_code]);
                    $ticket_id = $pdo->lastInsertId();
                    $ticket_ids[] = $ticket_id;
                    
                    // Link ticket to payment in payment_tickets table
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_tickets (payment_id, ticket_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$payment_id, $ticket_id]);
                }
                
                // Update available tickets
                $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE id = ?");
                $stmt->execute([$quantity, $event_id]);
                
                // Add to event attendees WITH QUANTITY TRACKING
                $stmt = $pdo->prepare("
                    INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) 
                    VALUES (?, ?, 'going', 1, ?) 
                    ON DUPLICATE KEY UPDATE status = 'going', quantity = quantity + ?
                ");
                $stmt->execute([$event_id, $_SESSION['user_id'], $quantity, $quantity]);
                
                $pdo->commit();
                
                // Redirect to M-Pesa payment processing (simulation)
                header("Location: process_payment.php?payment_id=" . $payment_id);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $FlashMessageObject->setMsg('msg', 'Error processing payment: ' . $e->getMessage(), 'danger');
            }
        }
    }

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error: ' . $e->getMessage(), 'danger');
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
.ticket-purchase-wrapper {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 60px 0;
}

.ticket-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    background: white;
}

.ticket-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border: none;
}

.ticket-header h4 {
    margin: 0;
    font-weight: 600;
    font-size: 1.75rem;
}

.ticket-header .subtitle {
    opacity: 0.9;
    margin-top: 5px;
    font-size: 0.95rem;
}

.ticket-body {
    padding: 40px;
}

.info-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border-color: #667eea;
}

.info-card h6 {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 20px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-card h6 i {
    font-size: 1.3rem;
}

.info-item {
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item strong {
    color: #495057;
    min-width: 120px;
    font-weight: 600;
}

.info-item span {
    color: #6c757d;
}

.quantity-selector {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
}

.quantity-selector label {
    color: #495057;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 15px;
}

.quantity-selector select {
    border-radius: 10px;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.quantity-selector select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.total-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    color: white;
    text-align: center;
}

.total-box label {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95rem;
    font-weight: 500;
}

.total-box h5 {
    margin: 10px 0 0 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.btn-purchase {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 12px;
    padding: 15px 30px;
    font-size: 1.1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-purchase:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.btn-cancel {
    background: white;
    color: #6c757d;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px 30px;
    font-size: 1.1rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #f8f9fa;
    color: #495057;
    border-color: #dee2e6;
    transform: translateY(-2px);
}

.availability-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    background: #d4edda;
    color: #155724;
}

.availability-badge.low {
    background: #fff3cd;
    color: #856404;
}

@media (max-width: 768px) {
    .ticket-purchase-wrapper {
        padding: 30px 0;
    }
    
    .ticket-body {
        padding: 25px;
    }
    
    .total-box h5 {
        font-size: 2rem;
    }
}
</style>

<div class="ticket-purchase-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="ticket-card">
                    <div class="ticket-header">
                        <h4><i class="fas fa-ticket-alt me-2"></i><?php echo htmlspecialchars($event['title']); ?></h4>
                        <div class="subtitle">Complete your ticket purchase securely</div>
                    </div>
                    <div class="ticket-body">
                        <?php echo $FlashMessageObject->getMsg('msg'); ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="info-card">
                                    <h6><i class="fas fa-info-circle"></i>Event Details</h6>
                                    <div class="info-item">
                                        <strong><i class="far fa-calendar me-2"></i>Date:</strong>
                                        <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="far fa-clock me-2"></i>Time:</strong>
                                        <span><?php echo date('g:i A', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-map-marker-alt me-2"></i>Location:</strong>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-user me-2"></i>Organizer:</strong>
                                        <span><?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6><i class="fas fa-tags"></i>Pricing Info</h6>
                                    <div class="info-item">
                                        <strong><i class="fas fa-money-bill-wave me-2"></i>Price:</strong>
                                        <span>KSh <?php echo number_format($event['ticket_price'], 2); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-tickets-alt me-2"></i>Available:</strong>
                                        <span>
                                            <span class="availability-badge <?php echo $event['available_tickets'] < 10 ? 'low' : ''; ?>">
                                                <?php echo $event['available_tickets']; ?> tickets
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-credit-card me-2"></i>Payment:</strong>
                                        <span>M-Pesa</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="quantity-selector">
                                <label for="quantity"><i class="fas fa-shopping-cart me-2"></i>Number of Tickets</label>
                                <select class="form-select" id="quantity" name="quantity" required>
                                    <?php for ($i = 1; $i <= min(10, $event['available_tickets']); $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == $quantity ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?> - KSh <?php echo number_format($event['ticket_price'] * $i, 2); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="total-box">
                                <label>Total Amount</label>
                                <h5 id="totalAmount">
                                    KSh <?php echo number_format($event['ticket_price'] * $quantity, 2); ?>
                                </h5>
                            </div>
                            
                            <div class="d-grid gap-3">
                                <button type="submit" name="purchase_tickets" class="btn btn-primary btn-purchase">
                                    <i class="fas fa-lock me-2"></i>
                                    Proceed to Secure Payment
                                </button>
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-cancel">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Event
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('quantity').addEventListener('change', function() {
    const quantity = this.value;
    const ticketPrice = <?php echo $event['ticket_price']; ?>;
    const totalAmount = quantity * ticketPrice;
    document.getElementById('totalAmount').textContent = 'KSh ' + totalAmount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
});
</script>

<?php
$LayoutObject->footer($conf);

// Helper function to generate unique ticket code
function generateUniqueTicketCode() {
    return 'TICKET_' . strtoupper(uniqid()) . '_' . mt_rand(1000, 9999);
}
?>