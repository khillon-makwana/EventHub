<?php
// Use project configuration and services to finalize payments and issue tickets
require_once dirname(__DIR__) . '/conf.php';

// Log raw callback for debugging
$raw = file_get_contents('php://input');
@file_put_contents(__DIR__ . '/callback_log.txt', date('c') . "\n" . $raw . "\n\n", FILE_APPEND);

$payload = json_decode($raw, true);
if (!isset($payload['Body']['stkCallback'])) {
    http_response_code(400);
    echo json_encode(['status' => 'invalid', 'message' => 'Missing stkCallback']);
    exit;
}

$cb = $payload['Body']['stkCallback'];
$resultCode = (int)($cb['ResultCode'] ?? 1);
$resultDesc = (string)($cb['ResultDesc'] ?? '');
$checkoutRequestId = (string)($cb['CheckoutRequestID'] ?? '');
$merchantRequestId = (string)($cb['MerchantRequestID'] ?? '');

// Prefer payment_id passed in callback URL for exact mapping
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

// Extract metadata when available
$metaItems = $cb['CallbackMetadata']['Item'] ?? [];
$meta = [];
foreach ($metaItems as $item) {
    if (isset($item['Name'])) {
        $meta[$item['Name']] = $item['Value'] ?? null;
    }
}
$mpesaAmount = $meta['Amount'] ?? null;
$mpesaReceipt = $meta['MpesaReceiptNumber'] ?? null;
$mpesaPhone = $meta['PhoneNumber'] ?? null;
$mpesaTime = $meta['TransactionDate'] ?? null;

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // If payment_id is not provided, look it up by CheckoutRequestID stored in transaction_id
    if ($payment_id <= 0 && $checkoutRequestId) {
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$checkoutRequestId]);
        $payment_id = (int)$stmt->fetchColumn();
    }

    if ($payment_id <= 0) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Payment not found']);
        exit;
    }

    if ($resultCode !== 0) {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$payment_id]);
        http_response_code(200);
        echo json_encode(['status' => 'failed', 'message' => $resultDesc]);
        exit;
    }

    // Success path: issue tickets and finalize payment
    $pdo->beginTransaction();

    // Fetch payment row
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        $pdo->rollBack();
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Payment row missing']);
        exit;
    }

    // Avoid double processing
    if (($payment['status'] ?? '') === 'completed') {
        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Already completed']);
        exit;
    }

    // Update payment details (set receipt, phone, transaction id if missing)
    $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', mpesa_receipt_number = ?, phone_number = COALESCE(?, phone_number), transaction_id = COALESCE(transaction_id, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$mpesaReceipt, $mpesaPhone, $checkoutRequestId, $payment_id]);

    // Create tickets and link to payment
    $ticketIds = [];
    for ($i = 0; $i < (int)$payment['quantity']; $i++) {
        $ticket_code = 'TICKET_' . strtoupper(uniqid()) . '_' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO tickets (event_id, user_id, ticket_code, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([(int)$payment['event_id'], (int)$payment['user_id'], $ticket_code]);
        $ticket_id = (int)$pdo->lastInsertId();
        $ticketIds[] = $ticket_id;

        $stmt = $pdo->prepare("INSERT INTO payment_tickets (payment_id, ticket_id) VALUES (?, ?)");
        $stmt->execute([$payment_id, $ticket_id]);
    }

    // Decrement available tickets
    $stmt = $pdo->prepare("UPDATE events SET available_tickets = GREATEST(0, available_tickets - ?) WHERE id = ?");
    $stmt->execute([(int)$payment['quantity'], (int)$payment['event_id']]);

    // Mark attendee and track quantity
    $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, user_id, status, category_id, quantity) VALUES (?, ?, 'going', 1, ?) ON DUPLICATE KEY UPDATE status = 'going', quantity = quantity + VALUES(quantity)");
    $stmt->execute([(int)$payment['event_id'], (int)$payment['user_id'], (int)$payment['quantity']]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'payment_id' => $payment_id, 'receipt' => $mpesaReceipt]);
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/callback_errors.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(200);
    echo json_encode(['status' => 'error']);
}

?>
