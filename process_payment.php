<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$payment_id = (int)($_GET['payment_id'] ?? 0);

if ($payment_id <= 0) {
    $FlashMessageObject->setMsg('msg', 'Invalid payment request.', 'danger');
    header("Location: my_tickets.php");
    exit;
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get payment details
    $stmt = $pdo->prepare("
        SELECT p.*, e.title as event_title, u.fullname
        FROM payments p
        JOIN events e ON p.event_id = e.id
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $FlashMessageObject->setMsg('msg', 'Payment not found or already processed.', 'danger');
        header("Location: my_tickets.php");
        exit;
    }

    // Handle payment simulation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'complete_payment') {
            // Simulate successful M-Pesa payment
            $mpesa_receipt = 'MPESA' . date('YmdHis') . rand(1000, 9999);
            
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'completed', 
                    mpesa_receipt_number = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$mpesa_receipt, $payment_id]);
            
            // Ensure user is marked as 'going' in event_attendees
            $stmt = $pdo->prepare("
                INSERT INTO event_attendees (event_id, user_id, status, category_id) 
                VALUES (?, ?, 'going', 1)
                ON DUPLICATE KEY UPDATE status = 'going'
            ");
            $stmt->execute([$payment['event_id'], $_SESSION['user_id']]);
            
            // Send RSVP confirmation notification and email
            $NotificationManager->sendRSVPConfirmation($_SESSION['user_id'], $payment['event_id'], 'going');
            
            // Get the first ticket ID for this payment to redirect to ticket view
            $stmt = $pdo->prepare("
                SELECT t.id 
                FROM tickets t
                JOIN payment_tickets pt ON t.id = pt.ticket_id
                WHERE pt.payment_id = ?
                LIMIT 1
            ");
            $stmt->execute([$payment_id]);
            $ticket_id = $stmt->fetch(PDO::FETCH_COLUMN);
            
            $FlashMessageObject->setMsg('msg', 'Payment completed successfully! Your tickets have been issued.', 'success');
            header("Location: view_ticket.php?ticket_id=" . $ticket_id);
            exit;
            
        } elseif ($action === 'fail_payment') {
            // Simulate failed payment
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'failed',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$payment_id]);
            
            // Free up the reserved tickets
            $stmt = $pdo->prepare("
                UPDATE events 
                SET available_tickets = available_tickets + ? 
                WHERE id = ?
            ");
            $stmt->execute([$payment['quantity'], $payment['event_id']]);
            
            // Remove from event attendees
            $stmt = $pdo->prepare("
                DELETE FROM event_attendees 
                WHERE event_id = ? AND user_id = ?
            ");
            $stmt->execute([$payment['event_id'], $_SESSION['user_id']]);
            
            $FlashMessageObject->setMsg('msg', 'Payment failed. Please try again.', 'danger');
            header("Location: event_details.php?id=" . $payment['event_id']);
            exit;
        }
    }

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error processing payment: ' . $e->getMessage(), 'danger');
    header("Location: my_tickets.php");
    exit;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    .payment-container {
        min-height: 80vh;
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem 0;
    }
    
    .payment-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        background: white;
    }
    
    .payment-header {
        background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
        padding: 2.5rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .payment-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 4s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }
    
    .mpesa-logo {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .mpesa-logo i {
        font-size: 2.5rem;
        color: #00c853;
    }
    
    .payment-header h4 {
        color: white;
        font-weight: 700;
        margin: 0;
        position: relative;
        z-index: 1;
        font-size: 1.75rem;
    }
    
    .payment-body {
        padding: 2.5rem;
    }
    
    .info-banner {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border-left: 4px solid #2196f3;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 2rem;
    }
    
    .info-banner h5 {
        color: #1565c0;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }
    
    .info-banner p {
        color: #0d47a1;
        margin: 0;
    }
    
    .details-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .details-section h6 {
        color: #495057;
        font-weight: 700;
        margin-bottom: 1.25rem;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }
    
    .details-section h6 i {
        margin-right: 0.5rem;
        color: #00c853;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        color: #6c757d;
        font-weight: 500;
    }
    
    .detail-value {
        color: #212529;
        font-weight: 600;
        text-align: right;
    }
    
    .amount-highlight {
        font-size: 1.5rem;
        color: #00c853;
    }
    
    .transaction-code {
        background: #e9ecef;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    
    .action-buttons h6 {
        color: #495057;
        font-weight: 700;
        margin-bottom: 1.25rem;
        font-size: 1.1rem;
    }
    
    .btn-action {
        border: none;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        font-weight: 600;
        font-size: 1.05rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .btn-success-custom {
        background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
        color: white;
    }
    
    .btn-success-custom:hover {
        background: linear-gradient(135deg, #00b248 0%, #00d66b 100%);
    }
    
    .btn-danger-custom {
        background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
        color: white;
    }
    
    .btn-danger-custom:hover {
        background: linear-gradient(135deg, #e53935 0%, #ef5350 100%);
    }
    
    .btn-secondary-custom {
        background: #6c757d;
        color: white;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }
    
    .btn-secondary-custom:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    .security-notice {
        text-align: center;
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 12px;
        margin-top: 1.5rem;
    }
    
    .security-notice i {
        color: #00c853;
        margin-right: 0.5rem;
    }
    
    .security-notice small {
        color: #6c757d;
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .payment-body {
            padding: 1.5rem;
        }
        
        .detail-row {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .detail-value {
            text-align: left;
        }
    }
</style>

<div class="payment-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card payment-card">
                    <div class="payment-header">
                        <div class="mpesa-logo">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>M-Pesa Payment</h4>
                    </div>
                    
                    <div class="payment-body">
                        <?php echo $FlashMessageObject->getMsg('msg'); ?>
                        
                        <div class="info-banner">
                            <h5>
                                <i class="fas fa-info-circle me-2"></i>
                                Payment Simulation Mode
                            </h5>
                            <p>This is a simulated M-Pesa payment environment for testing purposes. In production, you would be redirected to the actual M-Pesa gateway.</p>
                        </div>
                        
                        <div class="details-section">
                            <h6>
                                <i class="fas fa-receipt"></i>
                                Payment Summary
                            </h6>
                            
                            <div class="detail-row">
                                <span class="detail-label">Event</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['event_title']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value amount-highlight">KSh <?php echo number_format($payment['amount'], 2); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Quantity</span>
                                <span class="detail-value"><?php echo $payment['quantity']; ?> ticket(s)</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Customer</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['fullname']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Transaction ID</span>
                                <span class="detail-value">
                                    <span class="transaction-code"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <h6>Choose Payment Result</h6>
                            
                            <form method="POST" class="mb-3">
                                <button type="submit" name="action" value="complete_payment" class="btn btn-action btn-success-custom w-100">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Complete Payment Successfully
                                </button>
                            </form>
                            
                            <form method="POST" class="mb-3">
                                <button type="submit" name="action" value="fail_payment" class="btn btn-action btn-danger-custom w-100">
                                    <i class="fas fa-times-circle me-2"></i>
                                    Simulate Payment Failure
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <a href="event_details.php?id=<?php echo $payment['event_id']; ?>" class="btn btn-secondary-custom">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Cancel & Return
                                </a>
                            </div>
                        </div>
                        
                        <div class="security-notice">
                            <small>
                                <i class="fas fa-shield-alt"></i>
                                This is a secure payment simulation. In production, all transactions are processed through official M-Pesa APIs with end-to-end encryption.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$LayoutObject->footer($conf);
?>