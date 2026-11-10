<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/detector.php';

const MAX_ATTEMPTS = 5;     // échecs avant verrouillage
const LOCK_MINUTES = 15;    // durée du verrouillage
const IDLE_TIMEOUT = 1800;  // 30 min d'inactivité

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'");
}

function client_ip(): string
{
    // REMOTE_ADDR uniquement : X-Forwarded-For peut être falsifié
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_event(PDO $pdo, string $type, ?string $username): void
{
    $st = $pdo->prepare(
        'INSERT INTO logs (event_type, username, ip_address, user_agent)
         VALUES (?, ?, ?, ?)'
    );
    $st->execute([
        $type,
        $username !== null ? mb_substr($username, 0, 50) : null,
        client_ip(),
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function attempt_login(PDO $pdo, string $username, string $password): bool
{
    if (is_ip_blocked($pdo, client_ip())) {
        log_event($pdo, 'access_denied', $username);
        return false;
    }
    $st = $pdo->prepare(
        'SELECT id, username, password_hash, role, failed_attempts,
                (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked
         FROM users WHERE username = ?'
    );
    $st->execute([$username]);
    $user = $st->fetch();

    // Hash factice : le temps de réponse reste similaire si l'utilisateur n'existe pas
    $hash = $user['password_hash']
        ?? '$2y$10$usesomesillystringforeusesomesillystringfore.Jp3d2zQeQ6dJm';
    $ok = password_verify($password, $hash);

    if (!$user || $user['is_locked'] || !$ok) {
        log_event($pdo, 'login_failed', $username);
        run_detection($pdo, client_ip());

        if ($user && !$user['is_locked']) {
            $attempts = (int)$user['failed_attempts'] + 1;
            if ($attempts >= MAX_ATTEMPTS) {
                $pdo->prepare(
                    'UPDATE users SET failed_attempts = 0,
                     locked_until = DATE_ADD(NOW(), INTERVAL ' . (int)LOCK_MINUTES . ' MINUTE)
                     WHERE id = ?'
                )->execute([$user['id']]);
            } else {
                $pdo->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')
                    ->execute([$attempts, $user['id']]);
            }
        }
        return false;
    }

    // Succès
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([$user['id']]);

    session_regenerate_id(true); // évite la fixation de session
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['last_seen'] = time();

    log_event($pdo, 'login_success', $username);
    return true;
}

function require_login(): void
{
    start_secure_session();
    security_headers();

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if (time() - ($_SESSION['last_seen'] ?? 0) > IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_seen'] = time();
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}