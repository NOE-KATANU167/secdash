<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'method_not_allowed']);
}

// 1. Authentification par clé (avant tout accès à la base)
$secrets = require __DIR__ . '/../../config/secrets.php';
$key     = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($key === '' || !hash_equals($secrets['ingest_key'], $key)) {
    respond(401, ['error' => 'unauthorized']);
}

// 2. Lecture limitée du corps (10 Ko max)
$raw = file_get_contents('php://input', false, null, 0, 10241);
if ($raw === false || strlen($raw) > 10240) {
    respond(413, ['error' => 'payload_too_large']);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    respond(400, ['error' => 'invalid_json']);
}

// 3. Validation stricte
$allowed = ['login_success', 'login_failed', 'access_denied', 'other'];
$type    = $data['event_type'] ?? '';
if (!is_string($type) || !in_array($type, $allowed, true)) {
    respond(422, ['error' => 'invalid_event_type']);
}

$ip = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
    respond(422, ['error' => 'invalid_ip']);
}

$username = (isset($data['username']) && is_string($data['username']))
    ? mb_substr($data['username'], 0, 50) : null;
$ua = (isset($data['user_agent']) && is_string($data['user_agent']))
    ? mb_substr($data['user_agent'], 0, 255) : null;

// 4. Enregistrement + détection
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../includes/detector.php';

$pdo->prepare(
    'INSERT INTO logs (event_type, username, ip_address, user_agent) VALUES (?, ?, ?, ?)'
)->execute([$type, $username, $ip, $ua]);

if ($type === 'login_failed') {
    run_detection($pdo, $ip);
}

respond(201, ['status' => 'ok']);