<?php
// Site Information
$conf['site_name'] = 'EventHub';
$conf['site_url'] = 'http://localhost/EVENTHUB';
$conf['admin_email'] = 'admin@eventhub.com';

// Database Configuration
$conf['db_type'] = 'pdo';
$conf['db_host'] = 'localhost';
$conf['db_user'] = 'root';
$conf['db_pass'] = '';
$conf['db_name'] = '';
$conf['db_port'] = 0000; 

// Site Language
$conf['site_lang'] = 'en';

// Email Configurations
$conf['mail_type'] = 'smtp'; // Options: 'smtp' or 'mail'
$conf['smtp_host'] = 'smtp.gmail.com';
$conf['smtp_user'] = '';
$conf['smtp_pass'] = '';
$conf['smtp_port'] = 465;
$conf['smtp_secure'] = 'ssl';

$conf['min_password_length'] = 8;

$conf['valid_email_domains'] = ['eventhub.com', 'gmail.com', 'yahoo.com', 'outlook.com', 'strathmore.edu'];