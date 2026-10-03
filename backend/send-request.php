<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Mail config
|--------------------------------------------------------------------------
|
| Fejlesztéskor:
|   /projekt/mail-config.php
|
| Éles szerveren:
|   /home/user/mail-config.php
|   /home/user/public_html/api/send-request.php
|
*/

$configPaths = [
    dirname(__DIR__) . '/mail-config.php',
    dirname(__DIR__, 2) . '/mail-config.php',
];

$configFile = null;

foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $configFile = $path;
        break;
    }
}

if ($configFile === null) {
    http_response_code(500);
    exit('Mail configuration missing.');
}

$config = require $configFile;


/*
|--------------------------------------------------------------------------
| Csak POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}


/*
|--------------------------------------------------------------------------
| Honeypot
|--------------------------------------------------------------------------
*/

if (!empty($_POST['website'] ?? '')) {
    header('Location: /danke', true, 303);
    exit;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function field(string $name): string
{
    return trim((string) ($_POST[$name] ?? ''));
}

function safe(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function fail(string $code): never
{
    header(
        'Location: /anfrage?status=' . urlencode($code),
        true,
        303
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Form fields
|--------------------------------------------------------------------------
*/

$service = field('service');
$postalCode = field('postal_code');
$city = field('city');
$message = field('message');

$name = field('name');
$phone = field('phone');
$email = field('email');

$preferredContact = field('preferred_contact');
$privacy = field('privacy');


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    $service === '' ||
    $postalCode === '' ||
    $city === '' ||
    $message === '' ||
    $name === '' ||
    $phone === '' ||
    $email === ''
) {
    fail('missing');
}

if ($privacy !== 'accepted') {
    fail('privacy');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('email');
}

$allowedServices = [
    'Küchenmontage',
    'Möbelmontage',
    'Türen und Zargen',
    'Sonstiges',
];

if (!in_array($service, $allowedServices, true)) {
    fail('service');
}

if (
    mb_strlen($name) > 150 ||
    mb_strlen($postalCode) > 30 ||
    mb_strlen($city) > 150 ||
    mb_strlen($phone) > 80 ||
    mb_strlen($message) > 5000
) {
    fail('invalid');
}


/*
|--------------------------------------------------------------------------
| Attachments
|--------------------------------------------------------------------------
*/

$attachments = [];

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$maxFiles = 5;
$maxFileSize = 5 * 1024 * 1024;
$maxTotalSize = 15 * 1024 * 1024;

$totalSize = 0;

if (
    isset($_FILES['photos']) &&
    is_array($_FILES['photos']['name'])
) {
    $fileCount = count($_FILES['photos']['name']);

    if ($fileCount > $maxFiles) {
        fail('files');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < $fileCount; $i++) {
        $error = $_FILES['photos']['error'][$i];

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            fail('upload');
        }

        $tmpName = $_FILES['photos']['tmp_name'][$i];
        $size = (int) $_FILES['photos']['size'][$i];

        if ($size <= 0 || $size > $maxFileSize) {
            fail('filesize');
        }

        $totalSize += $size;

        if ($totalSize > $maxTotalSize) {
            fail('filesize');
        }

        $mime = $finfo->file($tmpName);

        if (
            $mime === false ||
            !array_key_exists($mime, $allowedMimeTypes)
        ) {
            fail('filetype');
        }

        $attachments[] = [
            'path' => $tmpName,
            'name' => sprintf(
                'projektfoto-%02d.%s',
                $i + 1,
                $allowedMimeTypes[$mime]
            ),
        ];
    }
}


/*
|--------------------------------------------------------------------------
| E-mail body
|--------------------------------------------------------------------------
*/

$body = '
<h2>Neue Projektanfrage</h2>

<p><strong>Leistung:</strong> ' . safe($service) . '</p>

<p>
    <strong>Name:</strong> ' . safe($name) . '<br>
    <strong>Telefon:</strong> ' . safe($phone) . '<br>
    <strong>E-Mail:</strong> ' . safe($email) . '<br>
    <strong>Bevorzugter Kontakt:</strong> ' . safe($preferredContact) . '
</p>

<p>
    <strong>Montageort:</strong><br>
    ' . safe($postalCode) . ' ' . safe($city) . '
</p>

<p>
    <strong>Projektbeschreibung:</strong><br><br>
    ' . nl2br(safe($message)) . '
</p>
';


/*
|--------------------------------------------------------------------------
| Send
|--------------------------------------------------------------------------
*/

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();

    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;

    $mail->Username = $config['username'];
    $mail->Password = $config['password'];

    $mail->Port = (int) $config['port'];

    if ($config['encryption'] === 'smtps') {
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_SMTPS;
    }

    if ($config['encryption'] === 'starttls') {
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        $config['from_email'],
        $config['from_name']
    );

    $mail->addAddress(
        $config['to_email'],
        $config['to_name']
    );

    $mail->addReplyTo(
        $email,
        $name
    );

    foreach ($attachments as $attachment) {
        $mail->addAttachment(
            $attachment['path'],
            $attachment['name']
        );
    }

    $mail->isHTML(true);

    $mail->Subject =
        'Neue Projektanfrage – ' . $service;

    $mail->Body = $body;

    $mail->AltBody =
        "Neue Projektanfrage\n\n" .
        "Leistung: {$service}\n" .
        "Name: {$name}\n" .
        "Telefon: {$phone}\n" .
        "E-Mail: {$email}\n" .
        "Ort: {$postalCode} {$city}\n\n" .
        "Projektbeschreibung:\n{$message}";

    $mail->send();

    header('Location: /danke', true, 303);
    exit;

} catch (Exception $e) {
    error_log(
        'Montageservice Finta mail error: ' .
        $mail->ErrorInfo
    );

    fail('send');
}