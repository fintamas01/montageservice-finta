<?php

declare(strict_types=1);

/*
 * Tests für backend/request-validation.php (ohne Framework).
 * Ausführen: php backend/tests/validation-test.php
 */

require dirname(__DIR__) . '/request-validation.php';

$passed = 0;
$failed = 0;

function check(string $name, callable $test): void
{
    global $passed, $failed;

    try {
        $test();
        $passed++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ {$name}: {$e->getMessage()}\n";
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function expect_error(string $code, callable $fn): void
{
    try {
        $fn();
    } catch (RequestValidationError $e) {
        assert_same($code, $e->errorCode, 'error code');
        return;
    }

    throw new RuntimeException("expected error '{$code}', none thrown");
}

$now = 1_800_000_000;

$valid = [
    'service' => 'Küchenmontage',
    'postal_code' => '89597',
    'city' => 'Munderkingen',
    'message' => "Neue Küche aufbauen.\nZweite Zeile.",
    'name' => 'Max Mustermann',
    'phone' => '+49 (0)151 / 123-456',
    'email' => 'max@example.de',
    'preferred_contact' => 'WhatsApp',
    'privacy' => 'accepted',
    'website' => '',
    'form_ts' => (string) ($now - 60),
];

echo "validate_request\n";

check('accepts a valid request', function () use ($valid, $now) {
    $data = validate_request($valid, $now);
    assert_same('Küchenmontage', $data['service']);
    assert_same("Neue Küche aufbauen.\nZweite Zeile.", $data['message']);
    assert_same('WhatsApp', $data['preferred_contact']);
});

check('works without JS timestamp', function () use ($valid, $now) {
    validate_request(['form_ts' => ''] + $valid, $now);
});

check('rejects honeypot', fn () => expect_error('spam', fn () => validate_request(['website' => 'http://spam'] + $valid, $now)));
check('rejects too-fast submissions', fn () => expect_error('spam', fn () => validate_request(['form_ts' => (string) ($now - 1)] + $valid, $now)));
check('rejects non-numeric timestamp', fn () => expect_error('spam', fn () => validate_request(['form_ts' => 'abc'] + $valid, $now)));

foreach (['service', 'postal_code', 'city', 'message', 'name', 'phone', 'email'] as $field) {
    check("requires {$field}", fn () => expect_error('missing', fn () => validate_request([$field => '  '] + $valid, $now)));
}

check('rejects array injection', fn () => expect_error('missing', fn () => validate_request(['name' => ['x']] + $valid, $now)));
check('rejects unknown service', fn () => expect_error('service', fn () => validate_request(['service' => 'Hacking'] + $valid, $now)));
check('rejects invalid postal code', fn () => expect_error('postal_code', fn () => validate_request(['postal_code' => '12a45'] + $valid, $now)));
check('accepts 4-digit postal code', fn () => validate_request(['postal_code' => '1010'] + $valid, $now));
check('rejects invalid phone', fn () => expect_error('phone', fn () => validate_request(['phone' => 'call me'] + $valid, $now)));
check('rejects invalid email', fn () => expect_error('email', fn () => validate_request(['email' => 'max@'] + $valid, $now)));
check('rejects header injection in email', fn () => expect_error('email', fn () => validate_request(['email' => "a@b.de\r\nBcc: x@y.de"] + $valid, $now)));
check('requires privacy consent', fn () => expect_error('privacy', fn () => validate_request(['privacy' => ''] + $valid, $now)));
check('rejects overlong message', fn () => expect_error('invalid', fn () => validate_request(['message' => str_repeat('a', 5001)] + $valid, $now)));

check('strips line breaks from single-line fields', function () use ($valid, $now) {
    $data = validate_request(['name' => "Max\r\nBcc: evil@example.com"] + $valid, $now);
    assert_same(false, str_contains($data['name'], "\n"));
    assert_same(false, str_contains($data['name'], "\r"));
});

check('falls back for unknown contact preference', function () use ($valid, $now) {
    assert_same('Telefon', validate_request(['preferred_contact' => 'Fax'] + $valid, $now)['preferred_contact']);
});

echo "validate_uploads\n";

$jpeg = fn (string $path): string => 'image/jpeg';
$file = fn (array $overrides = []) => array_merge(['name' => 'a.jpg', 'tmp_name' => '/tmp/a', 'size' => 1000, 'error' => UPLOAD_ERR_OK], $overrides);
$files = function (array ...$entries): array {
    $result = ['name' => [], 'tmp_name' => [], 'size' => [], 'error' => []];
    foreach ($entries as $entry) {
        foreach ($result as $key => $_) {
            $result[$key][] = $entry[$key];
        }
    }
    return $result;
};

check('no files is fine', fn () => assert_same([], validate_uploads(null, $jpeg)));
check('skips empty file slots', fn () => assert_same([], validate_uploads($files($file(['error' => UPLOAD_ERR_NO_FILE])), $jpeg)));

check('accepts valid images with safe names', function () use ($files, $file, $jpeg) {
    $result = validate_uploads($files($file(['name' => '../../evil.php']), $file()), $jpeg);
    assert_same('projektfoto-01.jpg', $result[0]['name']);
    assert_same('projektfoto-02.jpg', $result[1]['name']);
});

check('rejects more than 5 files', fn () => expect_error('files', fn () => validate_uploads($files($file(), $file(), $file(), $file(), $file(), $file()), $jpeg)));
check('rejects oversized file', fn () => expect_error('filesize', fn () => validate_uploads($files($file(['size' => 6 * 1024 * 1024])), $jpeg)));
check('rejects oversized total', fn () => expect_error('filesize', fn () => validate_uploads($files(...array_fill(0, 4, $file(['size' => 4 * 1024 * 1024]))), $jpeg)));
check('rejects ini size error', fn () => expect_error('filesize', fn () => validate_uploads($files($file(['error' => UPLOAD_ERR_INI_SIZE])), $jpeg)));
check('rejects non-image mime', fn () => expect_error('filetype', fn () => validate_uploads($files($file()), fn () => 'application/x-php')));
check('rejects undetectable mime', fn () => expect_error('filetype', fn () => validate_uploads($files($file()), fn () => false)));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
