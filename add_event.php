<?php
require_once 'ClassAutoLoad.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Initialize objects
$LayoutObject = new layouts();
$FlashMessageObject = new FlashMessage();

// Initialize form variables
$title = $description = $location = $event_date = '';
$total_tickets = 0;
$status = 'draft';
$category_ids = [];
$form_errors = [];

// Fetch categories
try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, name FROM attendee_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database connection error: ' . $e->getMessage(), 'danger');
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $category_ids = $_POST['category_ids'] ?? [];
    $total_tickets = (int)($_POST['total_tickets'] ?? 0);
    $status = $_POST['status'] ?? 'draft';

    // Validation
    if (empty($title)) $form_errors['title_error'] = "Event title is required";
    if (empty($description)) $form_errors['description_error'] = "Event description is required";
    if (empty($location)) $form_errors['location_error'] = "Event location is required";
    if (empty($event_date)) $form_errors['date_error'] = "Event date is required";
    if (empty($category_ids)) $form_errors['categories_error'] = "Please select at least one category";
    if ($total_tickets < 0) $form_errors['tickets_error'] = "Total tickets cannot be negative";

    // Image upload
    $image_path = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;
        $file_type = $_FILES['event_image']['type'];
        $file_size = $_FILES['event_image']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $form_errors['image_error'] = "Only JPG, PNG, GIF, and WebP images are allowed";
        } elseif ($file_size > $max_size) {
            $form_errors['image_error'] = "Image size must be less than 5MB";
        } else {
            $upload_dir = __DIR__ . '/uploads/events/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $new_image_path = 'uploads/events/' . $filename;

            if (move_uploaded_file($_FILES['event_image']['tmp_name'], __DIR__ . '/' . $new_image_path)) {
                $image_path = $new_image_path;
            } else {
                $form_errors['image_error'] = "Failed to upload image";
            }
        }
    }

    // If no errors, insert into DB
    if (empty($form_errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO events (user_id, title, description, location, event_date, image, total_tickets, available_tickets, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $location,
                $event_date,
                $image_path,
                $total_tickets,
                $total_tickets,
                $status
            ]);

            $event_id = $pdo->lastInsertId();

            // Insert categories
            $stmt = $pdo->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
            foreach ($category_ids as $cat_id) {
                $stmt->execute([$event_id, (int)$cat_id]);
            }

            $pdo->commit();

            $FlashMessageObject->setMsg('msg', 'Event created successfully!', 'success');
            header("Location: my_events.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
        }
    } else {
        $FlashMessageObject->setMsg('msg', 'Please correct the errors below.', 'danger');
    }
}
// After event creation success
if ($status == 'upcoming') {
    // Send notifications about new event
    $notificationResult = $NotificationManager->notifyNewEvent($event_id);
    if ($notificationResult) {
        error_log("Sent {$notificationResult['notifications']} notifications and {$notificationResult['emails']} emails for new event");
    }
}


$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <!-- Changed green to violet -->
                <div class="card-header text-white" style="background-color: #8b5cf6;">
                    <h4 class="mb-0">Create New Event</h4>
                </div>
                <div class="card-body">
                    <?php echo $FlashMessageObject->getMsg('msg'); ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($title) ?>" required>
                            <?php if (isset($form_errors['title_error'])): ?>
                                <div class="text-danger small"><?= $form_errors['title_error'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($description) ?></textarea>
                            <?php if (isset($form_errors['description_error'])): ?>
                                <div class="text-danger small"><?= $form_errors['description_error'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location *</label>
                                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($location) ?>" required>
                                <?php if (isset($form_errors['location_error'])): ?>
                                    <div class="text-danger small"><?= $form_errors['location_error'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event Date & Time *</label>
                                <input type="datetime-local" name="event_date" class="form-control" value="<?= htmlspecialchars($event_date) ?>" required>
                                <?php if (isset($form_errors['date_error'])): ?>
                                    <div class="text-danger small"><?= $form_errors['date_error'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Event Categories *</label>
                            <div class="border rounded p-3">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>"
                                            <?= in_array($cat['id'], $category_ids) ? 'checked' : '' ?>>
                                        <label class="form-check-label"><?= htmlspecialchars($cat['name']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($form_errors['categories_error'])): ?>
                                <div class="text-danger small"><?= $form_errors['categories_error'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Tickets</label>
                                <input type="number" name="total_tickets" class="form-control" value="<?= htmlspecialchars($total_tickets) ?>" min="0">
                                <?php if (isset($form_errors['tickets_error'])): ?>
                                    <div class="text-danger small"><?= $form_errors['tickets_error'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Event Image</label>
                            <input type="file" name="event_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF, WebP</div>
                            <?php if (isset($form_errors['image_error'])): ?>
                                <div class="text-danger small"><?= $form_errors['image_error'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="my_events.php" class="btn btn-secondary me-2">Cancel</a>
                            <!-- Changed to violet -->
                            <button type="submit" name="create_event" class="btn text-white" style="background-color: #8b5cf6;">Create Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$LayoutObject->footer($conf);
?>
