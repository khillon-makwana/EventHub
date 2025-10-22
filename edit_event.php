<?php
require 'ClassAutoLoad.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

// Get event ID from URL
$event_id = (int)($_GET['id'] ?? 0);
if ($event_id <= 0) {
    header("Location: my_events.php");
    exit;
}

// Fetch event data
$event = null;
$event_categories = [];
$categories = [];

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get event
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $FlashMessageObject->setMsg('msg', 'Event not found or access denied', 'danger');
        header("Location: my_events.php");
        exit;
    }

    // Get event's current categories
    $stmt = $pdo->prepare("SELECT category_id FROM event_categories WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get all categories
    $stmt = $pdo->query("SELECT id, name FROM attendee_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $errors = [];

    // Gather form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $category_ids = $_POST['category_ids'] ?? [];
    $total_tickets = (int)($_POST['total_tickets'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $remove_image = isset($_POST['remove_image']);
    $ticket_price = (float)($_POST['ticket_price'] ?? 0);


    // Validation
    if (empty($title)) $errors['title_error'] = "Event title is required";
    if (empty($description)) $errors['description_error'] = "Event description is required";
    if (empty($location)) $errors['location_error'] = "Event location is required";
    if (empty($event_date)) $errors['date_error'] = "Event date is required";
    if (empty($category_ids)) $errors['categories_error'] = "Please select at least one category";
    if ($total_tickets < 0) $errors['tickets_error'] = "Total tickets cannot be negative";
    if ($ticket_price < 0) $errors['price_error'] = "Ticket price cannot be negative";


    // Handle image upload/removal
    $image_path = $event['image'];
    if ($remove_image && $image_path) {
        if (file_exists(__DIR__ . '/' . $image_path)) {
            unlink(__DIR__ . '/' . $image_path);
        }
        $image_path = null;
    }

    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        $file_type = $_FILES['event_image']['type'];
        $file_size = $_FILES['event_image']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $errors['image_error'] = "Only JPG, PNG, GIF, and WebP images are allowed";
        } elseif ($file_size > $max_size) {
            $errors['image_error'] = "Image size must be less than 5MB";
        } else {
            $upload_dir = __DIR__ . '/uploads/events/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $new_image_path = 'uploads/events/' . $filename;

            if (move_uploaded_file($_FILES['event_image']['tmp_name'], __DIR__ . '/' . $new_image_path)) {
                // Delete old image if exists
                if ($image_path && file_exists(__DIR__ . '/' . $image_path)) {
                    unlink(__DIR__ . '/' . $image_path);
                }
                $image_path = $new_image_path;
            } else {
                $errors['image_error'] = "Failed to upload image";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update event
            $stmt = $pdo->prepare("
                UPDATE events 
                SET title = ?, description = ?, location = ?, event_date = ?, 
                    image = ?, total_tickets = ?, available_tickets = ?, 
                    ticket_price = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");


            // Calculate available tickets
            $current_available = $event['available_tickets'];
            $ticket_difference = $total_tickets - $event['total_tickets'];
            $new_available = max(0, $current_available + $ticket_difference);

            $stmt->execute([
                $title,
                $description,
                $location,
                $event_date,
                $image_path,
                $total_tickets,
                $new_available,
                $ticket_price,      
                $status,
                $event_id,
                $_SESSION['user_id']
            ]);


            // Update categories - delete old, insert new
            $stmt = $pdo->prepare("DELETE FROM event_categories WHERE event_id = ?");
            $stmt->execute([$event_id]);

            $stmt = $pdo->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
            foreach ($category_ids as $category_id) {
                $stmt->execute([$event_id, (int)$category_id]);
            }

            $pdo->commit();

            $FlashMessageObject->setMsg('msg', 'Event updated successfully!', 'success');
            header("Location: my_events.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
        }
    } else {
        $FlashMessageObject->setMsg('errors', $errors, 'danger');
        $FlashMessageObject->setMsg('msg', 'Please fix the errors below', 'danger');
    }
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    .bg-primary {
        background-color: #8b5cf6 !important;
    }

    .btn-primary {
        background-color: #8b5cf6 !important;
        border-color: #8b5cf6 !important;
    }

    .btn-primary:hover {
        background-color: #7c3aed !important;
        border-color: #7c3aed !important;
    }

    .btn-primary:focus,
    .btn-primary:active {
        background-color: #6d28d9 !important;
        border-color: #6d28d9 !important;
        box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.4) !important;
    }

    .form-check-input:checked {
        background-color: #8b5cf6 !important;
        border-color: #8b5cf6 !important;
    }
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Event</h4>
                </div>
                <div class="card-body">
                    <?php echo $FlashMessageObject->getMsg('msg'); ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Event Title *</label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo htmlspecialchars($_POST['title'] ?? $event['title']); ?>" required>
                            <?php
                            $errors = $FlashMessageObject->getMsg('errors');
                            if (isset($errors['title_error'])) {
                                echo '<div class="text-danger small">' . $errors['title_error'] . '</div>';
                            }
                            ?>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php
                                                                                                                    echo htmlspecialchars($_POST['description'] ?? $event['description']);
                                                                                                                    ?></textarea>
                            <?php
                            if (isset($errors['description_error'])) {
                                echo '<div class="text-danger small">' . $errors['description_error'] . '</div>';
                            }
                            ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location *</label>
                                    <input type="text" class="form-control" id="location" name="location"
                                        value="<?php echo htmlspecialchars($_POST['location'] ?? $event['location']); ?>" required>
                                    <?php
                                    if (isset($errors['location_error'])) {
                                        echo '<div class="text-danger small">' . $errors['location_error'] . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="event_date" class="form-label">Event Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="event_date" name="event_date"
                                        value="<?php echo date('Y-m-d\TH:i', strtotime($_POST['event_date'] ?? $event['event_date'])); ?>" required>
                                    <?php
                                    if (isset($errors['date_error'])) {
                                        echo '<div class="text-danger small">' . $errors['date_error'] . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Event Categories *</label>
                            <div class="border rounded p-3">
                                <?php
                                $current_categories = $_POST['category_ids'] ?? $event_categories;
                                foreach ($categories as $category): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            name="category_ids[]"
                                            value="<?php echo $category['id']; ?>"
                                            id="category_<?php echo $category['id']; ?>"
                                            <?php echo (in_array($category['id'], $current_categories)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                            if (isset($errors['categories_error'])) {
                                echo '<div class="text-danger small">' . $errors['categories_error'] . '</div>';
                            }
                            ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="total_tickets" class="form-label">Total Tickets</label>
                                    <input type="number" class="form-control" id="total_tickets" name="total_tickets"
                                        value="<?php echo htmlspecialchars($_POST['total_tickets'] ?? $event['total_tickets']); ?>" min="0">
                                    <div class="form-text">Currently available: <?php echo $event['available_tickets']; ?> tickets</div>
                                    <?php
                                    if (isset($errors['tickets_error'])) {
                                        echo '<div class="text-danger small">' . $errors['tickets_error'] . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ticket_price" class="form-label">Ticket Price (KSh)</label>
                                    <input type="number" class="form-control" id="ticket_price" name="ticket_price"
                                        value="<?php echo htmlspecialchars($_POST['ticket_price'] ?? $event['ticket_price']); ?>"
                                        step="0.01" min="0">
                                    <div class="form-text">Set to 0 for free events</div>
                                    <?php
                                    if (isset($errors['price_error'])) {
                                        echo '<div class="text-danger small">' . $errors['price_error'] . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?php echo (($_POST['status'] ?? $event['status']) == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        <option value="upcoming" <?php echo (($_POST['status'] ?? $event['status']) == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                        <option value="ongoing" <?php echo (($_POST['status'] ?? $event['status']) == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="completed" <?php echo (($_POST['status'] ?? $event['status']) == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo (($_POST['status'] ?? $event['status']) == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="event_image" class="form-label">Event Image</label>
                            <?php if ($event['image']): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($event['image']); ?>"
                                        class="img-thumbnail" style="max-height: 150px;">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">Remove current image</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="event_image" name="event_image"
                                accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF, WebP</div>
                            <?php
                            if (isset($errors['image_error'])) {
                                echo '<div class="text-danger small">' . $errors['image_error'] . '</div>';
                            }
                            ?>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="my_events.php" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" name="update_event" class="btn btn-primary">Update Event</button>
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