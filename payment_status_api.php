<?php
require 'ClassAutoLoad.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$payment_id = (int)($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment_id']);
    exit;
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT status, event_id FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    $status = $row['status'];
    $resp = ['status' => $status];

    if ($status === 'completed') {
        // Get first ticket for this payment
        $stmt = $pdo->prepare("SELECT t.id FROM tickets t JOIN payment_tickets pt ON t.id = pt.ticket_id WHERE pt.payment_id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $ticket_id = (int)$stmt->fetch(PDO::FETCH_COLUMN);
        if ($ticket_id) { $resp['ticket_id'] = $ticket_id; }
    } elseif ($status === 'failed') {
        $resp['event_id'] = (int)$row['event_id'];
    }

    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

?>

