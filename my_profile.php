<?php
require 'ClassAutoLoad.php';

//  Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    //  Establish PDO connection
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //  Fetch user details
    $stmt = $pdo->prepare("SELECT fullname, email, is_verified, created_at, password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }

    //  Handle profile update
    if (isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);

        if ($fullname) {
            $update = $pdo->prepare("UPDATE users SET fullname = ? WHERE id = ?");
            if ($update->execute([$fullname, $user_id])) {
                $success = "Profile updated successfully!";
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_fullname'] = $fullname;
            } else {
                $error = "Error updating profile.";
            }
        } else {
            $error = "Full name cannot be empty.";
        }
    }

    //  Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!$current_password || !$new_password || !$confirm_password) {
            $pw_error = "All password fields are required.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $pw_error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $pw_error = "New password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $pw_error = "New passwords do not match.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update_pw->execute([$hashed, $user_id])) {
                $pw_success = "Password changed successfully!";
            } else {
                $pw_error = "Error updating password.";
            }
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

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
            transform: scale(1.05);
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

    .profile-container {
        animation: fadeInUp 0.6s ease-out;
        max-width: 800px;
        margin: 0 auto;
    }

    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        animation: fadeInUp 0.8s ease-out;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }

    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 4s ease-in-out infinite;
    }

    .profile-header h2 {
        margin: 0;
        font-weight: 700;
        font-size: 2.5rem;
        position: relative;
        z-index: 1;
    }

    .profile-header p {
        margin: 0.5rem 0 0 0;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .profile-card {
        animation: fadeInUp 1s ease-out;
        transition: all 0.3s ease;
        border: none;
        border-radius: 15px;
        overflow: hidden;
        background: white;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    }

    .profile-card h5 {
        color: #667eea;
        font-weight: 600;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .form-control {
        border: 2px solid #e8e8e8;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }

    .form-control:hover:not(:focus):not([readonly]) {
        border-color: #c0c0c0;
    }

    .form-control[readonly] {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        cursor: not-allowed;
    }

    .form-label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 0.5rem;
        animation: slideInRight 0.5s ease-out;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 10px;
        padding: 0.85rem 2rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s ease;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .alert {
        border: none;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        animation: slideInRight 0.5s ease-out;
        font-weight: 500;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        animation: pulse 2s ease-in-out infinite;
        letter-spacing: 0.5px;
    }

    .badge.bg-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .badge.bg-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }

    .member-since {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #667eea;
        animation: slideInRight 0.7s ease-out;
    }

    .input-group-animated {
        position: relative;
    }

    .input-group-animated input:focus ~ .input-focus-border {
        width: 100%;
    }

    .password-section {
        animation: fadeInUp 1.2s ease-out;
    }

    @media (max-width: 768px) {
        .profile-header h2 {
            font-size: 2rem;
        }
        
        .profile-card {
            margin-bottom: 1.5rem;
        }
    }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-indicator::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s ease-in-out infinite;
    }

    .status-indicator.verified::before {
        background: #28a745;
        box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
    }

    .status-indicator.unverified::before {
        background: #dc3545;
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
    }
</style>

<div class="container mt-5 profile-container">
    <div class="profile-header">
        <h2>👤 My Profile</h2>
        <p>Manage your account settings and preferences</p>
    </div>

    <?php if (!empty($success)) : ?>
        <div class="alert alert-success">
            <strong>✓ Success!</strong> <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif (!empty($error)) : ?>
        <div class="alert alert-danger">
            <strong>✗ Error!</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card profile-card p-4 mb-4">
        <h5>📝 Profile Information</h5>
        
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label d-block">Email Verification Status</label>
            <span class="badge <?= $user['is_verified'] ? 'bg-success' : 'bg-danger' ?> status-indicator <?= $user['is_verified'] ? 'verified' : 'unverified' ?>">
                <?= $user['is_verified'] ? '✓ Verified' : '✗ Not Verified' ?>
            </span>
        </div>

        <div class="member-since mb-4">
            <strong>🗓️ Member Since:</strong> <?= date('F j, Y', strtotime($user['created_at'])) ?>
        </div>

        <button type="submit" name="update_profile" class="btn btn-primary w-100">
            💾 Update Profile
        </button>
    </form>

    <form method="POST" class="card profile-card p-4 password-section">
        <h5>🔒 Change Password</h5>

        <?php if (!empty($pw_success)) : ?>
            <div class="alert alert-success">
                <strong>✓ Success!</strong> <?= htmlspecialchars($pw_success) ?>
            </div>
        <?php elseif (!empty($pw_error)) : ?>
            <div class="alert alert-danger">
                <strong>✗ Error!</strong> <?= htmlspecialchars($pw_error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
            <small class="text-muted">Must be at least 6 characters</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>

        <button type="submit" name="change_password" class="btn btn-primary w-100">
            🔑 Update Password
        </button>
    </form>
</div>

<?php $LayoutObject->footer($conf); ?>