<?php
require 'ClassAutoLoad.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$payment_id = (int)($_POST['payment_id'] ?? $_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment_id']);
    exit;
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    if ($payment['status'] === 'completed') {
        // Already completed -> return first ticket
        $stmt = $pdo->prepare("SELECT t.id FROM tickets t JOIN payment_tickets pt ON t.id = pt.ticket_id WHERE pt.payment_id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $ticket_id = (int)$stmt->fetch(PDO::FETCH_COLUMN);
        echo json_encode(['status' => 'completed', 'ticket_id' => $ticket_id]);
        exit;
    }

    if (empty($payment['transaction_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No CheckoutRequestID available for this payment']);
        exit;
    }

    require_once __DIR__ . '/mpesa_integration/MpesaService.php';
    $mpesa = new MpesaService();
    $resp = $mpesa->queryStkStatus($payment['transaction_id']);

    $resultCode = (int)($resp['ResultCode'] ?? 1);
    $resultDesc = (string)($resp['ResultDesc'] ?? '');

    if ($resultCode !== 0) {
        echo json_encode(['status' => 'pending', 'message' => $resultDesc]);
        exit;
    }

    // Mark completed and issue tickets (no receipt from query; keep null if unknown)
    $pdo->beginTransaction();

    // Re-fetch FOR UPDATE to avoid race
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
    $stmt->execute([$payment_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) { $pdo->rollBack(); throw new RuntimeException('Payment missing'); }
    if ($p['status'] === 'completed') {
        $pdo->commit();
        $stmt = $pdo->prepare("SELECT t.id FROM tickets t JOIN payment_tickets pt ON t.id = pt.ticket_id WHERE pt.payment_id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $ticket_id = (int)$stmt->fetch(PDO::FETCH_COLUMN);
        echo json_encode(['status' => 'completed', 'ticket_id' => $ticket_id]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$payment_id]);

    // Create tickets and link
    $ticketIds = [];
    for ($i = 0; $i < (int)$p['quantity']; $i++) {
        $ticket_code = 'TICKET_' . strtoupper(uniqid()) . '_' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO tickets (event_id, user_id, ticket_code, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([(int)$p['event_id'], (int)$p['user_id'], $ticket_code]);
        $ticket_id = (int)$pdo->lastInsertId();
        $ticketIds[] = $ticket_id;
        $stmt = $pdo->prepare("INSERT INTO payment_tickets (payment_id, ticket_id) VALUES (?, ?)");
        $stmt->execute([$payment_id, $ticket_id]);
    }

    // Decrement available tickets and mark attendee
    $stmt = $pdo->prepare("UPDATE events SET available_tickets = GREATEST(0, available_tickets - ?) WHERE id = ?");
    $stmt->execute([(int)$p['quantity'], (int)$p['event_id']]);
    $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) VALUES (?, ?, 'going', 1, ?) ON DUPLICATE KEY UPDATE status = 'going', quantity = quantity + VALUES(quantity)");
    $stmt->execute([(int)$p['event_id'], (int)$p['user_id'], (int)$p['quantity']]);

    $pdo->commit();

    echo json_encode(['status' => 'completed', 'ticket_id' => $ticketIds[0] ?? null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

?>

