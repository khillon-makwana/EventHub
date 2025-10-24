<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get event details and verify ownership
    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM events e
        WHERE e.id = ? AND e.user_id = ?
    ");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $FlashMessageObject->setMsg('msg', 'Event not found or access denied.', 'danger');
        header("Location: my_events.php");
        exit;
    }

    // Get ticket statistics from tickets table
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tickets,
            SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_tickets,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets
        FROM tickets 
        WHERE event_id = ?
    ");
    $stmt->execute([$event_id]);
    $ticket_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get payment statistics from payments table
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_payments,
            SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_payments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_payments
        FROM payments 
        WHERE event_id = ?
    ");
    $stmt->execute([$event_id]);
    $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate total revenue (tickets sold * ticket price)
    $total_revenue = ($ticket_stats['total_tickets'] - $ticket_stats['cancelled_tickets']) * $event['ticket_price'];

    // Get all tickets with user information and payment details
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            u.fullname, 
            u.email,
            p.id as payment_id,
            p.transaction_id,
            p.mpesa_receipt_number,
            p.purchase_date,
            p.status as payment_status,
            pt.id as payment_ticket_id
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN payment_tickets pt ON t.id = pt.ticket_id
        LEFT JOIN payments p ON pt.payment_id = p.id
        WHERE t.event_id = ?
        ORDER BY t.purchase_date DESC
    ");
    $stmt->execute([$event_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error: ' . $e->getMessage(), 'danger');
    $event = null;
    $tickets = [];
    $ticket_stats = ['total_tickets' => 0, 'active_tickets' => 0, 'used_tickets' => 0, 'cancelled_tickets' => 0];
    $payment_stats = ['total_payments' => 0, 'active_payments' => 0, 'used_payments' => 0, 'cancelled_payments' => 0];
    $total_revenue = 0;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    .ticket-management-header {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(102, 126, 234, 0.2);
        backdrop-filter: blur(10px);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    }
    
    .stat-card {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        position: relative;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
    
    .stat-card:hover::before {
        opacity: 1;
    }
    
    .revenue-card {
        background: var(--primary-gradient);
        color: white;
    }
    
    .bg-primary-gradient {
        background: var(--primary-gradient);
    }
    
    .bg-success-gradient {
        background: var(--success-gradient);
    }
    
    .bg-warning-gradient {
        background: var(--warning-gradient);
    }
    
    .bg-info-gradient {
        background: var(--info-gradient);
    }
    
    .bg-danger-gradient {
        background: var(--danger-gradient);
    }
    
    .ticket-table {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }
    
    .ticket-code {
        font-family: 'Courier New', monospace;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        font-weight: bold;
        font-size: 0.85rem;
        letter-spacing: 1px;
        display: inline-block;
    }
    
    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 1.5rem;
    }
    
    .download-section {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }
    
    .download-btn {
        border-radius: 12px;
        padding: 1rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .download-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .download-btn:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .download-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    
    .download-btn i {
        font-size: 1.2rem;
    }
    
    .download-btn-csv {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .download-btn-excel {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
    }
    
    .download-btn-pdf {
        background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        color: white;
    }
    
    .table-hover tbody tr {
        transition: all 0.3s ease;
    }
    
    .table-hover tbody tr:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        transform: scale(1.01);
    }
    
    .btn-group-sm .btn {
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-group-sm .btn:hover {
        transform: scale(1.1);
    }
    
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .card-header {
        border-bottom: 3px solid #f0f0f0;
        padding: 1.5rem;
    }
    
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }
    
    @media (max-width: 768px) {
        .ticket-management-header {
            padding: 1.5rem 1rem;
            border-radius: 16px;
        }
        
        .download-btn {
            width: 100%;
            justify-content: center;
        }
        
        .table-responsive {
            font-size: 0.875rem;
        }
        
        .stat-card {
            margin-bottom: 1rem;
        }
    }

    /* Loading overlay for downloads */
    .download-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .download-overlay.active {
        display: flex;
    }

    .download-spinner {
        text-align: center;
        color: white;
    }

    .download-spinner i {
        font-size: 3rem;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="download-overlay" id="downloadOverlay">
    <div class="download-spinner">
        <i class="fas fa-spinner"></i>
        <p class="mt-3">Preparing your download...</p>
    </div>
</div>

<div class="container mt-4 mb-5">
    <?php if ($event): ?>
        <!-- Header Section -->
        <div class="ticket-management-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-2" style="color: #667eea; font-weight: 700;">
                        <i class="fas fa-chart-bar me-2"></i>Ticket Management Dashboard
                    </h1>
                    <h2 class="h4 mb-3" style="color: #764ba2; font-weight: 600;"><?php echo htmlspecialchars($event['title']); ?></h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?>
                        &nbsp;|&nbsp;
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo htmlspecialchars($event['location']); ?>
                        &nbsp;|&nbsp;
                        <i class="fas fa-ticket-alt me-2"></i>
                        KSh <?php echo number_format($event['ticket_price'], 2); ?> per ticket
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="my_events.php" class="btn btn-outline-primary me-2" style="border-radius: 12px;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                    <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-primary" style="border-radius: 12px; background: var(--primary-gradient); border: none;">
                        <i class="fas fa-eye me-2"></i>View Event
                    </a>
                </div>
            </div>
        </div>

        <?php echo $FlashMessageObject->getMsg('msg'); ?>

        <!-- Revenue Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card stat-card revenue-card">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="text-white-50 mb-2" style="letter-spacing: 1px;">TOTAL REVENUE</h6>
                                <h2 class="mb-2" style="font-weight: 700; font-size: 2.5rem;">KSh <?php echo number_format($total_revenue, 2); ?></h2>
                                <p class="mb-0 opacity-75" style="font-size: 1.1rem;">
                                    <?php echo $ticket_stats['total_tickets'] - $ticket_stats['cancelled_tickets']; ?> tickets sold × KSh <?php echo number_format($event['ticket_price'], 2); ?> each
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <i class="fas fa-chart-line fa-4x opacity-50 pulse-animation"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-primary-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">TOTAL TICKETS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $ticket_stats['total_tickets']; ?></h3>
                            </div>
                            <i class="fas fa-ticket-alt fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-success-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">ACTIVE TICKETS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $ticket_stats['active_tickets']; ?></h3>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-warning-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">USED TICKETS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $ticket_stats['used_tickets']; ?></h3>
                            </div>
                            <i class="fas fa-user-check fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-danger-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">CANCELLED</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $ticket_stats['cancelled_tickets']; ?></h3>
                            </div>
                            <i class="fas fa-times-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-info-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">TOTAL PAYMENTS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $payment_stats['total_payments']; ?></h3>
                            </div>
                            <i class="fas fa-credit-card fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-success-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">ACTIVE PAYMENTS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $payment_stats['active_payments']; ?></h3>
                            </div>
                            <i class="fas fa-check fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-warning-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">USED PAYMENTS</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $payment_stats['used_payments']; ?></h3>
                            </div>
                            <i class="fas fa-receipt fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-danger-gradient">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1" style="letter-spacing: 1px; font-size: 0.75rem;">CANCELLED</h6>
                                <h3 class="mb-0" style="font-weight: 700;"><?php echo $payment_stats['cancelled_payments']; ?></h3>
                            </div>
                            <i class="fas fa-ban fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets List -->
        <div class="card ticket-table mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0" style="color: #667eea; font-weight: 600;">
                    <i class="fas fa-list me-2"></i>Ticket Details
                </h5>
                <span class="badge" style="background: var(--primary-gradient); font-size: 0.9rem;">
                    <?php echo count($tickets); ?> tickets
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h4 style="color: #667eea; font-weight: 600;">No Tickets Sold Yet</h4>
                        <p class="text-muted mb-4">Tickets will appear here once attendees start purchasing.</p>
                        <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg" style="border-radius: 12px; background: var(--primary-gradient); border: none;">
                            <i class="fas fa-share me-2"></i>Share Your Event
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="font-weight: 600; color: #667eea;">Ticket Code</th>
                                    <th style="font-weight: 600; color: #667eea;">Attendee</th>
                                    <th style="font-weight: 600; color: #667eea;">Ticket Status</th>
                                    <th style="font-weight: 600; color: #667eea;">Amount</th>
                                    <th style="font-weight: 600; color: #667eea;">Purchase Date</th>
                                    <th style="font-weight: 600; color: #667eea;">Payment Status</th>
                                    <th style="font-weight: 600; color: #667eea;">Transaction ID</th>
                                    <th style="font-weight: 600; color: #667eea;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-code"><?php echo htmlspecialchars($ticket['ticket_code']); ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($ticket['fullname'] ?? 'Guest'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ticket['email'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $ticket['status'] == 'active' ? 'success' : 
                                                     ($ticket['status'] == 'used' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($ticket['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>KSh <?php echo number_format($event['ticket_price'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y g:i A', strtotime($ticket['purchase_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $ticket['payment_status'] == 'active' ? 'success' : 
                                                     ($ticket['payment_status'] == 'used' ? 'warning' : 
                                                     ($ticket['payment_status'] == 'cancelled' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($ticket['payment_status'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($ticket['transaction_id'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewTicket('<?php echo $ticket['ticket_code']; ?>')"
                                                        title="View Ticket">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($ticket['status'] == 'active'): ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="markAsUsed(<?php echo $ticket['id']; ?>)"
                                                            title="Mark as Used">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($ticket['status'] == 'used'): ?>
                                                    <button class="btn btn-outline-warning" 
                                                            onclick="markAsActive(<?php echo $ticket['id']; ?>)"
                                                            title="Mark as Active">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($ticket['status'] == 'active'): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="cancelTicket(<?php echo $ticket['id']; ?>)"
                                                            title="Cancel Ticket">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Download Section -->
        <div class="download-section">
            <div class="row align-items-center">
                <div class="col-md-12 mb-3">
                    <h5 class="mb-2" style="color: #667eea; font-weight: 600;">
                        <i class="fas fa-download me-2"></i>Download Reports
                    </h5>
                    <p class="text-muted mb-0">Export your ticket data in various formats for analysis and record-keeping</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <button onclick="downloadReport('csv')" class="download-btn download-btn-csv w-100">
                        <i class="fas fa-file-csv"></i>
                        <span>Download as CSV</span>
                    </button>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <button onclick="downloadReport('excel')" class="download-btn download-btn-excel w-100">
                        <i class="fas fa-file-excel"></i>
                        <span>Download as Excel</span>
                    </button>
                </div>
                <div class="col-md-4">
                    <button onclick="downloadReport('pdf')" class="download-btn download-btn-pdf w-100">
                        <i class="fas fa-file-pdf"></i>
                        <span>Download as PDF</span>
                    </button>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-danger text-center" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
            <i class="fas fa-exclamation-triangle me-2" style="font-size: 2rem;"></i>
            <h5 class="mt-3">Event not found or you don't have permission to manage tickets for this event.</h5>
            <div class="mt-3">
                <a href="my_events.php" class="btn btn-primary btn-lg" style="border-radius: 12px; background: var(--primary-gradient); border: none;">
                    <i class="fas fa-arrow-left me-2"></i>Back to My Events
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function viewTicket(ticketCode) {
    window.open('view_ticket.php?ticket_code=' + ticketCode, '_blank', 'width=600,height=700');
}

function markAsUsed(ticketId) {
    if (confirm('Are you sure you want to mark this ticket as used? This action cannot be undone.')) {
        window.location.href = 'event_actions.php?action=mark_ticket_used&ticket_id=' + ticketId + '&event_id=<?php echo $event_id; ?>';
    }
}

function markAsActive(ticketId) {
    if (confirm('Are you sure you want to mark this ticket as active again?')) {
        window.location.href = 'event_actions.php?action=mark_ticket_active&ticket_id=' + ticketId + '&event_id=<?php echo $event_id; ?>';
    }
}

function cancelTicket(ticketId) {
    if (confirm('Are you sure you want to cancel this ticket? This will refund the payment if applicable.')) {
        window.location.href = 'event_actions.php?action=cancel_ticket&ticket_id=' + ticketId + '&event_id=<?php echo $event_id; ?>';
    }
}

function downloadReport(format) {
    const overlay = document.getElementById('downloadOverlay');
    overlay.classList.add('active');
    
    // Create a temporary form to submit the download request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_tickets.php';
    form.style.display = 'none';
    
    const eventIdInput = document.createElement('input');
    eventIdInput.type = 'hidden';
    eventIdInput.name = 'event_id';
    eventIdInput.value = '<?php echo $event_id; ?>';
    
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    
    form.appendChild(eventIdInput);
    form.appendChild(formatInput);
    document.body.appendChild(form);
    
    form.submit();
    
    // Hide overlay after 2 seconds
    setTimeout(function() {
        overlay.classList.remove('active');
        document.body.removeChild(form);
    }, 2000);
}

// Auto-refresh page every 30 seconds to get latest ticket data
setTimeout(function() {
    window.location.reload();
}, 30000);
</script>

<?php
$LayoutObject->footer($conf);
?>