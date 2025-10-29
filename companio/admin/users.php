<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { header("Location: /companio/login.php"); exit; }
require_once __DIR__.'/../includes/db.php';

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { die('Invalid CSRF'); }
  $uid = (int)($_POST['user_id'] ?? 0);

  if (isset($_POST['suspend'])) {
    $stmt = $db->prepare("UPDATE users SET is_verified=0 WHERE user_id=? AND role<>'admin'");
    $stmt->bind_param('i', $uid); $stmt->execute();
  }
  if (isset($_POST['reactivate'])) {
    $stmt = $db->prepare("UPDATE users SET is_verified=1 WHERE user_id=?");
    $stmt->bind_param('i', $uid); $stmt->execute();
  }
  header("Location: users.php"); exit;
}

$res = $db->query("SELECT user_id, first_name, last_name, email, role, is_verified, created_at FROM users ORDER BY created_at DESC");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><title>Manage Users</title>
  <link rel="stylesheet" href="../assets/companio.css">
  <style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7}</style>
</head>
<body>
<header>
  <nav>
    <div class="logo">🌍 Companio — Admin</div>
    <div class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="users.php">Users</a>
      <a href="reviews.php">Reviews</a>
      <a href="bookings.php">Bookings</a>
      <a href="../logout.php">Sign Out</a>
    </div>
  </nav>
</header>

<main class="container">
  <h1>Users</h1>
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Verified</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if($res && $res->num_rows): while($r=$res->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$r['user_id'] ?></td>
        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= htmlspecialchars($r['role']) ?></td>
        <td><?= $r['is_verified'] ? 'Yes' : 'No' ?></td>
        <td>
          <?php if ($r['role']!=='admin'): ?>
            <?php if ($r['is_verified']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                <button name="suspend">Suspend</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                <button name="reactivate">Reactivate</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="6">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>
</body>
</html>
