<?php

ob_start();

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

function load_env($path)
{
    $result = [];
    if (!is_readable($path)) return $result;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((substr($v,0,1) === '"' && substr($v,-1) === '"') || (substr($v,0,1) === "'" && substr($v,-1) === "'")) {
            $v = substr($v,1,-1);
        }
        $result[$k] = $v;
    }
    return $result;
}

$env = load_env(__DIR__ . '/../../.env');

function env_log($msg)
{
    global $env;
    if (!empty($env['LOG_FILE'])) {
        $path = __DIR__ . '/../../' . $env['LOG_FILE'];
        @file_put_contents($path, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
   @ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'New contact message';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

$errors = [];
if ($name === '') $errors[] = 'Name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($message === '') $errors[] = 'Message is required';

if (!empty($errors)) {
    @ob_end_clean();
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$to = isset($env['MAIL_TO']) && $env['MAIL_TO'] !== '' ? $env['MAIL_TO'] : null;
if (!$to) {
    @ob_end_clean();
    env_log('MAIL_TO not configured in .env');
    echo json_encode(['success' => false, 'error' => 'Recipient (MAIL_TO) not configured.']);
    exit;
}

$plainBody = "You have a new contact form submission:\n\n";
$plainBody .= "Name: " . $name . "\n";
$plainBody .= "Email: " . $email . "\n";
$plainBody .= "Subject: " . $subject . "\n\n";
$plainBody .= "Message:\n" . $message . "\n";

$templatePath = __DIR__ . '/email_templates/contact_email.html';
$htmlBody = '';
if (is_readable($templatePath)) {
    $htmlBody = file_get_contents($templatePath);
    $replacements = [
        '{{NAME}}' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '{{EMAIL}}' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '{{SUBJECT}}' => htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '{{MESSAGE}}' => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    ];
    $htmlBody = strtr($htmlBody, $replacements);
}

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    try {
       $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
       
        if (!empty($env['SMTP_HOST'])) {
            $mail->isSMTP();
            $mail->Host = $env['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = isset($env['SMTP_USER']) ? $env['SMTP_USER'] : '';
            $mail->Password = isset($env['SMTP_PASS']) ? $env['SMTP_PASS'] : '';
            if (!empty($env['SMTP_PORT'])) $mail->Port = (int)$env['SMTP_PORT'];
            if (!empty($env['SMTP_SECURE'])) $mail->SMTPSecure = $env['SMTP_SECURE'];
        }
        $from = !empty($env['MAIL_FROM']) ? $env['MAIL_FROM'] : 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $mail->setFrom($from, 'Website Contact');
        $mail->addAddress($to);
        $mail->addReplyTo($email, $name);
        $mail->Subject = $subject;
        if ($htmlBody !== '') {
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
        } else {
            $mail->Body = $plainBody;
            $mail->AltBody = $plainBody;
        }
        $mail->send();
        @ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Message sent']);
        exit;
    } catch (Exception $e) {
        env_log('PHPMailer error: ' . $e->getMessage());
    }
}

$headers = [];
$fromHeader = !empty($env['MAIL_FROM']) ? $env['MAIL_FROM'] : 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
$headers[] = 'From: ' . $fromHeader;
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
if ($htmlBody !== '') {
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $mailContent = $htmlBody;
} else {
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $mailContent = $plainBody;
}
$ok = @mail($to, $subject, $mailContent, implode("\r\n", $headers));
if ($ok) {
    @ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Message sent (mail fallback)']);
    exit;
} else {
    env_log('mail() failed to send. Last error: ' . print_r(error_get_last(), true));
    @ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unable to send message.']);
    exit;
}

