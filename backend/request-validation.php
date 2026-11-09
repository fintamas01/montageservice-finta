<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validierung der Projektanfrage (ohne Seiteneffekte, testbar)
|--------------------------------------------------------------------------
|
| Limits müssen mit src/data/form.ts übereinstimmen.
|
*/

const REQUEST_ALLOWED_SERVICES = [
    'Küchenmontage',
    'Möbelmontage',
    'Türen und Zargen',
    'Sonstiges',
];

const REQUEST_ALLOWED_CONTACT = ['Telefon', 'WhatsApp', 'E-Mail'];

const REQUEST_UPLOAD_LIMITS = [
    'max_files' => 5,
    'max_file_size' => 5 * 1024 * 1024,
    'max_total_size' => 15 * 1024 * 1024,
    'mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];

/** Mindestzeit (Sekunden) zwischen Seitenaufruf und Absenden. */
const REQUEST_MIN_FILL_SECONDS = 3;

final class RequestValidationError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly ?string $field = null,
    ) {
        parent::__construct($errorCode);
    }
}

/** Liest ein Feld als einzeiligen, getrimmten String (ohne Steuerzeichen). */
function request_line(array $input, string $name): string
{
    $value = $input[$name] ?? '';

    if (!is_string($value)) {
        return '';
    }

    // Zeilenumbrüche/Steuerzeichen entfernen – schützt u. a. vor Header-Injection.
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

    return trim($value);
}

/** Liest ein mehrzeiliges Textfeld; erlaubt nur \n als Steuerzeichen. */
function request_text(array $input, string $name): string
{
    $value = $input[$name] ?? '';

    if (!is_string($value)) {
        return '';
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $value) ?? '';

    return trim($value);
}

/**
 * Prüft die Formulardaten.
 *
 * @return array<string, string> bereinigte Daten
 * @throws RequestValidationError
 */
function validate_request(array $input, ?int $now = null): array
{
    $now ??= time();

    // Spam: Honeypot
    if (request_line($input, 'website') !== '') {
        throw new RequestValidationError('spam');
    }

    // Spam: Zeitfalle (nur wenn der Zeitstempel per JS gesetzt wurde)
    $timestamp = request_line($input, 'form_ts');
    if ($timestamp !== '') {
        if (!ctype_digit($timestamp)) {
            throw new RequestValidationError('spam');
        }

        $elapsed = $now - (int) $timestamp;
        if ($elapsed < REQUEST_MIN_FILL_SECONDS) {
            throw new RequestValidationError('spam');
        }
    }

    foreach (['service', 'postal_code', 'city', 'message', 'name', 'phone', 'email'] as $field) {
        if (!is_string($input[$field] ?? null) || trim($input[$field]) === '') {
            throw new RequestValidationError('missing', $field === 'service' ? 'service' : $field);
        }
    }

    $data = [
        'service' => request_line($input, 'service'),
        'postal_code' => request_line($input, 'postal_code'),
        'city' => request_line($input, 'city'),
        'message' => request_text($input, 'message'),
        'name' => request_line($input, 'name'),
        'phone' => request_line($input, 'phone'),
        'email' => request_line($input, 'email'),
        'preferred_contact' => request_line($input, 'preferred_contact'),
    ];

    if (!in_array($data['service'], REQUEST_ALLOWED_SERVICES, true)) {
        throw new RequestValidationError('service', 'service');
    }

    if (!preg_match('/^[0-9]{4,5}$/', $data['postal_code'])) {
        throw new RequestValidationError('postal_code', 'postal_code');
    }

    if (!preg_match('/^[0-9+()\/ \-]{6,30}$/', $data['phone'])) {
        throw new RequestValidationError('phone', 'phone');
    }

    if (mb_strlen($data['email']) > 254 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new RequestValidationError('email', 'email');
    }

    $maxLengths = ['name' => 150, 'city' => 150, 'message' => 5000];
    foreach ($maxLengths as $field => $max) {
        if (mb_strlen($data[$field]) > $max) {
            throw new RequestValidationError('invalid', $field);
        }
    }

    if (!in_array($data['preferred_contact'], REQUEST_ALLOWED_CONTACT, true)) {
        $data['preferred_contact'] = 'Telefon';
    }

    if (($input['privacy'] ?? '') !== 'accepted') {
        throw new RequestValidationError('privacy', 'privacy');
    }

    return $data;
}

/**
 * Prüft hochgeladene Fotos.
 *
 * @param array<string, mixed>|null $files Eintrag $_FILES['photos']
 * @param callable(string): (string|false) $detectMime
 * @return list<array{path: string, name: string}>
 * @throws RequestValidationError
 */
function validate_uploads(?array $files, callable $detectMime): array
{
    if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $limits = REQUEST_UPLOAD_LIMITS;
    $count = count($files['name']);
    $attachments = [];
    $totalSize = 0;

    for ($i = 0; $i < $count; $i++) {
        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (count($attachments) >= $limits['max_files']) {
            throw new RequestValidationError('files', 'photos');
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RequestValidationError('filesize', 'photos');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RequestValidationError('upload', 'photos');
        }

        $size = (int) ($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > $limits['max_file_size']) {
            throw new RequestValidationError('filesize', 'photos');
        }

        $totalSize += $size;
        if ($totalSize > $limits['max_total_size']) {
            throw new RequestValidationError('filesize', 'photos');
        }

        $tmpName = (string) ($files['tmp_name'][$i] ?? '');
        $mime = $detectMime($tmpName);

        if ($mime === false || !array_key_exists($mime, $limits['mime_types'])) {
            throw new RequestValidationError('filetype', 'photos');
        }

        $attachments[] = [
            'path' => $tmpName,
            'name' => sprintf('projektfoto-%02d.%s', count($attachments) + 1, $limits['mime_types'][$mime]),
        ];
    }

    return $attachments;
}
