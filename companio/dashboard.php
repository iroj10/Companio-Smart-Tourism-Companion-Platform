<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'includes/db.php';

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'tourist';

/* ------- Common data ------- */
$profile = [];
if ($pr = $db->query("SELECT * FROM profiles WHERE user_id=$userId")) {
  if ($pr->num_rows) $profile = $pr->fetch_assoc();
}

$recommendedGuides = [];
$matchCount = 0;

/* ------- Role-specific data ------- */
$pendingRequests = [];
$totalEarnings = 0.00;
$avgRating = null;
$reviewCount = 0;

if ($role === 'tourist') {
  // Guides who share at least one interest with me
  $qry = "
    SELECT DISTINCT u.user_id, u.first_name, u.last_name,
           p.location, p.languages, p.specialties, p.rate
    FROM users u
    JOIN profiles p ON u.user_id = p.user_id
    JOIN user_interests ui_g ON ui_g.user_id = u.user_id
    JOIN user_interests ui_t ON ui_t.interest_id = ui_g.interest_id
    WHERE u.role='guide'
      AND ui_t.user_id = $userId
      AND u.user_id <> $userId
  ";
  if ($res = $db->query($qry)) {
    while ($g = $res->fetch_assoc()) {
      // Common interests (names)
      $ci = [];
      $ci_q = "
        SELECT i.name
        FROM interests i
        WHERE i.interest_id IN (SELECT interest_id FROM user_interests WHERE user_id=".$g['user_id'].")
          AND i.interest_id IN (SELECT interest_id FROM user_interests WHERE user_id=$userId)
        LIMIT 3
      ";
      if ($ci_res = $db->query($ci_q)) {
        while ($row = $ci_res->fetch_assoc()) { $ci[] = $row['name']; }
      }
      // Average rating
      $avg = 'N/A';
      if ($rv = $db->query("SELECT ROUND(AVG(rating),1) AS a FROM reviews WHERE guide_id=".$g['user_id'])) {
        $r = $rv->fetch_assoc();
        if ($r && $r['a'] !== null) $avg = $r['a'];
      }
      $g['common_interests'] = $ci;
      $g['avg_rating'] = $avg;
      $recommendedGuides[] = $g;
    }
  }
  $matchCount = count($recommendedGuides);

  // My bookings
  $myBookings = [];
  $bq = $db->query("
    SELECT b.booking_id, b.tour_date, b.hours, b.status,
           u.first_name, u.last_name, u.user_id AS guide_id
    FROM bookings b
    JOIN users u ON b.guide_id = u.user_id
    WHERE b.tourist_id = $userId
    ORDER BY b.tour_date DESC
  ");
  if ($bq) { $myBookings = $bq->fetch_all(MYSQLI_ASSOC); }

} else {
  // GUIDE: upcoming/pending bookings  (FIX: include tourist_id)
  $q = "
    SELECT b.booking_id, b.tour_date, b.status,
           u.first_name, u.last_name, u.user_id AS tourist_id
    FROM bookings b
    JOIN users u ON b.tourist_id = u.user_id
    WHERE b.guide_id = $userId
      AND b.status IN ('pending','confirmed')
    ORDER BY b.tour_date ASC
  ";
  if ($res = $db->query($q)) { $pendingRequests = $res->fetch_all(MYSQLI_ASSOC); }

  // GUIDE: earnings
  if ($er = $db->query("
        SELECT SUM(p.amount) AS total
        FROM payments p
        JOIN bookings b ON p.booking_id=b.booking_id
        WHERE b.guide_id=$userId
      ")) {
    $e = $er->fetch_assoc();
    if ($e && $e['total'] !== null) $totalEarnings = (float)$e['total'];
  }

  // GUIDE: rating summary
  if ($rr = $db->query("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE guide_id=$userId")) {
    $r = $rr->fetch_assoc();
    if ($r) { $avgRating = $r['avg_rating']; $reviewCount = (int)$r['cnt']; }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - Companio</title>
  <link rel="stylesheet" href="assets/companio.css"/>
</head>
<body>
<header>
  <nav>
    <div class="logo">🌍 Companio</div>
    <div class="nav-links">
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="messages.php">Messages</a>
      <a href="logout.php">Sign Out</a>
    </div>
  </nav>
</header>

<main>
  <div class="dashboard container">
    <div class="dashboard-header">
      <h1 id="welcomeMessage"><?php echo "Welcome back, ".htmlspecialchars($_SESSION['name']); ?>!</h1>
      <a href="logout.php" class="btn btn-secondary">Sign Out</a>
    </div>

    <?php if ($role === 'tourist'): ?>

      <!-- Tourist: clickable cards -->
      <div class="dashboard-cards">
        <a href="#section-guides" class="card" style="text-decoration:none; color:inherit; display:block;">
          <h3>🔍 Discover Guides</h3>
          <p id="matchCount">
            <?php echo ($matchCount>0) ? "$matchCount guides match your interests" : "Find local guides who share your interests"; ?>
          </p>
        </a>

        <a href="messages.php" class="card" style="text-decoration:none; color:inherit; display:block;">
          <h3>💬 Messages</h3>
          <p>Chat with your potential guides</p>
        </a>

        <a href="#section-bookings" class="card" style="text-decoration:none; color:inherit; display:block;">
          <h3>📅 My Bookings</h3>
          <p>View your upcoming experiences</p>
        </a>

        <a href="#section-reviews" class="card" style="text-decoration:none; color:inherit; display:block;">
          <h3>⭐ My Reviews</h3>
          <p>Share your travel experiences</p>
        </a>
      </div>

      <!-- Tourist: guides -->
      <div class="matches-section" id="section-guides">
        <h2>Recommended Guides For You</h2>
        <div id="matchesList">
          <?php if ($matchCount === 0): ?>
            <p>No matches found yet. Check back soon!</p>
          <?php else: foreach ($recommendedGuides as $guide):
            $guideName   = htmlspecialchars($guide['first_name'].' '.$guide['last_name']);
            $guideLoc    = htmlspecialchars($guide['location']);
            $guideRating = htmlspecialchars($guide['avg_rating']);
          ?>
            <div class="match-card">
              <div class="match-avatar"><?php echo strtoupper($guide['first_name'][0].$guide['last_name'][0]); ?></div>
              <div class="match-info">
                <div class="match-name"><?= $guideName; ?></div>
                <div class="match-rating">⭐ <?= $guideRating; ?>/5 • <?= $guideLoc; ?></div>
                <div class="match-interests">
                  <?php foreach ($guide['common_interests'] as $int): ?>
                    <span class="match-interest"><?= htmlspecialchars($int); ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="match-actions">
                <a href="profile.php?uid=<?= (int)$guide['user_id']; ?>" class="btn btn-secondary btn-small">View Profile</a>
                <a href="chat.php?uid=<?= (int)$guide['user_id']; ?>" class="btn btn-secondary btn-small">Message</a>
                <a href="booking.php?gid=<?= (int)$guide['user_id']; ?>" class="btn btn-primary btn-small">Book Now</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Tourist: bookings -->
      <div class="matches-section" id="section-bookings">
        <h2>My Bookings</h2>
        <?php if (empty($myBookings)): ?>
          <p>No bookings yet. Find a guide above and book your first tour!</p>
        <?php else: foreach ($myBookings as $bk):
          $gname = htmlspecialchars($bk['first_name'].' '.$bk['last_name']);
        ?>
          <div class="match-card">
            <div class="match-info">
              <div class="match-name"><?= $gname; ?></div>
              <div class="match-rating">
                📅 <?= htmlspecialchars($bk['tour_date']); ?> • ⏱ <?= (int)$bk['hours']; ?>h • <?= htmlspecialchars($bk['status']); ?>
              </div>
            </div>
            <div class="match-actions">
              <a href="profile.php?uid=<?= (int)$bk['guide_id']; ?>" class="btn btn-secondary btn-small">Guide Profile</a>
              <?php
                $isPast = (new DateTime($bk['tour_date'])) <= new DateTime('today');
                $canReview = ($bk['status']==='completed') || ($bk['status']==='confirmed' && $isPast);
                if ($canReview):
              ?>
                <a href="review.php?bid=<?= (int)$bk['booking_id']; ?>" class="btn btn-secondary btn-small">Review</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Tourist: reviews -->
      <div class="matches-section" id="section-reviews">
        <h2>My Reviews</h2>
        <p>After your tours, you can submit reviews from the Bookings section above.</p>
      </div>

    <?php else: ?>

      <!-- GUIDE DASHBOARD -->
      <div id="guideDashboard">
        <!-- Guide cards (same-page anchors) -->
        <div class="dashboard-cards">
          <a href="messages.php" class="card" style="text-decoration:none; color:inherit; display:block;">
            <h3>👥 My Tourists</h3>
            <p>Manage your current clients</p>
          </a>

          <a href="#section-schedule" class="card" style="text-decoration:none; color:inherit; display:block;">
            <h3>📅 My Schedule</h3>
            <p>View and manage your bookings</p>
          </a>

          <a href="#section-earnings" class="card" style="text-decoration:none; color:inherit; display:block;">
            <h3>💰 Earnings</h3>
            <p id="guideEarnings">
              <?php
                if ($totalEarnings>0) echo "Total Earned: $".number_format($totalEarnings,2);
                else echo "$".htmlspecialchars($profile['rate'] ?? '0')."/hour - Available ".htmlspecialchars($profile['availability'] ?? 'N/A');
              ?>
            </p>
          </a>

          <a href="#section-reviews" class="card" style="text-decoration:none; color:inherit; display:block;">
            <h3>⭐ Reviews & Rating</h3>
            <p id="guideRating">
              <?php
                if ($reviewCount>0) echo "⭐ ".htmlspecialchars($avgRating)."/5 (".$reviewCount." reviews)";
                else echo "See what tourists say about you";
              ?>
            </p>
          </a>
        </div>

        <!-- Guide: incoming requests list (preview) -->
        <div class="matches-section">
          <h2>Tourist Requests</h2>
          <div id="guidematchesList">
            <?php if (empty($pendingRequests)): ?>
              <p>No new requests at the moment.</p>
            <?php else: foreach ($pendingRequests as $req):
              $touristName = htmlspecialchars($req['first_name']." ".$req['last_name']);
            ?>
              <div class="match-card">
                <div class="match-info">
                  <div class="match-name"><?= $touristName; ?></div>
                  <div class="match-rating">📅 <?= htmlspecialchars($req['tour_date']); ?> (<?= htmlspecialchars($req['status']); ?>)</div>
                </div>
                <div class="match-actions">
                  <!-- FIX: open chat with the tourist, not the guide -->
                  <a class="btn btn-secondary btn-small" href="chat.php?uid=<?= (int)$req['tourist_id']; ?>">Message</a>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Guide: schedule section (anchor) -->
        <div class="matches-section" id="section-schedule">
          <h2>My Schedule</h2>
          <?php if (empty($pendingRequests)): ?>
            <p>No bookings yet.</p>
          <?php else: foreach ($pendingRequests as $req): ?>
            <div class="match-card">
              <div class="match-info">
                <div class="match-name"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']); ?></div>
                <div class="match-rating">📅 <?= htmlspecialchars($req['tour_date']); ?> • <?= htmlspecialchars($req['status']); ?></div>
              </div>
              <div class="match-actions">
                <!-- FIX here as well -->
                <a class="btn btn-secondary btn-small" href="chat.php?uid=<?= (int)$req['tourist_id']; ?>">Message</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Guide: earnings section (anchor) -->
        <div class="matches-section" id="section-earnings">
          <h2>My Earnings</h2>
          <p>Total earned so far: $<?= number_format($totalEarnings,2); ?></p>
        </div>

        <!-- Guide: reviews section (anchor) — no created_at dependency -->
        <div class="matches-section" id="section-reviews">
          <h2>My Reviews</h2>
          <?php
            $r = $db->query("
              SELECT r.review_id, r.rating, r.comment, u.first_name, u.last_name
              FROM reviews r
              JOIN users u ON r.tourist_id=u.user_id
              WHERE r.guide_id=$userId
              ORDER BY r.review_id DESC
            ");
            if ($r && $r->num_rows):
              while ($rev = $r->fetch_assoc()):
          ?>
            <div class="match-card">
              <div class="match-info">
                <div class="match-name">⭐ <?= (int)$rev['rating']; ?>/5 by <?= htmlspecialchars($rev['first_name'].' '.$rev['last_name']); ?></div>
                <?php if (!empty($rev['comment'])): ?>
                  <div class="match-rating" style="color:#555;"><?= htmlspecialchars($rev['comment']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endwhile; else: ?>
            <p>No reviews yet.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
