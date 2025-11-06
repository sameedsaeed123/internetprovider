<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'name' => 'Test User',
    'email' => 'sameedjutt234@gmail.com',
    'subject' => 'Test message from local test',
    'message' => 'This is a test message to verify send_contact.php'
];
require __DIR__ . '/send_contact.php';

?>
