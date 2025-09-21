<?php
require 'ClassAutoLoad.php';

// process login if submitted
$AuthorizationObject->login($conf, $FlashMessageObject,$MailSendObject);

$LayoutObject->head($conf);
$LayoutObject->form_content($conf, $FormObject, $FlashMessageObject);
$LayoutObject->footer($conf);
