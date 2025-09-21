<?php
class Authorization {
    // Handle signup (insert user, create & store code, send email)
    public function signup($conf, $FlashMessageObject, $MailSendObject) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
            $errors = [];

            // Gather and sanitize
            $fullname = $_SESSION['fullname'] = ucwords(strtolower(trim($_POST['fullname'] ?? '')));
            $email    = $_SESSION['email']    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_SESSION['password'] = $_POST['password'] ?? '';

            // Validations
            if (!preg_match("/^[a-zA-Z-' ]*$/", $fullname)) {
                $errors['nameFormat_error'] = "Only letters and spaces allowed.";
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['mailFormat_error'] = "Invalid email format.";
            }
            $emailDomain = substr(strrchr($email, "@"), 1);
            if (!in_array($emailDomain, $conf['valid_email_domains'])) {
                $errors['emailDomain_error'] = "Invalid email domain.";
            }
            if (strlen($password) < $conf['min_password_length']) {
                $errors['passwordLength_error'] =
                    "Password must be at least {$conf['min_password_length']} characters.";
            }

            if (!empty($errors)) {
                $FlashMessageObject->setMsg('errors', $errors, 'danger');
                $FlashMessageObject->setMsg('msg', 'Please fix the errors below and try again.', 'danger');
                return;
            }

            try {
                $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Check duplicate email
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $FlashMessageObject->setMsg('msg', 'This email is already registered.', 'danger');
                    return;
                }

                // Insert user (not verified yet)
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$fullname, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = $pdo->lastInsertId();

                // Generate 6-digit code and store it
                $activationCode = random_int(100000, 999999);
                $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, code) VALUES (?, ?)");
                $stmt->execute([$userId, (string)$activationCode]);

                // Load email template file (messages_en.php) using absolute path
                $messagesFile = __DIR__ . '/../EmailMessages/messages_' . $conf['site_lang'] . '.php';
                if (file_exists($messagesFile)) {
                    include $messagesFile; // defines $msg[...] array
                } else {
                    // fallback message
                    $msg['verify_subject'] = "Account Activation Code - {$conf['site_name']}";
                    $msg['verify_body'] = "Hello {{fullname}}, Your verification code: <h2>{{activation_code}}</h2>";
                }

                $subject = str_replace('{{site_name}}', $conf['site_name'], $msg['verify_subject']);
                $body = str_replace(
                    ['{{fullname}}', '{{site_name}}', '{{activation_code}}'],
                    [$fullname, $conf['site_name'], $activationCode],
                    $msg['verify_body']
                );

                $mailCnt = [
                    'name_from' => $conf['site_name'],
                    'email_from' => $conf['admin_email'],
                    'name_to' => $fullname,
                    'email_to' => $email,
                    'subject' => $subject,
                    'body' => $body
                ];

                $sendResult = $MailSendObject->Send_Mail($conf, $mailCnt);

                if ($sendResult !== true) {
                    // Mail failed (but account is created) — inform the user
                    $FlashMessageObject->setMsg('msg', 'Signup created but failed to send email: ' . $sendResult, 'warning');
                    header("Location: verify.php?email=" . urlencode($email));
                    exit;
                }

                $FlashMessageObject->setMsg('msg', 'Signup successful! Please check your email for the verification code.', 'success');

                // clear form session values
                unset($_SESSION['fullname'], $_SESSION['email'], $_SESSION['password']);

                header("Location: verify.php?email=" . urlencode($email));
                exit;

            } catch (PDOException $e) {
                $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
            }
        }
    }

    // Handle login
    public function login($conf, $FlashMessageObject, $MailSendObject) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signin'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $FlashMessageObject->setMsg('msg', 'Invalid email or password.', 'danger');
            return;
        }

        try {
            $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                $FlashMessageObject->setMsg('msg', 'Invalid email or password.', 'danger');
                return;
            }

            if ((int)$user['is_verified'] === 0) {
                $FlashMessageObject->setMsg('msg', 'Your account is not verified. Please check your email for the verification code.', 'warning');
                header("Location: verify.php?email=" . urlencode($email));
                exit;
            }

            //  Generate 6-digit code for 2FA
            $twoFACode = random_int(100000, 999999);

            // Store in session
            $_SESSION['2fa_code'] = $twoFACode;
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_expires'] = time() + 300; // valid 5 mins

            // Send email with code
            $subject = "Your 2FA Login Code - {$conf['site_name']}";
            $body = "Hello {$user['fullname']},<br><br>"
                  . "Your login verification code is: <h2>{$twoFACode}</h2><br>"
                  . "This code will expire in 5 minutes.<br><br>"
                  . "Regards,<br>EventHub";

            $mailCnt = [
                'name_from' => $conf['site_name'],
                'email_from' => $conf['admin_email'],
                'name_to'   => $user['fullname'],
                'email_to'  => $user['email'],
                'subject'   => $subject,
                'body'      => $body
            ];

            $MailSendObject->Send_Mail($conf, $mailCnt);

            // Redirect to 2FA page
            header("Location: verify2fa.php");
            exit;

        } catch (PDOException $e) {
            $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
        }
    }
}

}
