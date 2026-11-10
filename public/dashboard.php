<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth.php';
require_login();
date_default_timezone_set('Africa/Kinshasa');

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Actions (admin uniquement, protégées par CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['nouvelle', 'en_cours', 'resolue'], true)) {
            $pdo->prepare('UPDATE alerts SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    } elseif ($action === 'block_ip') {
        $ip = (string)($_POST['ip'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $pdo->prepare(
                'INSERT INTO blocked_ips (ip_address, reason) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
            )->execute([$ip, 'Bloquée manuellement par ' . $_SESSION['username']]);
        }
    }
    header('Location: dashboard.php');
    exit;
}

// Statistiques
$failed24  = (int)$pdo->query("SELECT COUNT(*) FROM logs WHERE event_type='login_failed' AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
$success24 = (int)$pdo->query("SELECT COUNT(*) FROM logs WHERE event_type='login_success' AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
$openAlerts = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE status <> 'resolue'")->fetchColumn();
$blocked    = (int)$pdo->query('SELECT COUNT(*) FROM blocked_ips')->fetchColumn();

$topIps = $pdo->query(
    "SELECT ip_address, COUNT(*) AS c, MAX(created_at) AS last_seen
     FROM logs WHERE event_type='login_failed' AND created_at >= NOW() - INTERVAL 1 DAY
     GROUP BY ip_address ORDER BY c DESC LIMIT 5"
)->fetchAll();

$alerts = $pdo->query(
    'SELECT id, ip_address, severity, message, status, created_at
     FROM alerts ORDER BY created_at DESC LIMIT 10'
)->fetchAll();

// Échecs par jour sur 7 jours (jours sans événement = 0)
$rows = $pdo->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM logs
     WHERE event_type='login_failed' AND created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY d"
)->fetchAll();
$byDay = array_column($rows, 'c', 'd');
$labels = [];
$values = [];
for ($i = 6; $i >= 0; $i--) {
    $day      = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($day));
    $values[] = (int)($byDay[$day] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - SecDash</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dash">
  <header class="topbar">
    <div>
      <strong>SecDash</strong>
      <span class="sub"><?= e($_SESSION['username']) ?> (<?= e($_SESSION['role']) ?>)</span>
    </div>
    <form method="post" action="logout.php">
      <?= csrf_field() ?>
      <button type="submit" class="btn-small">Se déconnecter</button>
    </form>
  </header>

  <main class="wrap">
    <section class="stats">
      <div class="stat"><span><?= $failed24 ?></span>Échecs (24 h)</div>
      <div class="stat"><span><?= $success24 ?></span>Connexions réussies (24 h)</div>
      <div class="stat"><span><?= $openAlerts ?></span>Alertes ouvertes</div>
      <div class="stat"><span><?= $blocked ?></span>IP bloquées</div>
    </section>

    <section class="panel">
      <h2>Échecs de connexion, 7 derniers jours</h2>
      <canvas id="chart-failed" height="110"
              data-labels="<?= e(json_encode($labels)) ?>"
              data-values="<?= e(json_encode($values)) ?>"></canvas>
    </section>

    <section class="panel">
      <h2>IP les plus actives (échecs, 24 h)</h2>
      <?php if (!$topIps): ?>
        <p class="sub">Aucun échec enregistré.</p>
      <?php else: ?>
      <table>
        <tr><th>IP</th><th>Échecs</th><th>Dernier</th></tr>
        <?php foreach ($topIps as $r): ?>
          <tr>
            <td><?= e($r['ip_address']) ?></td>
            <td><?= (int)$r['c'] ?></td>
            <td><?= e((string)$r['last_seen']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Dernières alertes</h2>
      <?php if (!$alerts): ?>
        <p class="sub">Aucune alerte.</p>
      <?php else: ?>
      <div class="scroll">
      <table>
        <tr><th>Date</th><th>IP</th><th>Gravité</th><th>Message</th><th>Statut</th><?php if ($isAdmin): ?><th>Actions</th><?php endif; ?></tr>
        <?php foreach ($alerts as $a): ?>
          <tr>
            <td><?= e((string)$a['created_at']) ?></td>
            <td><?= e($a['ip_address']) ?></td>
            <td><span class="badge <?= e($a['severity']) ?>"><?= e($a['severity']) ?></span></td>
            <td><?= e((string)$a['message']) ?></td>
            <td><?= e($a['status']) ?></td>
            <?php if ($isAdmin): ?>
            <td class="actions">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <select name="status">
                  <?php foreach (['nouvelle', 'en_cours', 'resolue'] as $s): ?>
                    <option value="<?= $s ?>"<?= $a['status'] === $s ? ' selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn-small">OK</button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="block_ip">
                <input type="hidden" name="ip" value="<?= e($a['ip_address']) ?>">
                <button class="btn-small danger">Bloquer l'IP</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="assets/js/chart.umd.min.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>