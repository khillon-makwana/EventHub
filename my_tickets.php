<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Fetch user's tickets
try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title, e.event_date, e.location, e.image,
               p.transaction_id, p.purchase_date
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN payments p ON t.id = p.ticket_id
        WHERE t.user_id = ?
        ORDER BY t.purchase_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error loading tickets: ' . $e->getMessage(), 'danger');
    $tickets = [];
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-4">
    <h2 class="mb-4">My Tickets</h2>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <?php if (empty($tickets)): ?>
        <div class="text-center py-5">
            <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No Tickets Yet</h4>
            <p class="text-muted">You haven't purchased any tickets yet.</p>
            <a href="all_events.php" class="btn btn-primary">Browse Events</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($tickets as $ticket): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title"><?php echo htmlspecialchars($ticket['event_title']); ?></h5>
                                <span class="badge bg-<?php echo $ticket['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </div>
                            
                            <p class="card-text">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo date('F j, Y g:i A', strtotime($ticket['event_date'])); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($ticket['location']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Ticket Code: <?php echo htmlspecialchars($ticket['ticket_code']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-receipt me-2"></i>
                                Transaction: <?php echo htmlspecialchars($ticket['transaction_id']); ?>
                            </p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Purchased: <?php echo date('M j, Y g:i A', strtotime($ticket['purchase_date'])); ?>
                                </small>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-grid gap-2">
                                <a href="view_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-primary">
                                    View Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$LayoutObject->footer($conf);
?>
