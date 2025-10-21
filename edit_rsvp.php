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

    // Fetch RSVP record
    $stmt = $pdo->prepare("
        SELECT ea.*, u.fullname, u.email
        FROM event_attendees ea
        JOIN users u ON ea.user_id = u.id
        WHERE ea.id = ?
    ");
    $stmt->execute([$id]);
    $rsvp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rsvp) {
        die("RSVP not found.");
    }

    // Handle form submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $status = $_POST['status'];
        $category_id = $_POST['category_id'];

        $update = $pdo->prepare("UPDATE event_attendees SET status = ?, category_id = ? WHERE id = ?");
        $update->execute([$status, $category_id, $id]);

        header("Location: manage_rsvps.php?event_id=$eventId");
        exit;
    }

    // Fetch available categories
    $catStmt = $pdo->query("SELECT id, name FROM attendee_categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit RSVP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <h3 class="mb-4">Edit RSVP: <?= htmlspecialchars($rsvp['fullname']); ?></h3>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="going" <?= $rsvp['status'] === 'going' ? 'selected' : ''; ?>>Going</option>
                <option value="interested" <?= $rsvp['status'] === 'interested' ? 'selected' : ''; ?>>Interested</option>
                <option value="not going" <?= $rsvp['status'] === 'not going' ? 'selected' : ''; ?>>Not Going</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id']; ?>" <?= $rsvp['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a href="manage_rsvps.php?event_id=<?= $eventId; ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
