<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$eventId = $_GET['event_id'] ?? null;
if (!$eventId) {
    die("Invalid request. Event ID missing.");
}

try {
    // Connect to DB
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user is the event creator
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$eventId, $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        die("You're not authorized to manage this event.");
    }

    // Fetch attendees WITH QUANTITY
    $stmt = $pdo->prepare("
        SELECT ea.id, u.fullname, u.email, ea.status, ea.quantity, ea.registered_at
        FROM event_attendees ea
        JOIN users u ON ea.user_id = u.id
        WHERE ea.event_id = ?
        ORDER BY ea.registered_at DESC
    ");
    $stmt->execute([$eventId]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary counts - UPDATED TO USE QUANTITY
    $summary = [
        'total' => 0,
        'going' => 0,
        'interested' => 0,
        'not_going' => 0,
    ];

    foreach ($attendees as $a) {
        $summary['total']++;
        $status = strtolower($a['status']);
        $quantity = $a['quantity'] ?? 1; // Default to 1 if quantity doesn't exist
        
        if ($status === 'going') {
            $summary['going'] += $quantity; // Add the quantity for going attendees
        } elseif ($status === 'interested') {
            $summary['interested']++; // Interested are still counted as 1 per user
        } elseif ($status === 'not going' || $status === 'not_going') {
            $summary['not_going']++; // Not going are still counted as 1 per user
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage RSVPs - <?= htmlspecialchars($event['title']); ?></title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .page-wrapper {
            animation: fadeInUp 0.6s ease-out;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            animation: fadeInUp 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #667eea);
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }

        .page-header h2 {
            color: #2d3748;
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
        }

        .page-header .event-title {
            color: #667eea;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .summary-container {
            animation: fadeInUp 1s ease-out;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: none;
        }

        .summary-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.8s ease-out;
        }

        .summary-item:nth-child(1) { animation-delay: 0.1s; }
        .summary-item:nth-child(2) { animation-delay: 0.2s; }
        .summary-item:nth-child(3) { animation-delay: 0.3s; }
        .summary-item:nth-child(4) { animation-delay: 0.4s; }

        .summary-item:hover {
            transform: translateY(-5px);
        }

        .summary-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .summary-item:hover::before {
            opacity: 1;
        }

        .summary-item.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .summary-item.going {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .summary-item.interested {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .summary-item.not-going {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .summary-item h5 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            opacity: 0.95;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-item .count {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            animation: countUp 0.8s ease-out;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .summary-item .icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .table-wrapper {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            animation: fadeInUp 1.2s ease-out;
            overflow: hidden;
        }

        .dataTables_wrapper {
            padding: 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table tbody td {
            vertical-align: middle;
            padding: 1rem;
            font-size: 0.95rem;
        }

        .status-badge {
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }

        .status-going {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .status-interested {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .status-not-going {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn {
            border-radius: 10px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }

        .dt-buttons {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .dt-buttons .btn {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            animation: fadeInUp 0.8s ease-out;
            font-weight: 500;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .back-button-wrapper {
            text-align: center;
            margin-top: 2rem;
            animation: fadeInUp 1.4s ease-out;
        }

        .back-button-wrapper .btn {
            padding: 0.75rem 2rem;
            font-size: 1rem;
        }

        .dataTables_filter input {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .dataTables_filter input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            outline: none;
        }

        .dataTables_length select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            transition: all 0.3s ease;
        }

        .dataTables_length select:focus {
            border-color: #667eea;
            outline: none;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .page-link {
            color: #667eea;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin: 0 2px;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }

            .summary-item {
                margin-bottom: 1rem;
            }

            .summary-item .count {
                font-size: 2rem;
            }

            .table-wrapper {
                padding: 1rem;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            animation: fadeInUp 0.8s ease-out;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body>
<div class="container page-wrapper">
    <div class="page-header">
        <h2>📊 Manage RSVPs</h2>
        <div class="event-title">
            <?= htmlspecialchars($event['title']); ?>
        </div>
    </div>

    <?php if (empty($attendees)): ?>
        <div class="table-wrapper">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="alert alert-info d-inline-block">
                    <strong>No RSVPs Yet</strong><br>
                    No attendees have RSVP'd to this event. Share your event to get more attendees!
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Summary Section -->
        <div class="summary-container">
            <div class="summary-card">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-item total">
                            <span class="icon">👥</span>
                            <h5>Total RSVPs</h5>
                            <p class="count"><?= $summary['total']; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-item going">
                            <span class="icon">✓</span>
                            <h5>Going</h5>
                            <p class="count"><?= $summary['going']; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-item interested">
                            <span class="icon">⭐</span>
                            <h5>Interested</h5>
                            <p class="count"><?= $summary['interested']; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-item not-going">
                            <span class="icon">✗</span>
                            <h5>Not Going</h5>
                            <p class="count"><?= $summary['not_going']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RSVPs Table -->
        <div class="table-wrapper">
            <table id="rsvpTable" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>👤 Full Name</th>
                        <th>📧 Email</th>
                        <th>📍 Status</th>
                        <th>🗓️ Registered</th>
                        <th>⚙️ Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($attendees as $attendee): 
                    $statusClass = 'status-going';
                    $statusLower = strtolower($attendee['status']);
                    if ($statusLower === 'interested') $statusClass = 'status-interested';
                    elseif (strpos($statusLower, 'not') !== false) $statusClass = 'status-not-going';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($attendee['fullname']); ?></strong></td>
                        <td><?= htmlspecialchars($attendee['email']); ?></td>
                        <td>
                            <span class="status-badge <?= $statusClass; ?>">
                                <?= htmlspecialchars(ucfirst($attendee['status'])); ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y g:i A', strtotime($attendee['registered_at'])); ?></td>
                        <td>
                            <a href="edit_rsvp.php?id=<?= $attendee['id']; ?>&event_id=<?= $eventId; ?>" 
                               class="btn btn-sm btn-warning">✏️ Edit</a>
                            <a href="delete_rsvp.php?id=<?= $attendee['id']; ?>&event_id=<?= $eventId; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this attendee?')">🗑️ Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="back-button-wrapper">
        <a href="my_events.php" class="btn btn-secondary btn-lg">
            ← Back to My Events
        </a>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables Core + Bootstrap Integration -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons Extension -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    $('#rsvpTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            { 
                extend: 'copy', 
                className: 'btn btn-secondary btn-sm', 
                text: '📋 Copy',
                exportOptions: { columns: ':not(:last-child)'} 
            },
            { 
                extend: 'csv', 
                className: 'btn btn-primary btn-sm', 
                text: '📄 CSV',
                exportOptions: { columns: ':not(:last-child)'} 
            },
            { 
                extend: 'excel', 
                className: 'btn btn-success btn-sm', 
                text: '📊 Excel',
                exportOptions: { columns: ':not(:last-child)'} 
            },
            { 
                extend: 'pdf', 
                className: 'btn btn-danger btn-sm', 
                text: '📕 PDF',
                exportOptions: { columns: ':not(:last-child)'} 
            },
            { 
                extend: 'print', 
                className: 'btn btn-dark btn-sm', 
                text: '🖨️ Print',
                exportOptions: { columns: ':not(:last-child)'} 
            }
        ],
        pageLength: 10,
        order: [[3, 'desc']], // Sort by Registered Date
        columnDefs: [{ orderable: false, targets: 4 }],
        language: {
            search: "🔍 Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ attendees",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next →",
                previous: "← Previous"
            }
        }
    });
});
</script>
</body>
</html>