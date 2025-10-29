<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'includes/db.php';

$currentUserId = (int)$_SESSION['user_id'];
$viewUserId    = isset($_GET['uid']) ? (int)$_GET['uid'] : $currentUserId;
$viewOwn       = ($viewUserId === $currentUserId);

/* Safe HTML helper to avoid PHP 8 null warnings */
function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/* Load user + profile */
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.role, u.email,
               p.phone, p.location, p.bio, p.travel_style, p.languages, p.experience,
               p.specialties, p.rate, p.availability
        FROM users u
        JOIN profiles p ON u.user_id = p.user_id
        WHERE u.user_id = $viewUserId";
$result = $db->query($sql);
if (!$result || $result->num_rows === 0) { die("Profile not found."); }
$userData = $result->fetch_assoc();

/* Interests (names + ids) */
$interestNames = [];
$userInterestIds = [];
$idr = $db->query("SELECT ui.interest_id, i.name
                   FROM user_interests ui
                   JOIN interests i ON ui.interest_id=i.interest_id
                   WHERE ui.user_id=$viewUserId");
if ($idr) {
  while($ri = $idr->fetch_assoc()){
    $userInterestIds[] = (int)$ri['interest_id'];
    $interestNames[]   = $ri['name'];
  }
}

/* Save edits for own profile */
if ($viewOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = $db->real_escape_string($_POST['phone'] ?? '');
    $location = $db->real_escape_string($_POST['location'] ?? '');
    $bio      = $db->real_escape_string($_POST['bio'] ?? '');

    if ($userData['role'] === 'tourist') {
        $travelStyle = $db->real_escape_string($_POST['travel_style'] ?? '');
        $sqlUpdate = "UPDATE profiles
                      SET phone='$phone', location='$location', bio='$bio', travel_style='$travelStyle'
                      WHERE user_id=$currentUserId";
    } else { // guide
        $languages   = $db->real_escape_string($_POST['languages'] ?? '');
        $experience  = (int)($_POST['experience'] ?? 0);
        $specialties = $db->real_escape_string($_POST['specialties'] ?? '');
        $rate        = (float)($_POST['rate'] ?? 0);
        $availability= $db->real_escape_string($_POST['availability'] ?? '');
        $sqlUpdate = "UPDATE profiles
                      SET phone='$phone', location='$location', bio='$bio', languages='$languages',
                          experience=$experience, specialties='$specialties', rate=$rate, availability='$availability'
                      WHERE user_id=$currentUserId";
    }
    $db->query($sqlUpdate);

    // Interests update (checkboxes)
    if (isset($_POST['interests']) && is_array($_POST['interests'])) {
        $db->query("DELETE FROM user_interests WHERE user_id=$currentUserId");
        foreach($_POST['interests'] as $iid) {
            $iid = (int)$iid;
            $db->query("INSERT INTO user_interests (user_id, interest_id) VALUES ($currentUserId, $iid)");
        }
    }

    header("Location: profile.php");
    exit;
}

/* ---------- Reviews summary for GUIDE profiles (for tourists to inspect) ---------- */
$showReviews = ($userData['role'] === 'guide'); // show on guide profiles (own or others)
$avgRating = 'N/A';
$reviewCount = 0;
$dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
$initialReviews = [];

if ($showReviews) {
  $guideId = (int)$userData['user_id'];

  // avg + count
  $rr = $db->query("SELECT ROUND(AVG(rating),1) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE guide_id=$guideId");
  if ($rr && $rr->num_rows) {
    $r = $rr->fetch_assoc();
    if ((int)$r['cnt'] > 0) { $avgRating = $r['avg_r']; $reviewCount = (int)$r['cnt']; }
  }

  // distribution
  $dr = $db->query("SELECT rating, COUNT(*) c FROM reviews WHERE guide_id=$guideId GROUP BY rating");
  if ($dr) while($row=$dr->fetch_assoc()){ $dist[(int)$row['rating']] = (int)$row['c']; }

  // first 3 reviews (newest first, no created_at needed)
  $q = $db->query("
      SELECT r.review_id, r.rating, r.comment, u.first_name, u.last_name
      FROM reviews r
      JOIN users u ON r.tourist_id = u.user_id
      WHERE r.guide_id = $guideId
      ORDER BY r.review_id DESC
      LIMIT 3
  ");
  if ($q) $initialReviews = $q->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $viewOwn ? "My Profile" : h($userData['first_name']."’s Profile"); ?></title>
  <link rel="stylesheet" href="assets/companio.css">
</head>
<body>
  <header>
    <nav>
      <div class="logo">🌍 Companio</div>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Sign Out</a>
      </div>
    </nav>
  </header>

  <main>
    <div class="container">

      <h2><?= $viewOwn ? "Edit Profile" : h($userData['first_name']." ".$userData['last_name']); ?></h2>

      <?php if ($viewOwn): ?>
        <!-- --------- EDIT VIEW --------- -->
        <form method="POST" action="profile.php" class="form-container" style="max-width:800px;">
          <div class="form-group">
            <label>Name</label>
            <p><?= h($userData['first_name']." ".$userData['last_name']); ?> (<?= ucfirst(h($userData['role'])); ?>)</p>
          </div>
          <div class="form-group"><label>Email</label><p><?= h($userData['email']); ?></p></div>

          <div class="form-group"><label for="phone">Phone</label>
            <input type="text" name="phone" id="phone" value="<?= h($userData['phone']); ?>">
          </div>

          <div class="form-group"><label for="location">Location</label>
            <input type="text" name="location" id="location" value="<?= h($userData['location']); ?>">
          </div>

          <div class="form-group">
            <label>Interests</label>
            <div class="interests-grid">
              <?php
                $all = $db->query("SELECT interest_id, name FROM interests ORDER BY name ASC");
                if ($all) while($it = $all->fetch_assoc()):
                  $iid=(int)$it['interest_id'];
                  $checked = in_array($iid,$userInterestIds) ? "checked" : "";
              ?>
                <label class="interest-tag">
                  <input type="checkbox" name="interests[]" value="<?= $iid; ?>" <?= $checked; ?> style="display:none;">
                  <?= h($it['name']); ?>
                </label>
              <?php endwhile; ?>
            </div>
          </div>

          <?php if ($userData['role']==='tourist'): ?>
            <div class="form-group">
              <label for="travel_style">Travel Style</label>
              <select name="travel_style" id="travel_style">
                <?php
                  $styles=['budget'=>'Budget Traveler','mid-range'=>'Mid-range','luxury'=>'Luxury'];
                  foreach($styles as $v=>$lbl):
                    $sel = ($userData['travel_style']===$v) ? "selected" : "";
                ?>
                  <option value="<?= h($v); ?>" <?= $sel; ?>><?= h($lbl); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <div class="form-group"><label for="languages">Languages</label>
              <input type="text" name="languages" id="languages" value="<?= h($userData['languages']); ?>">
            </div>

            <div class="form-group"><label for="experience">Experience (years)</label>
              <input type="number" name="experience" id="experience" value="<?= (int)($userData['experience'] ?? 0); ?>">
            </div>

            <div class="form-group"><label for="specialties">Specialties</label>
              <textarea name="specialties" id="specialties"><?= h($userData['specialties']); ?></textarea>
            </div>

            <div class="form-group"><label for="rate">Rate (USD/hr)</label>
              <input type="number" name="rate" id="rate" value="<?= h($userData['rate']); ?>" step="0.01">
            </div>

            <div class="form-group">
              <label for="availability">Availability</label>
              <select name="availability" id="availability">
                <?php
                  $opts=["weekdays"=>"Weekdays Only","weekends"=>"Weekends Only","flexible"=>"Flexible Schedule","by appointment"=>"By Appointment"];
                  foreach($opts as $v=>$lbl):
                    $sel = ($userData['availability']===$v) ? "selected" : "";
                ?>
                  <option value="<?= h($v); ?>" <?= $sel; ?>><?= h($lbl); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="form-group"><label for="bio">About</label>
            <textarea name="bio" id="bio"><?= h($userData['bio']); ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>

      <?php else: ?>
        <!-- --------- PUBLIC VIEW (someone else's profile) --------- -->
        <div class="form-container" style="max-width:800px;">
          <p><strong>Name:</strong> <?= h($userData['first_name']." ".$userData['last_name']); ?></p>
          <p><strong>Location:</strong> <?= h($userData['location']); ?></p>
          <p><strong>Interests:</strong> <?= h(implode(", ", $interestNames)); ?></p>
          <p><strong>About:</strong> <?= nl2br(h($userData['bio'])); ?></p>

          <?php if ($userData['role']==='guide'): ?>
            <p><strong>Languages:</strong> <?= h($userData['languages']); ?></p>
            <p><strong>Experience:</strong> <?= (int)($userData['experience'] ?? 0); ?> years</p>
            <p><strong>Specialties:</strong> <?= h($userData['specialties']); ?></p>
            <p><strong>Rate:</strong> $<?= h($userData['rate']); ?>/hour</p>
            <p><strong>Availability:</strong> <?= h($userData['availability']); ?></p>

            <?php
              $rev = $db->query("SELECT ROUND(AVG(rating),1) AS a, COUNT(*) AS c FROM reviews WHERE guide_id=$viewUserId");
              $ratingText = "No reviews yet.";
              if ($rev && $rev->num_rows) { $r=$rev->fetch_assoc(); if ((int)$r['c']>0) $ratingText = "⭐ ".$r['a']."/5 (".$r['c']." reviews)"; }
            ?>
            <p><strong>Rating:</strong> <?= h($ratingText); ?></p>

            <?php if (($_SESSION['role'] ?? '')==='tourist'): ?>
              <a href="chat.php?uid=<?= (int)$userData['user_id']; ?>" class="btn btn-secondary">Message</a>
              <a href="booking.php?gid=<?= (int)$userData['user_id']; ?>" class="btn btn-primary">Book Tour</a>
            <?php endif; ?>
          <?php else: ?>
            <?php if (($_SESSION['role'] ?? '')==='guide'): ?>
              <a href="chat.php?uid=<?= (int)$userData['user_id']; ?>" class="btn btn-secondary">Message</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- ---------- REVIEWS (visible on guide profiles; both own and public view) ---------- -->
      <?php if ($showReviews): ?>
        <div class="matches-section" id="guide-reviews" style="max-width:800px; margin-left:auto; margin-right:auto;">
          <h2>⭐ Reviews</h2>

          <?php if ($reviewCount === 0): ?>
            <p>No reviews yet.</p>
          <?php else: ?>
            <!-- Summary + distribution -->
            <div class="match-card" style="align-items:flex-start;">
              <div class="match-info">
                <div class="match-name" style="font-size:1.4rem;">
                  Overall: ⭐ <?= h($avgRating); ?>/5
                  <span style="color:#666; font-size:.95rem;">(<?= (int)$reviewCount; ?> reviews)</span>
                </div>
                <div style="margin-top:.5rem;">
                  <?php
                    $maxCount = max($dist);
                    if ($maxCount < 1) $maxCount = 1;
                    for ($s=5; $s>=1; $s--):
                      $w = (int)round(($dist[$s]/$maxCount)*100);
                  ?>
                    <div style="display:flex; align-items:center; gap:.5rem; margin:.25rem 0;">
                      <span style="width:2.2rem; color:#444;"><?= $s; ?>★</span>
                      <div style="flex:1; background:#eee; height:8px; border-radius:999px; overflow:hidden;">
                        <div style="width:<?= $w; ?>%; height:100%; background:#ffd54f;"></div>
                      </div>
                      <span style="width:2rem; text-align:right; color:#666;"><?= (int)$dist[$s]; ?></span>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>
            </div>

            <!-- Initial 3 reviews -->
            <div id="reviewsList">
              <?php foreach ($initialReviews as $rev): ?>
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
              <?php endforeach; ?>
            </div>

            <?php if ($reviewCount > 3): ?>
              <div style="text-align:center; margin-top:1rem;">
                <button id="loadMoreReviews"
                        class="btn btn-secondary btn-small"
                        data-guide="<?= (int)$userData['user_id']; ?>"
                        data-offset="3">
                  Load more reviews
                </button>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <?php if ($showReviews): ?>
  <script>
  // Progressive "Load more" for reviews (3 at a time)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('#loadMoreReviews');
    if (!btn) return;

    const guideId = btn.getAttribute('data-guide');
    const offset  = parseInt(btn.getAttribute('data-offset'), 10) || 0;

    try {
      const res  = await fetch(`reviews_ajax.php?guide_id=${guideId}&offset=${offset}`);
      const html = await res.text();

      const list = document.getElementById('reviewsList');
      list.insertAdjacentHTML('beforeend', html);

      btn.setAttribute('data-offset', offset + 3);

      if (!html.trim()) {
        btn.remove(); // no more reviews
      }
    } catch (err) {
      alert('Could not load more reviews right now.');
    }
  });
  </script>
  <?php endif; ?>
  
  <script>
document.addEventListener('DOMContentLoaded', () => {
  // Each .interest-tag contains a hidden checkbox
  document.querySelectorAll('.interest-tag').forEach(tag => {
    const cb = tag.querySelector('input[type="checkbox"]');
    if (!cb) return;

    // Initialize selected state
    if (cb.checked) tag.classList.add('selected');

    // Make the whole pill toggle the checkbox
    tag.tabIndex = 0; // keyboard focusable

    tag.addEventListener('click', (e) => {
      // If user clicks the pill (not the input), toggle manually
      if (e.target !== cb) {
        cb.checked = !cb.checked;
      }
      tag.classList.toggle('selected', cb.checked);
    });

    // Keyboard support (space/enter)
    tag.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        cb.checked = !cb.checked;
        tag.classList.toggle('selected', cb.checked);
      }
    });
  });
});
</script>

</body>
</html>
