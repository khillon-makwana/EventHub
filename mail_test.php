<?php
require 'ClassAutoLoad.php';

$content = [
    'name_from' => 'EventHub',
    'email_from' => 'no-reply@eventhub.com',
    'name_to' => 'Khillon Makwana',
    'email_to' => 'khillonmakwana10@gmail.com',
    'subject' => 'Welcome to EventHub',
    'body' => 'Browse for Events and get to rsvp for various events at an affordable rate'
];
$MailSendObject->Send_Mail($conf, $content);