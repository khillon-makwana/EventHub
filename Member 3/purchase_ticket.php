<?php
// Start session to get user_id
session_start();

// Database connection
$pdo = new PDO('mysql:host=localhost;dbname=eventhub', 'root', 'Dicy');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if form is submitted
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $eventId = $_POST['eventId'] ?? null;
    $userId = $_POST['userId'] ?? null;
    $ticketId = $_POST['ticketId'] ?? null;
    $quantity = $_POST['quantity'] ?? null;

    // Validate input
    if (empty($eventId) || empty($userId) || empty($ticketId) || empty($quantity)) {
        echo "All fields are required.";
        exit;
    }

    if (!is_numeric($eventId) || !is_numeric($userId) || !is_numeric($ticketId) || !is_numeric($quantity)) {
        echo "Event ID, User ID, Ticket ID, and Quantity must be numbers.";
        exit;
    }

    if ($quantity <= 0) {
        echo "Quantity must be greater than zero.";
        exit;
    }

    // Check if enough tickets are available
    $stmt = $pdo->prepare("SELECT remaining, price FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket || $ticket['remaining'] < $quantity) {
        echo "Not enough tickets available.";
        exit;
    }

    // Update ticket remaining quantity
    $stmt = $pdo->prepare("UPDATE tickets SET remaining = remaining - ? WHERE id = ?");
    $stmt->execute([$quantity, $ticketId]);

    // Create purchase record
    $stmt = $pdo->prepare("INSERT INTO purchases (user_id, ticket_id, quantity, total_amount, payment_status) VALUES (?, ?, ?, ?, ?)");
    $total_amount = $quantity * $ticket['price'];
    $stmt->execute([$userId, $ticketId, $quantity, $total_amount, 'pending']);

    echo "Purchase successful.";
} else {
    echo "Invalid request method.";
}
?>


