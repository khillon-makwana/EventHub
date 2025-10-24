<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Handle ticket deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tickets'])) {
    try {
        $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (isset($_POST['delete_all'])) {
            // Delete all tickets for the user
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $count = $stmt->rowCount();
            $FlashMessageObject->setMsg('msg', "Successfully deleted {$count} ticket(s)", 'success');
        } elseif (isset($_POST['ticket_ids']) && is_array($_POST['ticket_ids'])) {
            // Delete selected tickets
            $placeholders = implode(',', array_fill(0, count($_POST['ticket_ids']), '?'));
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE id IN ($placeholders) AND user_id = ?");
            $params = array_merge($_POST['ticket_ids'], [$_SESSION['user_id']]);
            $stmt->execute($params);
            $count = $stmt->rowCount();
            $FlashMessageObject->setMsg('msg', "Successfully deleted {$count} ticket(s)", 'success');
        } else {
            $FlashMessageObject->setMsg('msg', 'No tickets selected for deletion', 'warning');
        }
        
        header("Location: my_tickets.php");
        exit;
    } catch (PDOException $e) {
        $FlashMessageObject->setMsg('msg', 'Error deleting tickets: ' . $e->getMessage(), 'danger');
    }
}

// Fetch user's tickets
try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check for pending payments
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, e.title as event_title
        FROM payments p
        JOIN events e ON p.event_id = e.id
        WHERE p.user_id = ? AND p.status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_payment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user's tickets
    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title, e.event_date, e.location, e.image,
               p.transaction_id, p.purchase_date, p.amount, p.mpesa_receipt_number,
               p.status as payment_status, p.quantity
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN payment_tickets pt ON t.id = pt.ticket_id
        LEFT JOIN payments p ON pt.payment_id = p.id
        WHERE t.user_id = ?
        ORDER BY t.purchase_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error loading tickets: ' . $e->getMessage(), 'danger');
    $tickets = [];
    $pending_payment = null;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.page-header {
    background: var(--primary-gradient);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 30px 30px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.page-header h2 {
    font-weight: 700;
    font-size: 2.5rem;
    margin: 0;
}

.ticket-checkbox {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 10;
}

.ticket-checkbox input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.ticket-checkbox input[type="checkbox"]:checked {
    background: var(--primary-gradient);
    border-color: #667eea;
}

.ticket-card {
    position: relative;
    border: none;
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.ticket-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
}

.ticket-card.selected {
    border: 2px solid #667eea;
    box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
}

.ticket-card .card-body {
    padding: 2rem;
    padding-left: 60px;
}

.ticket-header {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 1rem 2rem 1rem 60px;
    border-bottom: 3px solid #667eea;
}

.ticket-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}

.status-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.badge-modern {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.badge-active {
    background: var(--success-gradient);
    color: white;
}

.badge-used {
    background: var(--warning-gradient);
    color: white;
}

.badge-completed {
    background: var(--success-gradient);
    color: white;
}

.badge-pending {
    background: var(--warning-gradient);
    color: white;
}

.ticket-info {
    margin: 1.5rem 0;
}

.info-row {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.3s ease;
}

.info-row:hover {
    background: #f8f9fa;
    padding-left: 0.5rem;
    margin-left: -0.5rem;
    border-radius: 8px;
}

.info-row:last-child {
    border-bottom: none;
}

.info-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-right: 1rem;
    font-size: 1.1rem;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.8rem;
    color: #718096;
    margin-bottom: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1rem;
    color: #2d3748;
    font-weight: 600;
}

.ticket-code {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    letter-spacing: 1px;
}

.card-footer {
    background: transparent;
    border-top: none;
    padding: 0 2rem 2rem 60px;
}

.btn-view-ticket {
    background: var(--primary-gradient);
    border: none;
    border-radius: 12px;
    padding: 0.8rem 1.5rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-view-ticket:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.delete-actions {
    position: sticky;
    top: 20px;
    z-index: 100;
    margin-bottom: 2rem;
}

.delete-actions .card {
    border: none;
    border-radius: 15px;
    background: white;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
}

.delete-actions .card-body {
    padding: 1.2rem 1.5rem;
}

.select-all-label {
    font-weight: 600;
    color: #2d3748;
    font-size: 1.05rem;
}

#selectAll {
    width: 22px;
    height: 22px;
    cursor: pointer;
}

.selected-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.3rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.btn-delete-selected {
    background: var(--danger-gradient);
    border: none;
    border-radius: 10px;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
}

.btn-delete-selected:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(235, 51, 73, 0.3);
}

.btn-delete-selected:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-delete-all {
    border: 2px solid #eb3349;
    color: #eb3349;
    border-radius: 10px;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    background: white;
    transition: all 0.3s ease;
}

.btn-delete-all:hover {
    background: var(--danger-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

.pending-payment-alert {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
    border-radius: 15px;
    padding: 1.5rem;
    color: white;
    box-shadow: 0 5px 20px rgba(240, 147, 251, 0.3);
    margin-bottom: 2rem;
}

.pending-payment-alert h5 {
    color: white;
    font-weight: 700;
}

.pending-payment-alert .alert-link {
    color: white;
    text-decoration: underline;
    font-weight: 700;
}

.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
}

.empty-state i {
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.btn-browse-events {
    background: var(--primary-gradient);
    border: none;
    border-radius: 12px;
    padding: 1rem 2.5rem;
    font-weight: 600;
    color: white;
    font-size: 1.1rem;
    margin-top: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
}

.btn-browse-events:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
    color: white;
}

@media (max-width: 768px) {
    .page-header h2 {
        font-size: 2rem;
    }
    
    .delete-actions .card-body {
        padding: 1rem;
    }
    
    .delete-actions .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-ticket-alt me-3"></i>My Tickets</h2>
        </div>
    </div>
</div>

<div class="container">
    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <?php if ($pending_payment): ?>
    <div class="alert pending-payment-alert">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Pending Payment</h5>
        <p class="mb-0">
            You have a pending payment of <strong>KSh <?php echo number_format($pending_payment['amount'], 2); ?></strong> 
            for <strong><?php echo htmlspecialchars($pending_payment['event_title']); ?></strong>.
            <a href="process_payment.php?payment_id=<?php echo $pending_payment['id']; ?>" class="alert-link">
                Complete your payment to get your tickets.
            </a>
        </p>
    </div>
    <?php endif; ?>

    <?php if (empty($tickets)): ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt fa-5x mb-4"></i>
            <h3 class="fw-bold mb-3">No Tickets Yet</h3>
            <p class="text-muted fs-5">You haven't purchased any tickets yet. Start exploring amazing events!</p>
            <a href="all_events.php" class="btn btn-browse-events">
                <i class="fas fa-calendar-alt me-2"></i>Browse Events
            </a>
        </div>
    <?php else: ?>
        <form method="POST" id="deleteForm">
            <!-- Delete Actions Sticky Bar -->
            <div class="delete-actions">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="form-check">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                    <label for="selectAll" class="form-check-label select-all-label">Select All</label>
                                </div>
                                <span class="selected-count" id="selectedCount">0 selected</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-delete-selected" id="deleteSelectedBtn" disabled>
                                    <i class="fas fa-trash me-2"></i>Delete Selected
                                </button>
                                <button type="button" class="btn btn-delete-all" id="deleteAllBtn">
                                    <i class="fas fa-trash-alt me-2"></i>Delete All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100 ticket-card" data-ticket-id="<?php echo $ticket['id']; ?>">
                            <div class="ticket-checkbox">
                                <input type="checkbox" name="ticket_ids[]" value="<?php echo $ticket['id']; ?>" 
                                       class="form-check-input ticket-select">
                            </div>
                            
                            <div class="ticket-header">
                                <h5 class="ticket-title"><?php echo htmlspecialchars($ticket['event_title']); ?></h5>
                                <div class="status-badges">
                                    <span class="badge badge-modern badge-<?php echo $ticket['status'] == 'active' ? 'active' : 'used'; ?>">
                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                    <span class="badge badge-modern badge-<?php echo $ticket['payment_status'] == 'completed' ? 'completed' : 'pending'; ?>">
                                        <i class="fas fa-<?php echo $ticket['payment_status'] == 'completed' ? 'check' : 'clock'; ?> me-1"></i>
                                        <?php echo ucfirst($ticket['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="ticket-info">
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Event Date</div>
                                            <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($ticket['event_date'])); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Location</div>
                                            <div class="info-value"><?php echo htmlspecialchars($ticket['location']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-ticket-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Ticket Code</div>
                                            <div class="info-value">
                                                <span class="ticket-code"><?php echo htmlspecialchars($ticket['ticket_code']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($ticket['amount']): ?>
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Amount Paid</div>
                                            <div class="info-value">KSh <?php echo number_format($ticket['amount'], 2); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-row">
                                        <div class="info-icon">
                                            <i class="fas fa-receipt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Transaction ID</div>
                                            <div class="info-value"><?php echo htmlspecialchars($ticket['transaction_id'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-muted mt-3" style="font-size: 0.85rem;">
                                    <i class="fas fa-clock me-2"></i>Purchased on <?php echo date('M j, Y g:i A', strtotime($ticket['purchase_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="d-grid">
                                    <a href="view_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-view-ticket">
                                        <i class="fas fa-eye me-2"></i>View Full Ticket
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="delete_tickets" value="1">
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const ticketCheckboxes = document.querySelectorAll('.ticket-select');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    const deleteForm = document.getElementById('deleteForm');

    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.ticket-select:checked').length;
        selectedCountSpan.textContent = `${checkedCount} selected`;
        deleteSelectedBtn.disabled = checkedCount === 0;
        
        // Add visual feedback to selected cards
        ticketCheckboxes.forEach(checkbox => {
            const card = checkbox.closest('.ticket-card');
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    // Select/deselect all
    selectAllCheckbox.addEventListener('change', function() {
        ticketCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

    // Update count when individual checkboxes change
    ticketCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            
            // Update "Select All" state
            const allChecked = Array.from(ticketCheckboxes).every(cb => cb.checked);
            const noneChecked = Array.from(ticketCheckboxes).every(cb => !cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
        });
    });

    // Delete selected tickets
    deleteSelectedBtn.addEventListener('click', function() {
        const checkedCount = document.querySelectorAll('.ticket-select:checked').length;
        if (checkedCount === 0) return;

        if (confirm(`Are you sure you want to delete ${checkedCount} ticket(s)? This action cannot be undone.`)) {
            deleteForm.submit();
        }
    });

    // Delete all tickets
    deleteAllBtn.addEventListener('click', function() {
        const totalTickets = ticketCheckboxes.length;
        if (confirm(`Are you sure you want to delete ALL ${totalTickets} ticket(s)? This action cannot be undone.`)) {
            const deleteAllInput = document.createElement('input');
            deleteAllInput.type = 'hidden';
            deleteAllInput.name = 'delete_all';
            deleteAllInput.value = '1';
            deleteForm.appendChild(deleteAllInput);
            deleteForm.submit();
        }
    });
});
</script>

<?php
$LayoutObject->footer($conf);
?>