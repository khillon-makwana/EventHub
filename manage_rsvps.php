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
        die("You’re not authorized to manage this event.");
    }

    // Fetch attendees 
    $stmt = $pdo->prepare("
        SELECT ea.id, u.fullname, u.email, ea.status, ea.registered_at
        FROM event_attendees ea
        JOIN users u ON ea.user_id = u.id
        WHERE ea.event_id = ?
        ORDER BY ea.registered_at DESC
    ");
    $stmt->execute([$eventId]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary counts
    $summary = [
        'total' => 0,
        'going' => 0,
        'interested' => 0,
        'not_going' => 0,
    ];

    foreach ($attendees as $a) {
        $summary['total']++;
        $status = strtolower($a['status']);
        if ($status === 'going') $summary['going']++;
        elseif ($status === 'interested') $summary['interested']++;
        elseif ($status === 'not going' || $status === 'not_going') $summary['not_going']++;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage RSVPs - <?= htmlspecialchars($event['title']); ?></title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 40px; }
        .summary-card {
            background: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-item h5 {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center mb-4">Manage RSVPs for: <?= htmlspecialchars($event['title']); ?></h2>

    <?php if (empty($attendees)): ?>
        <div class="alert alert-info text-center">No attendees have RSVP’d yet.</div>
    <?php else: ?>

        <!-- Summary Section -->
        <div class="summary-card">
            <div class="row">
                <div class="col-md-3 summary-item">
                    <h5>Total RSVPs</h5>
                    <p class="fw-bold text-primary"><?= $summary['total']; ?></p>
                </div>
                <div class="col-md-3 summary-item">
                    <h5>Going</h5>
                    <p class="fw-bold text-success"><?= $summary['going']; ?></p>
                </div>
                <div class="col-md-3 summary-item">
                    <h5>Interested</h5>
                    <p class="fw-bold text-warning"><?= $summary['interested']; ?></p>
                </div>
                <div class="col-md-3 summary-item">
                    <h5>Not Going</h5>
                    <p class="fw-bold text-danger"><?= $summary['not_going']; ?></p>
                </div>
            </div>
        </div>

        <!-- 🔹 RSVPs Table -->
        <table id="rsvpTable" class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($attendees as $attendee): ?>
                <tr>
                    <td><?= htmlspecialchars($attendee['fullname']); ?></td>
                    <td><?= htmlspecialchars($attendee['email']); ?></td>
                    <td><?= htmlspecialchars(ucfirst($attendee['status'])); ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($attendee['registered_at'])); ?></td>
                    <td>
                        <a href="edit_rsvp.php?id=<?= $attendee['id']; ?>&event_id=<?= $eventId; ?>" 
                           class="btn btn-sm btn-warning">Edit</a>
                        <a href="delete_rsvp.php?id=<?= $attendee['id']; ?>&event_id=<?= $eventId; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Remove this attendee?')">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="my_events.php" class="btn btn-secondary">Back to My Events</a>
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
            { extend: 'copy', className: 'btn btn-secondary', exportOptions: { columns: ':not(:last-child)'} },
            { extend: 'csv', className: 'btn btn-primary', exportOptions: { columns: ':not(:last-child)'} },
            { extend: 'excel', className: 'btn btn-success', exportOptions: { columns: ':not(:last-child)'} },
            { extend: 'pdf', className: 'btn btn-danger', exportOptions: { columns: ':not(:last-child)'} },
            { extend: 'print', className: 'btn btn-dark', exportOptions: { columns: ':not(:last-child)'} }
        ],
        pageLength: 10,
        order: [[3, 'desc']], // Sort by Registered Date
        columnDefs: [{ orderable: false, targets: 4 }]
    });
});
</script>
</body>
</html>
