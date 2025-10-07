<?php
require_once 'ClassAutoLoad.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $FlashMessageObject->setMsg('msg', 'Please enter a valid email address.', 'danger');
    } else {
        try {
            // Database connection
            $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $FlashMessageObject->setMsg('msg', 'No account found with that email address.', 'danger');
            } else {
                // Generate reset token (unique, secure)
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

                // Store token in DB
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);

                // Build reset link
                $resetLink = $conf['site_url'] . "/reset_password.php?token=" . $token;

                // Prepare email
                $subject = "Password Reset Request - {$conf['site_name']}";
                $body = "Hi {$user['fullname']},<br><br>"
                      . "You requested to reset your password. Click the link below to set a new one:<br>"
                      . "<a href='{$resetLink}'>Reset Password</a><br><br>"
                      . "This link expires in 1 hour.<br><br>"
                      . "If you didn't request this, please ignore this email.";

                $mailCnt = [
                    'name_from' => $conf['site_name'],
                    'email_from' => $conf['admin_email'],
                    'name_to' => $user['fullname'],
                    'email_to' => $email,
                    'subject' => $subject,
                    'body' => $body
                ];

                $MailSendObject->Send_Mail($conf, $mailCnt);

                $FlashMessageObject->setMsg('msg', 'Password reset link sent to your email.', 'success');
            }
        } catch (PDOException $e) {
            $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
        }
    }
}
?>

<?php
$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-5">
    <h2 class="mb-3">Forgot Password</h2>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="email" class="form-label">Enter your email address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="e.g. user@example.com" required>
        </div>

        <button type="submit" class="btn btn-primary">Send Reset Link</button>
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
