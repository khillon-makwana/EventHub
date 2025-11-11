<?php
session_start();
require_once 'conf.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    echo json_encode(['error'=>'You must be logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get event_id from GET
$eventId = isset($_GET['event_id']) ? $_GET['event_id'] : 'all';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']}",
        $conf['db_user'],
        $conf['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error'=>'DB Connection Failed: '.$e->getMessage()]);
    exit;
}

// Function to get metrics
function getEventMetrics($pdo, $userId, $eventId = 'all') {
    if ($eventId === 'all') {
        // All events metrics
        $totalTicketsSold = $pdo->prepare("
            SELECT IFNULL(SUM(quantity),0)
            FROM event_attendees ea
            JOIN events e ON ea.event_id = e.id
            WHERE e.user_id = ? AND ea.status='going'
        ");
        $totalTicketsSold->execute([$userId]);
        $totalTicketsSold = $totalTicketsSold->fetchColumn();

        $totalRevenue = $pdo->prepare("
            SELECT IFNULL(SUM(amount),0)
            FROM payments p
            JOIN events e ON p.event_id = e.id
            WHERE e.user_id = ? AND p.status='completed'
        ");
        $totalRevenue->execute([$userId]);
        $totalRevenue = $totalRevenue->fetchColumn();

        $averageRating = $pdo->prepare("
            SELECT IFNULL(ROUND(AVG(rating),1),0)
            FROM feedback f
            JOIN events e ON f.event_id = e.id
            WHERE e.user_id = ?
        ");
        $averageRating->execute([$userId]);
        $averageRating = $averageRating->fetchColumn();

        $totalAttendees = $pdo->prepare("
            SELECT COUNT(DISTINCT ea.user_id)
            FROM event_attendees ea
            JOIN events e ON ea.event_id = e.id
            WHERE e.user_id = ?
        ");
        $totalAttendees->execute([$userId]);
        $totalAttendees = $totalAttendees->fetchColumn();

        $rsvpData = $pdo->prepare("
            SELECT ea.status, COUNT(*) AS total
            FROM event_attendees ea
            JOIN events e ON ea.event_id = e.id
            WHERE e.user_id = ?
            GROUP BY ea.status
        ");
        $rsvpData->execute([$userId]);
        $rsvpData = $rsvpData->fetchAll(PDO::FETCH_ASSOC);

        $ratingDist = $pdo->prepare("
            SELECT f.rating, COUNT(*) AS total
            FROM feedback f
            JOIN events e ON f.event_id = e.id
            WHERE e.user_id = ?
            GROUP BY f.rating
            ORDER BY f.rating ASC
        ");
        $ratingDist->execute([$userId]);
        $ratingDist = $ratingDist->fetchAll(PDO::FETCH_ASSOC);

        $revenueOverTime = $pdo->prepare("
            SELECT DATE(p.created_at) AS date, SUM(p.amount) AS total
            FROM payments p
            JOIN events e ON p.event_id = e.id
            WHERE e.user_id = ? AND p.status='completed' AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(p.created_at)
            ORDER BY date ASC
        ");
        $revenueOverTime->execute([$userId]);
        $revenueOverTime = $revenueOverTime->fetchAll(PDO::FETCH_ASSOC);

        $ticketsSoldOverTime = $pdo->prepare("
            SELECT DATE(ea.registered_at) AS date, SUM(ea.quantity) AS total
            FROM event_attendees ea
            JOIN events e ON ea.event_id = e.id
            WHERE e.user_id = ? AND ea.status='going' AND ea.registered_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(ea.registered_at)
            ORDER BY date ASC
        ");
        $ticketsSoldOverTime->execute([$userId]);
        $ticketsSoldOverTime = $ticketsSoldOverTime->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Validate event_id is numeric
        if(!is_numeric($eventId)){
            return ['error'=>'Invalid event ID'];
        }

        // Check if user owns the event
        $check = $pdo->prepare("SELECT COUNT(*) FROM events WHERE id=? AND user_id=?");
        $check->execute([$eventId, $userId]);
        if($check->fetchColumn() == 0){
            return ['error'=>'Unauthorized or event does not exist'];
        }

        // Single event metrics
        $totalTicketsSold = $pdo->prepare("
            SELECT IFNULL(SUM(quantity),0) 
            FROM event_attendees 
            WHERE event_id=? AND status='going'
        ");
        $totalTicketsSold->execute([$eventId]);
        $totalTicketsSold = $totalTicketsSold->fetchColumn();

        $totalRevenue = $pdo->prepare("
            SELECT IFNULL(SUM(amount),0) 
            FROM payments 
            WHERE event_id=? AND status='completed'
        ");
        $totalRevenue->execute([$eventId]);
        $totalRevenue = $totalRevenue->fetchColumn();

        $averageRating = $pdo->prepare("
            SELECT IFNULL(ROUND(AVG(rating),1),0) 
            FROM feedback 
            WHERE event_id=?
        ");
        $averageRating->execute([$eventId]);
        $averageRating = $averageRating->fetchColumn();

        $totalAttendees = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM event_attendees 
            WHERE event_id=?
        ");
        $totalAttendees->execute([$eventId]);
        $totalAttendees = $totalAttendees->fetchColumn();

        $rsvpData = $pdo->prepare("
            SELECT status, COUNT(*) AS total 
            FROM event_attendees 
            WHERE event_id=? 
            GROUP BY status
        ");
        $rsvpData->execute([$eventId]);
        $rsvpData = $rsvpData->fetchAll(PDO::FETCH_ASSOC);

        $ratingDist = $pdo->prepare("
            SELECT rating, COUNT(*) AS total 
            FROM feedback 
            WHERE event_id=? 
            GROUP BY rating 
            ORDER BY rating ASC
        ");
        $ratingDist->execute([$eventId]);
        $ratingDist = $ratingDist->fetchAll(PDO::FETCH_ASSOC);

        $revenueOverTime = $pdo->prepare("
            SELECT DATE(created_at) AS date, SUM(amount) AS total
            FROM payments
            WHERE event_id=? AND status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $revenueOverTime->execute([$eventId]);
        $revenueOverTime = $revenueOverTime->fetchAll(PDO::FETCH_ASSOC);

        $ticketsSoldOverTime = $pdo->prepare("
            SELECT DATE(registered_at) AS date, SUM(quantity) AS total
            FROM event_attendees
            WHERE event_id=? AND status='going' AND registered_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(registered_at)
            ORDER BY date ASC
        ");
        $ticketsSoldOverTime->execute([$eventId]);
        $ticketsSoldOverTime = $ticketsSoldOverTime->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ensure empty arrays are returned if no data exists
    if(empty($rsvpData)) {
        $rsvpData = [['status' => 'No Data', 'total' => 0]];
    }
    if(empty($ratingDist)) {
        $ratingDist = [['rating' => 0, 'total' => 0]];
    }

    return [
        'tickets' => (int)$totalTicketsSold,
        'revenue' => (float)$totalRevenue,
        'averageRating' => (float)$averageRating,
        'totalAttendees' => (int)$totalAttendees,
        'attendees' => $rsvpData,
        'ratings' => $ratingDist,
        'revenueOverTime' => $revenueOverTime,
        'ticketsSoldOverTime' => $ticketsSoldOverTime
    ];
}

// Get metrics
$metrics = getEventMetrics($pdo, $userId, $eventId);

// Return JSON response
echo json_encode($metrics, JSON_PRETTY_PRINT);
exit;
?>