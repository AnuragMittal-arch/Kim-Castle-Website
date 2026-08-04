<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════════════
   kimcastle.com — opt-in form → ActiveCampaign API v3

   Why this file exists:
   The browser must never see the ActiveCampaign API key. A key in
   js/script.js would be readable by every visitor and would grant
   full read/write access to EVERY list in the account. So the browser
   posts here, and this file — running on the server — holds the key.

   Setup (one time, on the droplet):
   Create the key file OUTSIDE the website folder, so the deploy's
   `rsync --delete` can never wipe it and the web server can never
   serve it:

       /www/secrets/kimcastle-ac.php

   containing exactly:

       <?php return ['api_key' => 'PASTE_THE_KEY_HERE'];

   Get the key from ActiveCampaign → Settings → Developer → API Key.
   ═══════════════════════════════════════════════════════════ */

const AC_API_BASE = 'https://intentionproducts.api-us1.com/api/3';
const AC_LIST_ID  = 5;            // "Kim Castle General"
const AC_STATUS   = 1;            // 1 = active/subscribed, 0 = unconfirmed (double opt-in)
const THROTTLE_SECONDS = 5;       // minimum gap between submissions from one IP
const MAX_FIELD_LENGTH = 200;

/* Candidate locations for the key file, first match wins. */
const CONFIG_PATHS = [
    '/www/secrets/kimcastle-ac.php',
    __DIR__ . '/../secrets/kimcastle-ac.php',
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $httpCode, string $publicMessage, string $logDetail = ''): never
{
    if ($logDetail !== '') {
        error_log('[subscribe.php] ' . $logDetail);
    }
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => $publicMessage]);
    exit;
}

/* ── Only POST ── */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail(405, 'Method not allowed.');
}

/* ── Read the submission (accepts JSON or form-encoded) ── */
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);
    if (!is_array($input)) {
        fail(400, 'Malformed request.');
    }
} else {
    $input = $_POST;
}

$firstName = trim((string) ($input['firstname'] ?? ''));
$lastName  = trim((string) ($input['lastname'] ?? ''));
$email     = trim((string) ($input['email'] ?? ''));

/* ── Validate (the browser checks too; never trust that) ── */
if ($firstName === '' || $lastName === '' || $email === '') {
    fail(422, 'Please fill in your name and email.');
}
if (strlen($firstName) > MAX_FIELD_LENGTH
    || strlen($lastName) > MAX_FIELD_LENGTH
    || strlen($email) > MAX_FIELD_LENGTH) {
    fail(422, 'That entry is too long.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail(422, 'That email address doesn\'t look right.');
}

/* ── Light throttle per IP. This endpoint writes to a live CRM and has
      no captcha in front of it, so slow down anything hammering it. ── */
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$stampFile = sys_get_temp_dir() . '/kc-optin-' . hash('sha256', $ip);
$now = time();
if (is_file($stampFile) && ($now - (int) @filemtime($stampFile)) < THROTTLE_SECONDS) {
    fail(429, 'Please wait a moment and try again.');
}
@touch($stampFile);

/* ── Load the API key from outside the web root ── */
$apiKey = '';
foreach (CONFIG_PATHS as $path) {
    if (is_readable($path)) {
        $config = require $path;
        $apiKey = is_array($config) ? (string) ($config['api_key'] ?? '') : '';
        if ($apiKey !== '') {
            break;
        }
    }
}
if ($apiKey === '') {
    fail(503, 'Sign-up is temporarily unavailable. Please try again later.',
        'API key not found. Create /www/secrets/kimcastle-ac.php returning [\'api_key\' => \'...\'].');
}

/* ── Call ActiveCampaign ── */
function acRequest(string $path, array $payload, string $apiKey): array
{
    $ch = curl_init(AC_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Api-Token: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['status' => 0, 'data' => null, 'error' => $curlError, 'raw' => ''];
    }
    return ['status' => $status, 'data' => json_decode((string) $body, true), 'error' => '', 'raw' => $body];
}

/* 1. Upsert the contact (contact/sync matches on email, so repeat
      sign-ups update rather than duplicate). */
$sync = acRequest('/contact/sync', [
    'contact' => [
        'email'     => $email,
        'firstName' => $firstName,
        'lastName'  => $lastName,
    ],
], $apiKey);

if ($sync['status'] === 0) {
    fail(502, 'Something went wrong on our end. Please try again.',
        'contact/sync transport error: ' . $sync['error']);
}
if ($sync['status'] < 200 || $sync['status'] >= 300) {
    fail(502, 'We couldn\'t sign you up. Please try again.',
        'contact/sync HTTP ' . $sync['status'] . ' body: ' . substr((string) $sync['raw'], 0, 500));
}

$contactId = $sync['data']['contact']['id'] ?? null;
if (!$contactId) {
    fail(502, 'We couldn\'t sign you up. Please try again.',
        'contact/sync returned no contact id: ' . substr((string) $sync['raw'], 0, 500));
}

/* 2. Subscribe them to the list. Without this the contact exists but
      receives nothing — the easiest way to silently lose sign-ups. */
$subscribe = acRequest('/contactLists', [
    'contactList' => [
        'list'    => AC_LIST_ID,
        'contact' => (int) $contactId,
        'status'  => AC_STATUS,
    ],
], $apiKey);

if ($subscribe['status'] === 0) {
    fail(502, 'Something went wrong on our end. Please try again.',
        'contactLists transport error: ' . $subscribe['error']);
}
if ($subscribe['status'] < 200 || $subscribe['status'] >= 300) {
    fail(502, 'We couldn\'t complete your sign-up. Please try again.',
        'contactLists HTTP ' . $subscribe['status'] . ' body: ' . substr((string) $subscribe['raw'], 0, 500));
}

echo json_encode(['ok' => true]);
