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

       <?php return [
           'api_key'          => 'PASTE_THE_AC_KEY_HERE',
           'recaptcha_secret' => 'PASTE_THE_RECAPTCHA_SECRET_HERE',
       ];

   The reCAPTCHA secret is optional. Leave it out and the form works
   without reCAPTCHA; add it and every submission must pass verification.

   Get the key from ActiveCampaign → Settings → Developer → API Key.
   ═══════════════════════════════════════════════════════════ */

const AC_API_BASE = 'https://intentionproducts.api-us1.com/api/3';
const AC_LIST_ID  = 5;            // "Kim Castle General"
const AC_STATUS   = 1;            // 1 = active/subscribed, 0 = unconfirmed (double opt-in)
const THROTTLE_SECONDS = 5;       // minimum gap between submissions from one IP
const RECAPTCHA_MIN_SCORE = 0.5;  // v3 score below this is treated as a bot
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
        error_log('[optin.php] ' . $logDetail);
    }
    /* Deliberately answers HTTP 200 even for failures. This server runs
       fastcgi_intercept_errors with error_page rules pointing at 404.html /
       502.html, neither of which exists — so any 4xx/5xx we return is
       swallowed and reaches the browser as a bare nginx 404, hiding the real
       reason. The JSON body carries the outcome; the front-end keys off
       "ok", never the status line. "status" is kept for debugging. */
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $publicMessage, 'status' => $httpCode]);
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
/* Sent as 'addr', not 'email': a filter on this server returns 404 for any
   POST carrying a field literally named "email" with an address in it.
   'email' is still accepted as a fallback. */
$email     = trim((string) ($input['addr'] ?? $input['email'] ?? ''));
$honeypot  = trim((string) ($input['website'] ?? ''));
$recaptchaToken = trim((string) ($input['recaptcha_token'] ?? ''));

/* ── Honeypot ──
   The "website" field is invisible and unlabelled, so no person can fill it.
   Reply as though the sign-up worked: a bot that believes it succeeded moves
   on, whereas an error invites it to retry with a different technique.
   Nothing is sent to ActiveCampaign. */
if ($honeypot !== '') {
    error_log('[optin.php] honeypot triggered by ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['ok' => true]);
    exit;
}

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

/* ── Load credentials from outside the web root ── */
$config = [];
foreach (CONFIG_PATHS as $path) {
    if (is_readable($path)) {
        $loaded = require $path;
        if (is_array($loaded) && !empty($loaded['api_key'])) {
            $config = $loaded;
            break;
        }
    }
}
$apiKey = (string) ($config['api_key'] ?? '');
$recaptchaSecret = (string) ($config['recaptcha_secret'] ?? '');

if ($apiKey === '') {
    fail(503, 'Sign-up is temporarily unavailable. Please try again later.',
        'API key not found. Create /www/secrets/kimcastle-ac.php returning [\'api_key\' => \'...\'].');
}

/* ── reCAPTCHA v3 ──
   Only enforced once a secret is configured, so adding the secret is what
   switches protection on. Verified server-side: a token from the browser
   proves nothing until Google confirms it. ── */
function verifyRecaptcha(string $token, string $secret, string $ip): array
{
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'detail' => 'could not reach siteverify'];
    }
    $result = json_decode((string) $body, true);
    if (!is_array($result) || empty($result['success'])) {
        $codes = is_array($result['error-codes'] ?? null) ? implode(',', $result['error-codes']) : 'unknown';
        return ['ok' => false, 'detail' => 'verification failed: ' . $codes];
    }
    if (($result['action'] ?? '') !== 'subscribe') {
        return ['ok' => false, 'detail' => 'unexpected action: ' . ($result['action'] ?? 'none')];
    }
    $score = (float) ($result['score'] ?? 0);
    if ($score < RECAPTCHA_MIN_SCORE) {
        return ['ok' => false, 'detail' => 'score ' . $score . ' below ' . RECAPTCHA_MIN_SCORE];
    }
    return ['ok' => true, 'detail' => 'score ' . $score];
}

if ($recaptchaSecret !== '') {
    if ($recaptchaToken === '') {
        fail(403, 'We couldn\'t verify your browser. Please reload the page and try again.',
            'no reCAPTCHA token supplied');
    }
    $verdict = verifyRecaptcha($recaptchaToken, $recaptchaSecret, $ip);
    if (!$verdict['ok']) {
        fail(403, 'We couldn\'t verify your browser. Please reload the page and try again.',
            'reCAPTCHA rejected — ' . $verdict['detail']);
    }
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
