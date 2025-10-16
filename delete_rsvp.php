<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$id = $_GET['id'] ?? null;
$eventId = $_GET['event_id'] ?? null;

if (!$id || !$eventId) {
    die("Invalid request.");
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Only allow deletion if logged-in user owns the event
    $stmt = $pdo->prepare("DELETE ea FROM event_attendees ea
                           JOIN events e ON ea.event_id = e.id
                           WHERE ea.id = ? AND e.user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);

    header("Location: manage_rsvps.php?event_id=$eventId");
    exit;
} catch (PDOException $e) {
    die("Error deleting RSVP: " . $e->getMessage());
}
?>
