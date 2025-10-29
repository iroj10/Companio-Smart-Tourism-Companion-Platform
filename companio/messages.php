<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'includes/db.php';

$meId    = (int)$_SESSION['user_id'];
$myRole  = strtolower($_SESSION['role'] ?? '');
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/* --- Detect the message text column name and alias it as 'content' --- */
$msgCol = null;
$candidates = ['content','message','body','text','msg'];
foreach ($candidates as $cand) {
  $chk = $db->query("SHOW COLUMNS FROM messages LIKE '{$cand}'");
  if ($chk && $chk->num_rows) { $msgCol = $cand; break; }
}
if ($msgCol === null) {
  // last resort: try to find any varchar/text column to show as snippet
  $fallback = $db->query("SHOW COLUMNS FROM messages");
  if ($fallback) {
    while ($c = $fallback->fetch_assoc()) {
      $type = strtolower($c['Type'] ?? '');
      if (str_contains($type, 'text') || str_contains($type, 'char')) {
        $msgCol = $c['Field'];
        break;
      }
    }
  }
}
if ($msgCol === null) {
  die("Messages table found, but no text column (content/message/body/text) could be detected.");
}

/* --- Build inbox: latest message per conversation (me <-> other) --- */
/*     Works without created_at; orders by message_id (assumed auto-increment) */
$sql = "
  SELECT x.message_id, x.sender_id, x.receiver_id, x.content, x.other_id,
         u.first_name, u.last_name
  FROM (
    SELECT m.message_id, m.sender_id, m.receiver_id, m.`{$msgCol}` AS content,
           CASE WHEN m.sender_id = $meId THEN m.receiver_id ELSE m.sender_id END AS other_id
    FROM messages m
    JOIN (
      SELECT LEAST(sender_id, receiver_id) a,
             GREATEST(sender_id, receiver_id) b,
             MAX(message_id) AS last_id
      FROM messages
      WHERE sender_id = $meId OR receiver_id = $meId
      GROUP BY a, b
    ) t
      ON t.last_id = m.message_id
    ORDER BY m.message_id DESC
  ) x
  JOIN users u ON u.user_id = x.other_id
  ORDER BY x.message_id DESC
";
$res = $db->query($sql);
$threads = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/* --- Optional: unread counts if you have a read_at column --- */
$hasReadAt = false;
$check = $db->query("SHOW COLUMNS FROM messages LIKE 'read_at'");
if ($check && $check->num_rows) { $hasReadAt = true; }

$unreadMap = [];
if ($hasReadAt && !empty($threads)) {
  $otherIds = array_unique(array_map(fn($t) => (int)$t['other_id'], $threads));
  if (!empty($otherIds)) {
    $in = implode(',', $otherIds);
    $qr = $db->query("
      SELECT sender_id AS other_id, COUNT(*) AS unread_cnt
      FROM messages
      WHERE receiver_id = $meId
        AND sender_id IN ($in)
        AND (read_at IS NULL OR read_at = '')
      GROUP BY sender_id
    ");
    if ($qr) {
      while ($row = $qr->fetch_assoc()) {
        $unreadMap[(int)$row['other_id']] = (int)$row['unread_cnt'];
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Messages - Companio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/companio.css">
  <style>
    .thread-card {
      background: #fff;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin: 0 0 1rem 0;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      display: flex; align-items: center; gap: 1rem;
    }
    .thread-avatar {
      min-width: 44px; width: 44px; height: 44px; border-radius: 50%;
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-weight:700;
      background: linear-gradient(135deg, #667eea, #764ba2);
    }
    .thread-info { flex: 1; }
    .thread-name { font-weight: 700; margin-bottom: .2rem; }
    .thread-last { color: #666; font-size: .95rem; }
    .unread-badge {
      background: #ff4757; color: #fff; border-radius: 999px;
      padding: 2px 8px; font-size: .8rem; font-weight: 700; margin-left: .5rem;
    }
    .thread-actions { display:flex; gap:.5rem; }
  </style>
</head>
<body>
<header>
  <nav>
    <div class="logo">🌍 Companio</div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="logout.php">Sign Out</a>
    </div>
  </nav>
</header>

<main>
  <div class="container dashboard" style="max-width:900px;">
    <div class="dashboard-header" style="margin-bottom:1rem;">
      <h1>Messages</h1>
    </div>

    <?php if (empty($threads)): ?>
      <?php if ($myRole === 'guide'): ?>
        <p>No conversations yet. You can message tourists from your dashboard when you receive requests or bookings.</p>
      <?php else: ?>
        <p>No conversations yet. Find a guide and start a chat from their profile or the dashboard.</p>
      <?php endif; ?>
    <?php else: ?>
      <?php foreach ($threads as $t):
        $otherId  = (int)$t['other_id'];
        $name     = trim(($t['first_name'] ?? '').' '.($t['last_name'] ?? ''));
        $name     = $name !== '' ? $name : 'User #'.$otherId;
        $initials = strtoupper(mb_substr($t['first_name'] ?? '', 0, 1) . mb_substr($t['last_name'] ?? '', 0, 1));
        $snippet  = $t['content'] ?? '';
        if (mb_strlen($snippet) > 120) $snippet = mb_substr($snippet, 0, 117).'…';
        $unread   = $unreadMap[$otherId] ?? 0;
      ?>
        <div class="thread-card">
          <div class="thread-avatar"><?= h($initials !== '' ? $initials : 'U'); ?></div>
          <div class="thread-info">
            <div class="thread-name">
              <?= h($name); ?>
              <?php if ($unread > 0): ?><span class="unread-badge"><?= (int)$unread; ?></span><?php endif; ?>
            </div>
            <div class="thread-last"><?= h($snippet); ?></div>
          </div>
          <div class="thread-actions">
            <a href="profile.php?uid=<?= $otherId; ?>" class="btn btn-secondary btn-small">View Profile</a>
            <a href="chat.php?uid=<?= $otherId; ?>" class="btn btn-primary btn-small">Open Chat</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</main>
</body>
</html>
