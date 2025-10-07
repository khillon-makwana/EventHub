<?php
require_once 'ClassAutoLoad.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    $FlashMessageObject->setMsg('msg', 'Invalid or missing reset token.', 'danger');
    exit;
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check token validity
    $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData) {
        $FlashMessageObject->setMsg('msg', 'Invalid or expired token.', 'danger');
        exit;
    }

    if (strtotime($resetData['expires_at']) < time()) {
        $FlashMessageObject->setMsg('msg', 'This reset link has expired.', 'danger');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['password'] ?? '';

        if (strlen($newPassword) < $conf['min_password_length']) {
            $FlashMessageObject->setMsg('msg', 'Password must be at least ' . $conf['min_password_length'] . ' characters.', 'danger');
        } elseif ($newPassword !== ($_POST['confirm_password'] ?? '')) {
            $FlashMessageObject->setMsg('msg', 'Passwords do not match.', 'danger');
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetData['email']]);

            // Delete token (single-use)
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $FlashMessageObject->setMsg('msg', 'Password reset successful! You can now sign in.', 'success');
            header("Location: signin.php");
            exit;
        }
    }
} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
}
?>

<?php
$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-5">
    <h2 class="mb-3">Reset Password</h2>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
        </div>

        <button type="submit" class="btn btn-primary">Reset Password</button>
    </form>

    <p class="mt-3">
        Remembered your password?
        <a href="signin.php" class="text-decoration-none fw-semibold" style="color: #0d6efd;">
            Sign In
        </a>
    </p>
</div>

<?php
$LayoutObject->footer($conf);
?>
