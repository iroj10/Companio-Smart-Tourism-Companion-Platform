<?php
session_start(); // not strictly required for view-only
require_once 'includes/db.php';

$guideId = isset($_GET['guide_id']) ? (int)$_GET['guide_id'] : 0;
$offset  = isset($_GET['offset'])   ? max(0, (int)$_GET['offset']) : 0;

if ($guideId < 1) { http_response_code(400); exit; }

// fetch next 3
$sql = "
  SELECT r.review_id, r.rating, r.comment, u.first_name, u.last_name
  FROM reviews r
  JOIN users u ON r.tourist_id = u.user_id
  WHERE r.guide_id = $guideId
  ORDER BY r.review_id DESC
  LIMIT 3 OFFSET $offset
";
$res = $db->query($sql);
if (!$res || !$res->num_rows) { echo ''; exit; }

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Return HTML snippets (to be appended)
while ($rev = $res->fetch_assoc()):
?>
  <div class="match-card">
    <div class="match-info">
      <div class="match-name">
        ⭐ <?= (int)$rev['rating']; ?>/5 by <?= h($rev['first_name'].' '.$rev['last_name']); ?>
      </div>
      <?php if (!empty($rev['comment'])): ?>
        <div class="match-rating" style="color:#555;"><?= h($rev['comment']); ?></div>
      <?php endif; ?>
    </div>
  </div>
<?php endwhile; ?>
