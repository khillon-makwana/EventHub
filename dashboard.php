<?php
require 'ClassAutoLoad.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
$LayoutObject->banner($FlashMessageObject);
$LayoutObject->events();
$LayoutObject->footer($conf);
