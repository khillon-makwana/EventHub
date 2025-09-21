<?php
require 'ClassAutoLoad.php';

// Handle 2FA submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredCode = trim($_POST['code'] ?? '');

    if (
        isset($_SESSION['2fa_code'], $_SESSION['2fa_user_id'], $_SESSION['2fa_expires']) &&
        time() < $_SESSION['2fa_expires']
    ) {
        if ($enteredCode == $_SESSION['2fa_code']) {
            // Fetch user fullname from DB
            try {
                $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['2fa_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $_SESSION['user_fullname'] = $user['fullname'];
                }

            } catch (PDOException $e) {
                // Optional: log error
            }

            // Success → log user in
            $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
            unset($_SESSION['2fa_code'], $_SESSION['2fa_user_id'], $_SESSION['2fa_expires']);

            $FlashMessageObject->setMsg('msg', 'Login successful!', 'success');
            header("Location: dashboard.php");
            exit;

        } else {
            $FlashMessageObject->setMsg('msg', 'Invalid 2FA code.', 'danger');
        }
    } else {
        $FlashMessageObject->setMsg('msg', '2FA code expired. Please login again.', 'warning');
        header("Location: signin.php");
        exit;
    }
}


$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-5" style="max-width: 400px;">
    <h2>Two-Factor Authentication</h2>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="code" class="form-label">Enter 6-digit code sent to your email</label>
            <input type="text" class="form-control" id="code" name="code" maxlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary">Verify</button>
    </form>

    <p class="mt-3">
        Didn't get the code? 
        <a href="resend_2fa.php">Resend Code</a>
    </p>
</div>

<?php
$LayoutObject->footer($conf);
?>
