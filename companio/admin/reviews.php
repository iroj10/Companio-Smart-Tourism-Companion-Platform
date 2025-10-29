<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { header("Location: /companio/login.php"); exit; }
require_once __DIR__.'/../includes/db.php';

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_review_id'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { die('Invalid CSRF'); }
  $rid = (int)$_POST['delete_review_id'];
  $stmt = $db->prepare("DELETE FROM reviews WHERE review_id=?");
  $stmt->bind_param('i', $rid); $stmt->execute();
  header("Location: reviews.php"); exit;
}

$q = $db->query("
  SELECT r.review_id, r.rating, r.comment, r.review_date,
         t.first_name AS tourist_fn, t.last_name AS tourist_ln,
         g.first_name AS guide_fn,   g.last_name AS guide_ln
  FROM reviews r
  JOIN users t ON t.user_id=r.tourist_id
  JOIN users g ON g.user_id=r.guide_id
  ORDER BY r.review_date DESC
");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><title>Reviews</title>
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
  <h1>Reviews</h1>
  <table>
    <thead><tr><th>ID</th><th>Rating</th><th>Comment</th><th>Tourist</th><th>Guide</th><th>Date</th><th>Action</th></tr></thead>
    <tbody>
      <?php if($q && $q->num_rows): while($r=$q->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$r['review_id'] ?></td>
        <td><?= (int)$r['rating'] ?></td>
        <td><?= htmlspecialchars($r['comment']) ?></td>
        <td><?= htmlspecialchars($r['tourist_fn'].' '.$r['tourist_ln']) ?></td>
        <td><?= htmlspecialchars($r['guide_fn'].' '.$r['guide_ln']) ?></td>
        <td><?= htmlspecialchars($r['review_date']) ?></td>
        <td>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="delete_review_id" value="<?= (int)$r['review_id'] ?>">
            <button>Delete</button>
          </form>
        </td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="7">No reviews found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>
</body>
</html>
