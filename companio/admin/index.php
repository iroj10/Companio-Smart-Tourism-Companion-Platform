<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: /companio/login.php"); exit;
}
require_once __DIR__.'/../includes/db.php';

// ---------- Data ----------
$users = $db->query("
  SELECT 
    SUM(role='tourist') AS tourists,
    SUM(role='guide')   AS guides,
    SUM(role='admin')   AS admins
  FROM users
")->fetch_assoc();

$bookings = $db->query("
  SELECT 
    SUM(status='pending')   AS pending,
    SUM(status='confirmed') AS confirmed,
    SUM(status='completed') AS completed,
    SUM(status='cancelled') AS cancelled
  FROM bookings
")->fetch_assoc();

$rev_res = $db->query("SELECT COALESCE(SUM(amount),0) AS total FROM payments");
$revenue = $rev_res ? (float)$rev_res->fetch_assoc()['total'] : 0.0;

// Unverified users (simple “needs attention” signal)
$unverified = $db->query("SELECT user_id, first_name, last_name, email, role FROM users WHERE is_verified=0 ORDER BY user_id DESC LIMIT 6");

// Recent bookings (last 6)
$recentBookings = $db->query("
  SELECT b.booking_id, b.tour_date, b.status,
         t.first_name AS tourist_fn, t.last_name AS tourist_ln,
         g.first_name AS guide_fn,   g.last_name AS guide_ln
  FROM bookings b
  JOIN users t ON t.user_id=b.tourist_id
  JOIN users g ON g.user_id=b.guide_id
  ORDER BY b.tour_date DESC
  LIMIT 6
");

// Helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard • Companio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../assets/companio.css">
  <style>
    :root{
      --bg: #0f172a;
      --bg-grad-1:#5b7cfa; --bg-grad-2:#7a3df2;
      --card:#ffffff; --muted:#667085; --text:#0f172a;
      --ring:#e5e7eb; --accent:#4f46e5; --accent-2:#06b6d4;
      --good:#16a34a; --warn:#d97706; --bad:#dc2626;
    }
    body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
    header{position:sticky;top:0;background:#fff;border-bottom:1px solid #eef0f4;z-index:50}
    nav{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:14px 18px}
    .logo{font-weight:700;font-size:1.1rem;display:flex;gap:10px;align-items:center}
    .logo::before{content:"🌍"}
    .nav-links a{margin-left:18px;color:#334155;text-decoration:none}
    .hero{
      background: radial-gradient(1200px 600px at 10% 10%, rgba(255,255,255,.25), transparent 50%),
                  linear-gradient(135deg,var(--bg-grad-1),var(--bg-grad-2));
      color:#fff; padding:48px 18px;
    }
    .hero-inner{max-width:1100px;margin:0 auto}
    .hello{opacity:.95;font-size:.95rem}
    h1{margin:.2rem 0 0;font-size:1.8rem}
    .kpis{max-width:1100px;margin:-28px auto 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;padding:0 18px}
    .card{
      background:var(--card); border:1px solid var(--ring); border-radius:16px; padding:18px; box-shadow:0 2px 14px rgba(2,6,23,.06);
    }
    .kpi .label{color:var(--muted);font-size:.85rem}
    .kpi .value{font-weight:700;font-size:1.4rem;margin-top:6px}
    .kpi .sub{font-size:.75rem;margin-top:6px;color:#64748b}
    .kpi .dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;vertical-align:middle}
    .dot.good{background:var(--good)} .dot.warn{background:var(--warn)} .dot.bad{background:var(--bad)}
    .main{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr;gap:16px;padding:0 18px 36px}
    .section-title{font-weight:700;margin-bottom:10px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid #eef0f4;text-align:left}
    th{font-size:.85rem;color:#475569}
    td{font-size:.92rem;color:#0f172a}
    .status{padding:4px 8px;border-radius:999px;font-size:.75rem;font-weight:600;display:inline-block}
    .status.pending{background:#fff7ed;color:#b45309;border:1px solid #fde68a}
    .status.confirmed{background:#ecfeff;color:#0e7490;border:1px solid #a5f3fc}
    .status.completed{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
    .status.cancelled{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--ring);border-radius:999px;font-size:.85rem;color:#334155;background:#fff}
    .stack{display:grid;gap:10px}
    .empty{color:#64748b;font-size:.9rem}
    .actions{display:flex;gap:10px;flex-wrap:wrap}
    .btn{
      display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;border:1px solid var(--ring);
      text-decoration:none;color:#111827;background:#fff;transition:.15s;
    }
    .btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(2,6,23,.08)}
    .btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}
    .btn.teal{background:var(--accent-2);color:#063d47;border-color:transparent}
    @media (max-width: 980px){
      .kpis{grid-template-columns:repeat(2,1fr)}
      .main{grid-template-columns:1fr}
    }

    /* Fix colored button text contrast */
.btn.primary,
.btn.primary * {
  color: #ffffff !important;
}

.btn.teal,
.btn.teal * {
  color: #063d47 !important; /* dark teal on aqua background */
}

/* Default white buttons keep dark text */
.btn:not(.primary):not(.teal),
.btn:not(.primary):not(.teal) * {
  color: #0f172a !important;
}

    /* ===== Admin hard overrides to beat global styles ===== */
body.admin-theme { color:#0f172a !important; }

/* Keep hero white on gradient */
.hero, .hero * { color:#ffffff !important; }

/* Force dark text on white cards */
.kpis .card, .main .card { background:#ffffff !important; }
.kpis .card *, .main .card * { color:#0f172a !important; }

/* Tables in Recent Bookings */
table thead th, table tbody td { color:#0f172a !important; }

/* KPI labels/values/subtitles */
.kpi .label, .kpi .value, .kpi .sub { color:#0f172a !important; }

/* Status chips (readable no matter globals) */
.status.pending   { background:#fff7ed !important; color:#b45309 !important; border:1px solid #fde68a !important; }
.status.confirmed { background:#ecfeff !important; color:#0e7490 !important; border:1px solid #a5f3fc !important; }
.status.completed { background:#ecfdf5 !important; color:#166534 !important; border:1px solid #bbf7d0 !important; }
.status.cancelled { background:#fef2f2 !important; color:#b91c1c !important; border:1px solid #fecaca !important; }

  </style>
</head>
<body class="admin-theme">

<header>
  <nav>
    <div class="logo">Companio — Admin</div>
    <div class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="users.php">Users</a>
      <a href="reviews.php">Reviews</a>
      <a href="bookings.php">Bookings</a>
      <a href="../logout.php">Sign Out</a>
    </div>
  </nav>
</header>

<section class="hero">
  <div class="hero-inner">
    <div class="hello">Hello, <?= h($_SESSION['name'] ?? 'Admin') ?> 👋</div>
    <h1>Admin Dashboard</h1>
    <div class="actions" style="margin-top:14px">
      <a class="btn primary" href="users.php">Manage Users</a>
      <a class="btn teal" href="reviews.php">Moderate Reviews</a>
      <a class="btn" href="bookings.php">View Bookings</a>
    </div>
  </div>
</section>

<section class="kpis">
  <div class="card kpi">
    <div class="label">Tourists</div>
    <div class="value"><?= (int)$users['tourists'] ?></div>
    <div class="sub"><span class="dot good"></span>Active travellers</div>
  </div>
  <div class="card kpi">
    <div class="label">Guides</div>
    <div class="value"><?= (int)$users['guides'] ?></div>
    <div class="sub"><span class="dot teal" style="background:var(--accent-2)"></span>Available experts</div>
  </div>
  <div class="card kpi">
    <div class="label">Confirmed Bookings</div>
    <div class="value"><?= (int)$bookings['confirmed'] ?></div>
    <div class="sub"><span class="dot good"></span>Ready to go</div>
  </div>
  <div class="card kpi">
    <div class="label">Revenue (A$)</div>
    <div class="value"><?= number_format($revenue, 2) ?></div>
    <div class="sub"><span class="dot" style="background:#22c55e"></span>Totals to date</div>
  </div>
</section>

<main class="main">
  <!-- Recent bookings -->
  <section class="card">
    <div class="section-title">Recent Bookings</div>
    <?php if ($recentBookings && $recentBookings->num_rows): ?>
      <table>
        <thead>
          <tr><th>ID</th><th>Tourist</th><th>Guide</th><th>Date</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php while($b = $recentBookings->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int)$b['booking_id'] ?></td>
            <td><?= h($b['tourist_fn'].' '.$b['tourist_ln']) ?></td>
            <td><?= h($b['guide_fn'].' '.$b['guide_ln']) ?></td>
            <td><?= h($b['tour_date']) ?></td>
            <td>
              <span class="status <?= h($b['status']) ?>"><?= h(ucfirst($b['status'])) ?></span>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty">No bookings yet.</div>
    <?php endif; ?>
  </section>

  <!-- Needs attention -->
  <section class="card">
    <div class="section-title">Needs Attention</div>
    <div class="stack">
      <div class="pill"><strong><?= (int)$bookings['pending'] ?></strong> pending booking(s)</div>
      <div class="pill"><strong><?= (int)$bookings['cancelled'] ?></strong> recently cancelled</div>
      <?php if ($unverified && $unverified->num_rows): ?>
        <div class="pill"><strong><?= (int)$unverified->num_rows ?></strong> unverified account(s)</div>
        <div class="stack" style="padding-left:4px">
          <?php while($u = $unverified->fetch_assoc()): ?>
            <div class="pill" style="justify-content:space-between">
              <span><?= h($u['first_name'].' '.$u['last_name']) ?> • <?= h($u['role']) ?></span>
              <a class="btn" href="users.php" style="padding:6px 10px">Review</a>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="empty">All users are verified ✅</div>
      <?php endif; ?>
    </div>
  </section>
</main>

</body>
</html>
