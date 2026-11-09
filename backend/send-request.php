<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/request-validation.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/*
|--------------------------------------------------------------------------
| Antworten
|--------------------------------------------------------------------------
| Mit JavaScript sendet das Formular per fetch() und erwartet JSON.
| Ohne JavaScript erfolgt eine klassische Weiterleitung (PRG-Muster).
*/

function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function respond_success(): never
{
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'redirect' => '/danke']);
        exit;
    }

    header('Location: /danke', true, 303);
    exit;
}

function respond_error(string $code, int $status = 422, ?string $field = null): never
{
    if (wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'code' => $code, 'field' => $field]);
        exit;
    }

    header('Location: /anfrage?status=' . rawurlencode($code), true, 303);
    exit;
}

function safe(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Nur POST
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

// Upload größer als post_max_size: PHP verwirft dann den gesamten Request.
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond_error('filesize', 413, 'photos');
}

/*
|--------------------------------------------------------------------------
| Konfiguration
|--------------------------------------------------------------------------
| Empfohlen: mail-config.php AUSSERHALB des Web-Verzeichnisses ablegen,
| z. B. /home/<user>/mail-config.php neben public_html/.
*/

$configPaths = [
    dirname(__DIR__, 2) . '/mail-config.php', // außerhalb des Webroots (empfohlen)
    dirname(__DIR__) . '/mail-config.php',    // lokale Entwicklung / Fallback
];

$config = null;

foreach ($configPaths as $path) {
    if (is_file($path)) {
        $config = require $path;
        break;
    }
}

if (!is_array($config)) {
    error_log('Montageservice Finta: mail-config.php not found.');
    respond_error('send', 500);
}

/*
|--------------------------------------------------------------------------
| Rate-Limit (gehashte IP, temporäre Datei)
|--------------------------------------------------------------------------
*/

function rate_limit_exceeded(array $config): bool
{
    $maxRequests = (int) ($config['rate_limit_max'] ?? 5);
    $window = (int) ($config['rate_limit_window'] ?? 900);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $salt = (string) ($config['rate_limit_salt'] ?? 'montageservice-finta');

    $file = sys_get_temp_dir() . '/msf-rate-' . hash('sha256', $salt . $ip) . '.json';
    $now = time();

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return false; // im Zweifel nicht blockieren
    }

    try {
        flock($handle, LOCK_EX);
        $content = stream_get_contents($handle);
        $timestamps = json_decode($content !== false && $content !== '' ? $content : '[]', true);
        $timestamps = array_values(array_filter(
            is_array($timestamps) ? $timestamps : [],
            static fn ($t): bool => is_int($t) && $t > $now - $window,
        ));

        if (count($timestamps) >= $maxRequests) {
            return true;
        }

        $timestamps[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($timestamps));

        return false;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/*
|--------------------------------------------------------------------------
| Validierung
|--------------------------------------------------------------------------
*/

try {
    $data = validate_request($_POST);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $attachments = validate_uploads(
        isset($_FILES['photos']) && is_array($_FILES['photos']) ? $_FILES['photos'] : null,
        static fn (string $path): string|false => is_uploaded_file($path) ? $finfo->file($path) : false,
    );
} catch (RequestValidationError $error) {
    if ($error->errorCode === 'spam') {
        // Bots keinen Hinweis geben – scheinbarer Erfolg ohne Versand.
        respond_success();
    }

    respond_error($error->errorCode, 422, $error->field);
}

if (rate_limit_exceeded($config)) {
    respond_error('rate', 429);
}

/*
|--------------------------------------------------------------------------
| E-Mail
|--------------------------------------------------------------------------
*/

$rows = [
    'Leistung' => $data['service'],
    'Name' => $data['name'],
    'Telefon' => $data['phone'],
    'E-Mail' => $data['email'],
    'Bevorzugter Kontakt' => $data['preferred_contact'],
    'Montageort' => $data['postal_code'] . ' ' . $data['city'],
    'Fotos' => (string) count($attachments),
];

$htmlRows = '';
$textRows = '';

foreach ($rows as $label => $value) {
    $htmlRows .= '<tr><th align="left" style="padding:4px 16px 4px 0">' . safe($label)
        . '</th><td style="padding:4px 0">' . safe($value) . '</td></tr>';
    $textRows .= $label . ': ' . $value . "\n";
}

$body = '<h2>Neue Projektanfrage</h2>'
    . '<table cellspacing="0" cellpadding="0">' . $htmlRows . '</table>'
    . '<h3>Projektbeschreibung</h3><p>' . nl2br(safe($data['message'])) . '</p>';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = (string) $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $config['username'];
    $mail->Password = (string) $config['password'];
    $mail->Port = (int) $config['port'];
    $mail->Timeout = 20;

    $encryption = $config['encryption'] ?? 'smtps';
    $mail->SMTPSecure = match ($encryption) {
        'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
        'none' => '', // nur für lokale Test-Mailserver
        default => PHPMailer::ENCRYPTION_SMTPS,
    };
    $mail->SMTPAutoTLS = $encryption !== 'none';

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
    $mail->addAddress((string) $config['to_email'], (string) $config['to_name']);
    $mail->addReplyTo($data['email'], $data['name']);

    foreach ($attachments as $attachment) {
        $mail->addAttachment($attachment['path'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['mime']);
    }

    $mail->isHTML(true);
    $mail->Subject = 'Neue Projektanfrage – ' . $data['service'] . ' – ' . $data['postal_code'] . ' ' . $data['city'];
    $mail->Body = $body;
    $mail->AltBody = "Neue Projektanfrage\n\n" . $textRows . "\nProjektbeschreibung:\n" . $data['message'];

    $mail->send();
} catch (MailException $exception) {
    error_log('Montageservice Finta mail error: ' . $mail->ErrorInfo);
    respond_error('send', 502);
}

respond_success();
