<?php
require 'ClassAutoLoad.php';

if (!isset($_GET['email']) || empty($_GET['email'])) {
    $FlashMessageObject->setMsg('msg', 'Email required to resend code.', 'danger');
    header("Location: signup.php");
    exit;
}

$email = strtolower(trim($_GET['email']));

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get user
    $stmt = $pdo->prepare("SELECT id, fullname, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $FlashMessageObject->setMsg('msg', 'No account found with that email.', 'danger');
        header("Location: signup.php");
        exit;
    }
    if ((int)$user['is_verified'] === 1) {
        $FlashMessageObject->setMsg('msg', 'Account already verified. Please login.', 'info');
        header("Location: signin.php");
        exit;
    }

    // Rate limit: ensure at least 60 seconds since last code
    $stmt = $pdo->prepare("SELECT created_at FROM email_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        $lastTs = strtotime($last['created_at']);
        if ((time() - $lastTs) < 60) {
            $FlashMessageObject->setMsg('msg', 'Please wait a moment before requesting another code.', 'warning');
            header("Location: verify.php?email=" . urlencode($email));
            exit;
        }
    }

    // Generate & insert new code
    $newCode = random_int(100000, 999999);
    $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, code) VALUES (?, ?)");
    $stmt->execute([$user['id'], (string)$newCode]);

    // Load template
    $messagesFile = __DIR__ . '/../EmailMessages/messages_' . $conf['site_lang'] . '.php';
    if (file_exists($messagesFile)) {
        include $messagesFile;
    } else {
        $msg['verify_subject'] = "Account Activation Code - {$conf['site_name']}";
        $msg['verify_body'] = "Hello {{fullname}}, Your verification code: <h2>{{activation_code}}</h2>";
    }

    $subject = str_replace('{{site_name}}', $conf['site_name'], $msg['verify_subject']);
    $body = str_replace(
        ['{{fullname}}', '{{site_name}}', '{{activation_code}}'],
        [$user['fullname'], $conf['site_name'], $newCode],
        $msg['verify_body']
    );

    $mailCnt = [
        'name_from' => $conf['site_name'],
        'email_from' => $conf['admin_email'],
        'name_to' => $user['fullname'],
        'email_to' => $email,
        'subject' => $subject,
        'body' => $body
    ];

    $sendResult = $MailSendObject->Send_Mail($conf, $mailCnt);
    if ($sendResult !== true) {
        $FlashMessageObject->setMsg('msg', 'Failed to send verification email: ' . $sendResult, 'danger');
    } else {
        $FlashMessageObject->setMsg('msg', 'A new verification code has been sent to your email.', 'success');
    }

    header("Location: verify.php?email=" . urlencode($email));
    exit;

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Database error: ' . $e->getMessage(), 'danger');
    header("Location: signup.php");
    exit;
}
