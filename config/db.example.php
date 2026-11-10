<?php
// Copiez ce fichier en config/db.php et renseignez vos valeurs.
// config/db.php est ignoré par Git : ne le publiez jamais.
declare(strict_types=1);

$host = 'localhost';
$db   = 'secdash';
$user = 'secdash_user';
$pass = 'CHANGER_CE_MOT_DE_PASSE';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Erreur de connexion à la base de données.');
}
