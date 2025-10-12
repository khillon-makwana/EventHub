<?php
require 'ClassAutoLoad.php';

// Run signup logic (if any)
$AuthorizationObject->signup($conf, $FlashMessageObject, $MailSendObject);

// Render page
$LayoutObject->head($conf);
$LayoutObject->form_content($conf, $FormObject, $FlashMessageObject);
$LayoutObject->footer($conf);
