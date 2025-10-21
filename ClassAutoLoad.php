<?php
require __DIR__ . '/vendor/autoload.php';

require_once 'conf.php';

// start session (FlashMessage relies on sessions)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$directories = ["Forms","PageLayouts","Global","Proc","FlashMessage","Notifications", ""];

spl_autoload_register(function ($className) use ($directories) {
    foreach ($directories as $directory) {
        if ($directory === "") {
            $filePath = __DIR__ . "/" . $className . '.php';
        } else {
            $filePath = __DIR__ . "/$directory/" . $className . '.php';
        }
        
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});

// instantiate common objects
$FormObject = new forms();
$LayoutObject = new layouts();
$MailSendObject = new SendMail();
$FlashMessageObject = new FlashMessage();

// Instantiate NotificationManager with configuration and mailer
$NotificationManager = new NotificationManager($conf, $MailSendObject);

// make Authorization available to pages
// (class file resides in Proc/Authorization.php and will be autoloaded)
$AuthorizationObject = new Authorization();
?>
