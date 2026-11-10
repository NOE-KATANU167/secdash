<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Accès interdit.');
}
require __DIR__ . '/../config/db.php';

echo 'Nom d\'utilisateur : ';
$username = trim((string)fgets(STDIN));
echo 'Mot de passe (12 caractères minimum) : ';
$password = rtrim((string)fgets(STDIN), "\r\n");

if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    exit("Nom invalide (3 à 50 caractères : lettres, chiffres, _ . -).\n");
}
if (strlen($password) < 12) {
    exit("Mot de passe trop court.\n");
}

$st = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
$st->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);

echo "Compte admin « $username » créé.\n";