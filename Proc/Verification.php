<?php
class Verification {
    // Handle POST verify action
    public function handleVerification($conf, $FlashMessageObject) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $code  = trim($_POST['code'] ?? '');

            if ($email === '' || $code === '') {
                $FlashMessageObject->setMsg('msg', 'Email and code required.', 'danger');
                return;
            }

            try {
                $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Get user
                $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $FlashMessageObject->setMsg('msg', 'User not found.', 'danger');
                    return;
                }

                if ((int)$user['is_verified'] === 1) {
                    $FlashMessageObject->setMsg('msg', 'Account already verified. Please login.', 'info');
                    header("Location: signin.php");
                    exit;
                }

                // Check code within last 15 minutes
                $stmt = $pdo->prepare("
                    SELECT id FROM email_verifications
                    WHERE user_id = ? AND code = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$user['id'], $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    // mark verified
                    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user['id']]);
                    $FlashMessageObject->setMsg('msg', 'Account verified successfully! You can now log in.', 'success');
                    header("Location: signin.php");
                    exit;
                } else {
                    $FlashMessageObject->setMsg('msg', 'Invalid or expired code (codes are valid for 15 minutes).', 'danger');
                }

            } catch (PDOException $e) {
                $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
            }
        }
    }
}
