<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth.php';
start_secure_session();
security_headers();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Session expirée, réessaie.';
    } elseif (attempt_login($pdo, $username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        // Message volontairement vague : ne révèle pas si le compte existe
        $error = 'Identifiants incorrects ou compte temporairement verrouillé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion - SecDash</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <main class="card">
    <h1>SecDash</h1>
    <p class="sub">Tableau de bord de sécurité</p>

    <?php if ($error): ?>
      <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Utilisateur
        <input type="text" name="username" required maxlength="50">
      </label>
      <label>Mot de passe
        <input type="password" name="password" required>
      </label>
      <button type="submit">Se connecter</button>
    </form>
  </main>
</body>
</html>