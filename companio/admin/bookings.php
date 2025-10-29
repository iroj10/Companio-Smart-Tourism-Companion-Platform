<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { header("Location: /companio/login.php"); exit; }
require_once __DIR__.'/../includes/db.php';

$q = $db->query("
  SELECT b.booking_id, b.tour_date, b.hours, b.status,
         t.first_name AS tourist_fn, t.last_name AS tourist_ln,
         g.first_name AS guide_fn,   g.last_name AS guide_ln,
         COALESCE(p.amount,0) AS amount
  FROM bookings b
  JOIN users t ON t.user_id=b.tourist_id
  JOIN users g ON g.user_id=b.guide_id
  LEFT JOIN payments p ON p.booking_id=b.booking_id
  ORDER BY b.tour_date DESC
");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Bookings</title>
<link rel="stylesheet" href="../assets/companio.css">
<style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7}</style>
</head><body>
<header><nav><div class="logo">🌍 Companio — Admin</div><div class="nav-links"><a href="index.php">Dashboard</a><a href="users.php">Users</a><a href="reviews.php">Reviews</a><a href="bookings.php">Bookings</a><a href="../logout.php">Sign Out</a></div></nav></header>
<main class="container">
  <h1>Bookings</h1>
  <table>
    <thead><tr><th>ID</th><th>Tourist</th><th>Guide</th><th>Date</th><th>Hours</th><th>Status</th><th>Amount</th></tr></thead>
    <tbody>
      <?php if($q && $q->num_rows): while($r=$q->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$r['booking_id'] ?></td>
        <td><?= htmlspecialchars($r['tourist_fn'].' '.$r['tourist_ln']) ?></td>
        <td><?= htmlspecialchars($r['guide_fn'].' '.$r['guide_ln']) ?></td>
        <td><?= htmlspecialchars($r['tour_date']) ?></td>
        <td><?= (int)$r['hours'] ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= number_format((float)$r['amount'],2) ?></td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="7">No bookings found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>
</body></html>
