<?php
declare(strict_types=1);

const DETECT_WINDOW_MIN = 10;  // fenêtre d'analyse
const DETECT_THRESHOLD  = 5;   // échecs avant alerte

function is_ip_blocked(PDO $pdo, string $ip): bool
{
    $st = $pdo->prepare('SELECT 1 FROM blocked_ips WHERE ip_address = ?');
    $st->execute([$ip]);
    return (bool)$st->fetchColumn();
}

function run_detection(PDO $pdo, string $ip): void
{
    $win = (int)DETECT_WINDOW_MIN;

    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM logs
         WHERE event_type = 'login_failed' AND ip_address = ?
           AND created_at >= NOW() - INTERVAL $win MINUTE"
    );
    $st->execute([$ip]);
    $count = (int)$st->fetchColumn();

    if ($count < DETECT_THRESHOLD) {
        return;
    }

    // Évite de créer une alerte par tentative : une seule alerte ouverte récente par IP
    $st = $pdo->prepare(
        "SELECT 1 FROM alerts
         WHERE ip_address = ? AND status <> 'resolue'
           AND created_at >= NOW() - INTERVAL $win MINUTE"
    );
    $st->execute([$ip]);
    if ($st->fetchColumn()) {
        return;
    }

    $severity = $count >= 10 ? 'haute' : 'moyenne';
    $message  = sprintf('%d échecs de connexion en %d minutes', $count, $win);

    $pdo->prepare('INSERT INTO alerts (ip_address, severity, message) VALUES (?, ?, ?)')
        ->execute([$ip, $severity, $message]);
}