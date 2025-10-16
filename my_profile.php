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

<div class="container mt-5">
    <h2 class="mb-4">My Profile</h2>

    <?php if (!empty($success)) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (!empty($error)) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm rounded mb-4">
        <h5 class="mb-3">Profile Information</h5>
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email (read-only)</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Status:</label>
            <span class="badge <?= $user['is_verified'] ? 'bg-success' : 'bg-danger' ?>">
                <?= $user['is_verified'] ? 'Verified' : 'Not Verified' ?>
            </span>
        </div>

        <div class="mb-3 text-muted small">
            Member since: <?= date('F j, Y', strtotime($user['created_at'])) ?>
        </div>

        <button type="submit" name="update_profile" class="btn btn-primary w-100">Update Profile</button>
    </form>

    <!-- Change Password Section -->
    <form method="POST" class="card p-4 shadow-sm rounded">
        <h5 class="mb-3">Change Password</h5>

        <?php if (!empty($pw_success)) : ?>
            <div class="alert alert-success"><?= htmlspecialchars($pw_success) ?></div>
        <?php elseif (!empty($pw_error)) : ?>
            <div class="alert alert-danger"><?= htmlspecialchars($pw_error) ?></div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>

        <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
    </form>
</div>

<?php $LayoutObject->footer($conf); ?>
