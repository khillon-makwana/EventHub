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
               p.transaction_id, p.amount, p.purchase_date, p.mpesa_receipt_number,
               p.status as payment_status, p.quantity
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN payment_tickets pt ON t.id = pt.ticket_id
        LEFT JOIN payments p ON pt.payment_id = p.id
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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');

body {
    font-family: 'Poppins', sans-serif;
}

.ticket-wrapper {
    max-width: 700px;
    margin: 0 auto;
    perspective: 1000px;
}

.ticket-container {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    position: relative;
    transition: transform 0.3s ease;
}

.ticket-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
}

.ticket-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        linear-gradient(135deg, rgba(102, 126, 234, 0.05) 25%, transparent 25%),
        linear-gradient(225deg, rgba(118, 75, 162, 0.05) 25%, transparent 25%),
        linear-gradient(45deg, rgba(102, 126, 234, 0.05) 25%, transparent 25%),
        linear-gradient(315deg, rgba(118, 75, 162, 0.05) 25%, transparent 25%);
    background-size: 40px 40px;
    background-position: 0 0, 20px 0, 20px -20px, 0 20px;
    pointer-events: none;
}

.ticket-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.ticket-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-30%, -30%) scale(1.1); }
}

.ticket-header h1 {
    color: white;
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 10px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
    z-index: 1;
}

.event-title {
    color: rgba(255,255,255,0.95);
    font-size: 1.3rem;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.ticket-divider {
    height: 30px;
    background: white;
    position: relative;
    margin: 0;
}

.ticket-divider::before,
.ticket-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 30px;
    height: 30px;
    background: #f8f9fa;
    border-radius: 50%;
    transform: translateY(-50%);
}

.ticket-divider::before { left: -15px; }
.ticket-divider::after { right: -15px; }

.ticket-divider-line {
    position: absolute;
    top: 50%;
    left: 30px;
    right: 30px;
    height: 2px;
    background: repeating-linear-gradient(
        90deg,
        #ddd 0px,
        #ddd 10px,
        transparent 10px,
        transparent 20px
    );
}

.ticket-body {
    padding: 40px 30px;
    background: white;
    position: relative;
}

.qr-section {
    text-align: center;
    margin-bottom: 30px;
}

.qr-placeholder {
    width: 180px;
    height: 180px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    color: white;
    font-weight: bold;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: transform 0.3s ease;
}

.qr-placeholder:hover {
    transform: scale(1.05);
}

.qr-placeholder i {
    font-size: 4rem;
}

.ticket-code {
    font-family: 'Courier New', monospace;
    font-size: 1.8rem;
    font-weight: bold;
    letter-spacing: 4px;
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    padding: 20px;
    border: 3px dashed #667eea;
    border-radius: 12px;
    margin: 25px 0;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.ticket-code:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    border-color: #764ba2;
    transform: scale(1.02);
}

.ticket-code::after {
    content: 'Click to copy';
    position: absolute;
    bottom: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    color: #999;
    font-family: 'Poppins', sans-serif;
    letter-spacing: normal;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.ticket-code:hover::after {
    opacity: 1;
}

.ticket-info {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 20px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-item {
    flex: 1;
}

.info-label {
    font-size: 0.85rem;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.info-label i {
    color: #667eea;
}

.info-value {
    font-size: 1rem;
    color: #333;
    font-weight: 500;
}

.ticket-footer {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 25px 30px;
    text-align: center;
    color: white;
}

.ticket-footer p {
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #10b981;
    color: white;
}

.status-used {
    background: #f59e0b;
    color: white;
}

.status-cancelled {
    background: #6b7280;
    color: white;
}

.status-completed {
    background: #10b981;
    color: white;
}

.status-pending {
    background: #f59e0b;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-custom {
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.btn-primary-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-secondary-custom {
    background: white;
    color: #333;
    border: 2px solid #e5e7eb;
}

.alert-custom {
    background: white;
    border-left: 4px solid #3b82f6;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.alert-custom h5 {
    color: #3b82f6;
    font-weight: 600;
    margin-bottom: 15px;
}

.alert-custom ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.alert-custom li {
    margin-bottom: 8px;
    color: #555;
}

@media print {
    .no-print {
        display: none !important;
    }
    .ticket-container {
        box-shadow: none;
    }
    .ticket-container:hover {
        transform: none;
    }
}

@media (max-width: 768px) {
    .info-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-custom {
        width: 100%;
        justify-content: center;
    }
    
    .ticket-code {
        font-size: 1.3rem;
        letter-spacing: 2px;
    }
}

.copy-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
    z-index: 9999;
    display: none;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 style="color: #333; font-weight: 600;">Your Event Ticket</h2>
        <a href="my_tickets.php" class="btn btn-secondary-custom">
            <i class="fas fa-arrow-left"></i>Back to Tickets
        </a>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

<?php if (!empty($_GET['paid'])): ?>
<div class="container mt-3">
    <div class="alert alert-success d-flex justify-content-between align-items-center" role="alert">
        <div>
            <i class="fas fa-check-circle me-2"></i>
            Payment completed successfully! Your ticket is ready.
        </div>
        <a href="my_tickets.php" class="btn btn-sm btn-success">
            <i class="fas fa-ticket-alt me-1"></i> View All My Tickets
        </a>
    </div>
</div>
<?php endif; ?>
    <div class="ticket-wrapper">
        <div class="ticket-container" id="ticketContainer">
            <div class="ticket-pattern"></div>
            
            <!-- Ticket Header -->
            <div class="ticket-header">
                <h1><i class="fas fa-ticket-alt me-2"></i>EVENT TICKET</h1>
                <p class="event-title mb-0"><?php echo htmlspecialchars($ticket['event_title']); ?></p>
            </div>

            <!-- Divider -->
            <div class="ticket-divider">
                <div class="ticket-divider-line"></div>
            </div>

            <!-- Ticket Body -->
            <div class="ticket-body">
                <!-- QR Code Section -->
                <div class="qr-section">
                    <div class="qr-placeholder">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>

                <!-- Ticket Code -->
                <div class="ticket-code" id="ticketCode">
                    <?php echo htmlspecialchars($ticket['ticket_code']); ?>
                </div>

                <!-- Event Information -->
                <div class="ticket-info">
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar"></i>Event
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['event_title']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-clock"></i>Date & Time
                            </div>
                            <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($ticket['event_date'])); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt"></i>Location
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['location']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user"></i>Ticket Holder
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($ticket['amount']): ?>
                <div class="ticket-info">
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-money-bill-wave"></i>Amount Paid
                            </div>
                            <div class="info-value">KSh <?php echo number_format($ticket['amount'], 2); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-receipt"></i>Transaction ID
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['transaction_id'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-mobile-alt"></i>M-Pesa Receipt
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['mpesa_receipt_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-check"></i>Purchase Date
                            </div>
                            <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($ticket['purchase_date'])); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-check-circle"></i>Payment Status
                            </div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $ticket['payment_status']; ?>">
                                    <?php echo ucfirst($ticket['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($ticket['quantity'] > 1): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-ticket-alt"></i>Tickets in Order
                            </div>
                            <div class="info-value"><?php echo $ticket['quantity']; ?> tickets</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ticket Footer -->
            <div class="ticket-footer">
                <p><i class="fas fa-info-circle me-2"></i>Please present this ticket at the event entrance</p>
                <p class="mb-0">
                    Ticket ID: #<?php echo $ticket['id']; ?> | 
                    <span class="status-badge status-<?php echo $ticket['status']; ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons mt-4 no-print">
        <button onclick="downloadTicketPDF()" class="btn btn-primary-custom">
            <i class="fas fa-file-pdf"></i>Download as PDF
        </button>
        <button onclick="downloadTicketImage()" class="btn btn-primary-custom">
            <i class="fas fa-image"></i>Download as Image
        </button>
        <button onclick="window.print()" class="btn btn-secondary-custom">
            <i class="fas fa-print"></i>Print Ticket
        </button>
    </div>

    <!-- Important Information -->
    <div class="alert-custom mt-4 no-print">
        <h5><i class="fas fa-info-circle me-2"></i>Important Information</h5>
        <ul>
            <li>Please bring a valid ID matching the ticket holder name</li>
            <li>This ticket is non-transferable and non-refundable</li>
            <li>Keep this ticket safe - it's your entry pass to the event</li>
            <li>For any issues, contact the event organizer</li>
            <?php if ($ticket['status'] == 'active'): ?>
            <li><strong>This ticket is ACTIVE and valid for entry</strong></li>
            <?php elseif ($ticket['status'] == 'used'): ?>
            <li><strong>This ticket has been USED and is no longer valid</strong></li>
            <?php elseif ($ticket['status'] == 'cancelled'): ?>
            <li><strong>This ticket has been CANCELLED and is not valid for entry</strong></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Copy Notification -->
<div class="copy-notification" id="copyNotification">
    <i class="fas fa-check-circle"></i>
    <span>Ticket code copied to clipboard!</span>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// Copy ticket code to clipboard
document.addEventListener('DOMContentLoaded', function() {
    const ticketCode = document.getElementById('ticketCode');
    const notification = document.getElementById('copyNotification');
    
    ticketCode.addEventListener('click', function() {
        const text = this.textContent.trim();
        navigator.clipboard.writeText(text).then(function() {
            notification.style.display = 'flex';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        });
    });
});

// Download ticket as image
function downloadTicketImage() {
    const ticketContainer = document.getElementById('ticketContainer');
    const buttons = document.querySelectorAll('.no-print');
    
    // Temporarily hide buttons
    buttons.forEach(btn => btn.style.display = 'none');
    
    html2canvas(ticketContainer, {
        scale: 2,
        backgroundColor: '#f8f9fa',
        logging: false,
        useCORS: true
    }).then(canvas => {
        // Show buttons again
        buttons.forEach(btn => btn.style.display = '');
        
        // Convert to blob and download
        canvas.toBlob(function(blob) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = 'ticket-<?php echo $ticket['ticket_code']; ?>.png';
            link.href = url;
            link.click();
            URL.revokeObjectURL(url);
        });
    });
}

// Download ticket as PDF
function downloadTicketPDF() {
    const ticketContainer = document.getElementById('ticketContainer');
    const buttons = document.querySelectorAll('.no-print');
    
    // Temporarily hide buttons
    buttons.forEach(btn => btn.style.display = 'none');
    
    html2canvas(ticketContainer, {
        scale: 2,
        backgroundColor: '#f8f9fa',
        logging: false,
        useCORS: true
    }).then(canvas => {
        // Show buttons again
        buttons.forEach(btn => btn.style.display = '');
        
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        
        // Calculate dimensions
        const imgWidth = 210; // A4 width in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        // Create PDF
        const pdf = new jsPDF({
            orientation: imgHeight > imgWidth ? 'portrait' : 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        
        // Add image to PDF
        const xOffset = 0;
        const yOffset = (297 - imgHeight) / 2; // Center vertically on A4 (297mm height)
        
        pdf.addImage(imgData, 'PNG', xOffset, yOffset > 0 ? yOffset : 0, imgWidth, imgHeight);
        
        // Download PDF
        pdf.save('ticket-<?php echo $ticket['ticket_code']; ?>.pdf');
    });
}
</script>

<?php
$LayoutObject->footer($conf);
?>
