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
        SELECT ea.*, u.fullname, u.email, e.title as event_title
        FROM event_attendees ea
        JOIN users u ON ea.user_id = u.id
        JOIN events e ON ea.event_id = e.id
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit RSVP - <?= htmlspecialchars($rsvp['fullname']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }

        .page-container {
            animation: fadeInUp 0.6s ease-out;
        }

        .edit-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .card-header-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        .card-header-custom h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
            position: relative;
            z-index: 1;
        }

        .card-header-custom .subtitle {
            margin: 0.75rem 0 0 0;
            opacity: 0.95;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .card-header-custom .event-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 1rem;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }

        .card-body-custom {
            padding: 2.5rem;
        }

        .attendee-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
            animation: slideInRight 0.8s ease-out;
        }

        .attendee-info h5 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .attendee-info .info-row {
            display: flex;
            align-items: center;
            margin: 0.5rem 0;
            color: #4a5568;
        }

        .attendee-info .info-row .icon {
            width: 30px;
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .form-group-animated {
            margin-bottom: 1.5rem;
            animation: slideInRight 1s ease-out;
        }

        .form-group-animated:nth-child(1) { animation-delay: 0.1s; }
        .form-group-animated:nth-child(2) { animation-delay: 0.2s; }

        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.75rem;
            display: block;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
            cursor: pointer;
        }

        .form-select:hover {
            border-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            outline: none;
            transform: translateY(-2px);
        }

        .status-select {
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
            animation: fadeInUp 1.2s ease-out;
        }

        .btn {
            border-radius: 12px;
            padding: 0.9rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            flex: 1;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        .btn:active {
            transform: translateY(0) !important;
        }

        .form-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .status-preview {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s ease-in-out infinite;
        }

        .status-going { background: #28a745; }
        .status-interested { background: #ffc107; }
        .status-not-going { background: #dc3545; }

        @media (max-width: 576px) {
            .card-header-custom {
                padding: 2rem 1.5rem;
            }

            .card-header-custom h3 {
                font-size: 1.5rem;
            }

            .card-body-custom {
                padding: 2rem 1.5rem;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(102, 126, 234, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.5rem;
            animation: fadeInUp 0.3s ease-out;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner mx-auto mb-3"></div>
            <div>Saving changes...</div>
        </div>
    </div>

    <div class="container page-container">
        <div class="edit-card">
            <div class="card-header-custom">
                <h3>✏️ Edit RSVP</h3>
                <p class="subtitle">Update attendee response and category</p>
                <?php if (isset($rsvp['event_title'])): ?>
                    <div class="event-badge">
                        📅 <?= htmlspecialchars($rsvp['event_title']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-body-custom">
                <div class="attendee-info">
                    <h5>👤 Attendee Information</h5>
                    <div class="info-row">
                        <span class="icon">📝</span>
                        <strong>Name:</strong>&nbsp;<?= htmlspecialchars($rsvp['fullname']); ?>
                    </div>
                    <div class="info-row">
                        <span class="icon">📧</span>
                        <strong>Email:</strong>&nbsp;<?= htmlspecialchars($rsvp['email']); ?>
                    </div>
                    <div class="info-row">
                        <span class="icon">🗓️</span>
                        <strong>Registered:</strong>&nbsp;<?= date('M j, Y g:i A', strtotime($rsvp['registered_at'])); ?>
                    </div>
                </div>

                <form method="POST" id="editForm" onsubmit="showLoading()">
                    <div class="form-group-animated">
                        <label class="form-label">
                            <span class="form-icon">📍</span>
                            RSVP Status
                        </label>
                        <select name="status" class="form-select status-select" required id="statusSelect">
                            <option value="going" <?= $rsvp['status'] === 'going' ? 'selected' : ''; ?>>
                                ✓ Going
                            </option>
                            <option value="interested" <?= $rsvp['status'] === 'interested' ? 'selected' : ''; ?>>
                                ⭐ Interested
                            </option>
                            <option value="not going" <?= $rsvp['status'] === 'not going' ? 'selected' : ''; ?>>
                                ✗ Not Going
                            </option>
                        </select>
                    </div>

                    <div class="form-group-animated">
                        <label class="form-label">
                            <span class="form-icon">🏷️</span>
                            Attendee Category
                        </label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>" <?= $rsvp['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <button class="btn btn-primary" type="submit">
                            💾 Save Changes
                        </button>
                        <a href="manage_rsvps.php?event_id=<?= $eventId; ?>" class="btn btn-secondary">
                            ← Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Add smooth scroll behavior
        document.addEventListener('DOMContentLoaded', function() {
            // Animate form elements on load
            const formGroups = document.querySelectorAll('.form-group-animated');
            formGroups.forEach((group, index) => {
                setTimeout(() => {
                    group.style.opacity = '1';
                }, index * 100);
            });
        });

        // Add visual feedback for select changes
        document.querySelectorAll('.form-select').forEach(select => {
            select.addEventListener('change', function() {
                this.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>