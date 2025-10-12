<?php
require 'Plugins/PHPMailer/vendor/autoload.php';

require_once 'conf.php';

// start session (FlashMessage relies on sessions)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$directories = ["Forms","PageLayouts","Global","Proc","FlashMessage"];

spl_autoload_register(function ($className) use ($directories) {
    foreach ($directories as $directory) {
        $filePath = __DIR__ . "/$directory/" . $className . '.php';
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

// make Authorization available to pages
// (class file resides in Proc/Authorization.php and will be autoloaded)
$AuthorizationObject = new Authorization();
