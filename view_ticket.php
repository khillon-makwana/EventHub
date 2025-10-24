<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$ticket_id = (int)($_GET['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    header("Location: my_tickets.php");
    exit;
}

// Fetch ticket details
try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title, e.event_date, e.location, e.description, e.image,
               u.fullname as user_name, u.email as user_email,
               p.transaction_id, p.amount, p.purchase_date, p.mpesa_receipt_number
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN payments p ON t.id = p.ticket_id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $FlashMessageObject->setMsg('msg', 'Ticket not found or access denied.', 'danger');
        header("Location: my_tickets.php");
        exit;
    }

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error loading ticket: ' . $e->getMessage(), 'danger');
    header("Location: my_tickets.php");
    exit;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
.ticket-container {
    max-width: 600px;
    margin: 0 auto;
    border: 2px solid #333;
    border-radius: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
}

.ticket-header {
    background: rgba(0,0,0,0.2);
    padding: 20px;
    text-align: center;
    border-bottom: 2px dashed white;
}

.ticket-body {
    padding: 30px;
}

.ticket-info {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.ticket-code {
    font-family: 'Courier New', monospace;
    font-size: 1.5rem;
    font-weight: bold;
    letter-spacing: 2px;
    text-align: center;
    background: rgba(0,0,0,0.3);
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
}

.ticket-footer {
    background: rgba(0,0,0,0.2);
    padding: 15px;
    text-align: center;
    border-top: 2px dashed white;
    font-size: 0.9rem;
}

.qr-placeholder {
    width: 150px;
    height: 150px;
    background: white;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: #333;
    font-weight: bold;
}

@media print {
    .no-print {
        display: none !important;
    }
    .ticket-container {
        border: none;
        box-shadow: none;
    }
}
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2>Your Ticket</h2>
        <div>
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print me-2"></i>Print Ticket
            </button>
            <a href="my_tickets.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Tickets
            </a>
        </div>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <div class="ticket-container">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <h1 class="mb-0">EVENT TICKET</h1>
            <p class="mb-0"><?php echo htmlspecialchars($ticket['event_title']); ?></p>
        </div>

        <!-- Ticket Body -->
        <div class="ticket-body">
            <!-- QR Code Placeholder -->
            <div class="qr-placeholder">
                <div class="text-center">
                    <i class="fas fa-qrcode fa-3x mb-2"></i>
                    <div>QR CODE</div>
                </div>
            </div>

            <!-- Ticket Code -->
            <div class="ticket-code">
                <?php echo htmlspecialchars($ticket['ticket_code']); ?>
            </div>

            <!-- Event Information -->
            <div class="ticket-info">
                <div class="row">
                    <div class="col-6">
                        <strong>Event:</strong><br>
                        <?php echo htmlspecialchars($ticket['event_title']); ?>
                    </div>
                    <div class="col-6">
                        <strong>Date & Time:</strong><br>
                        <?php echo date('F j, Y g:i A', strtotime($ticket['event_date'])); ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-6">
                        <strong>Location:</strong><br>
                        <?php echo htmlspecialchars($ticket['location']); ?>
                    </div>
                    <div class="col-6">
                        <strong>Ticket Holder:</strong><br>
                        <?php echo htmlspecialchars($ticket['user_name']); ?>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="ticket-info">
                <div class="row">
                    <div class="col-6">
                        <strong>Amount Paid:</strong><br>
                        KSh <?php echo number_format($ticket['amount'], 2); ?>
                    </div>
                    <div class="col-6">
                        <strong>Transaction ID:</strong><br>
                        <?php echo htmlspecialchars($ticket['transaction_id']); ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-6">
                        <strong>M-Pesa Receipt:</strong><br>
                        <?php echo htmlspecialchars($ticket['mpesa_receipt_number']); ?>
                    </div>
                    <div class="col-6">
                        <strong>Purchase Date:</strong><br>
                        <?php echo date('M j, Y g:i A', strtotime($ticket['purchase_date'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Footer -->
        <div class="ticket-footer">
            <p class="mb-1">Please present this ticket at the event entrance</p>
            <p class="mb-0">Ticket ID: <?php echo $ticket['id']; ?> | Status: <?php echo ucfirst($ticket['status']); ?></p>
        </div>
    </div>

    <!-- Important Information -->
    <div class="alert alert-info mt-4 no-print">
        <h5><i class="fas fa-info-circle me-2"></i>Important Information</h5>
        <ul class="mb-0">
            <li>Please bring a valid ID matching the ticket holder name</li>
            <li>This ticket is non-transferable and non-refundable</li>
            <li>Keep this ticket safe - it's your entry pass to the event</li>
            <li>For any issues, contact the event organizer</li>
        </ul>
    </div>
</div>

<script>
// Auto-select ticket code for easy copying
document.addEventListener('DOMContentLoaded', function() {
    const ticketCode = document.querySelector('.ticket-code');
    ticketCode.addEventListener('click', function() {
        const text = this.textContent.trim();
        navigator.clipboard.writeText(text).then(function() {
            // Show copied message
            const originalText = ticketCode.textContent;
            ticketCode.textContent = 'Copied to clipboard!';
            setTimeout(() => {
                ticketCode.textContent = originalText;
            }, 2000);
        });
    });
});
</script>

<?php
$LayoutObject->footer($conf);
?>

