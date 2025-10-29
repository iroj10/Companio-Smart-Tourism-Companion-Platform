<?php session_start(); require_once 'includes/db.php'; ?>
<?php
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname   = $db->real_escape_string($_POST['first_name']);
    $lname   = $db->real_escape_string($_POST['last_name']);
    $email   = $db->real_escape_string($_POST['email']);
    $pass    = $_POST['password'];
    $phone   = $db->real_escape_string($_POST['phone']);
    $location= $db->real_escape_string($_POST['location']);
    $role    = $db->real_escape_string($_POST['role']); // explicit hidden input

    $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
    $sql_user = "INSERT INTO users (email, password_hash, first_name, last_name, role, is_verified)
                 VALUES ('$email', '$passwordHash', '$fname', '$lname', '$role', 1)";
    if ($db->query($sql_user)) {
        $newUserId = $db->insert_id;

        $bio         = $db->real_escape_string($_POST['bio'] ?? '');
        $travelStyle = ($role === 'tourist') ? $db->real_escape_string($_POST['travel_style'] ?? '') : NULL;
        $languages   = ($role === 'guide')   ? $db->real_escape_string($_POST['languages'] ?? '')   : NULL;
        $experience  = ($role === 'guide')   ? (int)($_POST['experience'] ?? 0) : NULL;
        $specialties = ($role === 'guide')   ? $db->real_escape_string($_POST['specialties'] ?? '') : NULL;
        $rate        = ($role === 'guide')   ? (float)($_POST['rate'] ?? 0) : NULL;
        $availability= ($role === 'guide')   ? $db->real_escape_string($_POST['availability'] ?? ''): NULL;

        $sql_profile = "INSERT INTO profiles 
            (user_id, phone, location, bio, travel_style, languages, experience, specialties, rate, availability)
            VALUES ($newUserId, '$phone', '$location', '$bio', ".
              ($travelStyle ? "'$travelStyle'" : "NULL") . ", ".
              ($languages   ? "'$languages'"   : "NULL") . ", ".
              ($experience !== NULL ? $experience : "NULL") . ", ".
              ($specialties ? "'$specialties'" : "NULL") . ", ".
              ($rate !== NULL ? $rate : "NULL") . ", ".
              ($availability? "'$availability'":"NULL") .
            ")";
        $db->query($sql_profile);

        if (!empty($_POST['interests'])) {
            foreach($_POST['interests'] as $iid) {
                $iid = (int)$iid;
                $db->query("INSERT INTO user_interests (user_id, interest_id) VALUES ($newUserId, $iid)");
            }
        }

        // Build matches for the new user
        if ($role === 'guide') {
            $qry = "SELECT DISTINCT ui.user_id AS tourist_id
                    FROM user_interests ui
                    WHERE ui.interest_id IN (SELECT interest_id FROM user_interests WHERE user_id=$newUserId)
                      AND ui.user_id != $newUserId";
            $res = $db->query($qry);
            while($row = $res->fetch_assoc()) {
                $tid = (int)$row['tourist_id'];
                $r = $db->query("SELECT role FROM users WHERE user_id=$tid");
                if ($r && $r->num_rows) {
                    $torole = $r->fetch_assoc()['role'];
                    if ($torole === 'tourist') {
                        $db->query("INSERT INTO matches (tourist_id, guide_id) VALUES ($tid, $newUserId)");
                    }
                }
            }
        } else {
            $qry = "SELECT DISTINCT ui.user_id AS guide_id
                    FROM user_interests ui
                    WHERE ui.interest_id IN (SELECT interest_id FROM user_interests WHERE user_id=$newUserId)
                      AND ui.user_id != $newUserId";
            $res = $db->query($qry);
            while($row = $res->fetch_assoc()) {
                $gid = (int)$row['guide_id'];
                $r = $db->query("SELECT role FROM users WHERE user_id=$gid");
                if ($r && $r->num_rows) {
                    $gurole = $r->fetch_assoc()['role'];
                    if ($gurole === 'guide') {
                        $db->query("INSERT INTO matches (tourist_id, guide_id) VALUES ($newUserId, $gid)");
                    }
                }
            }
        }

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['role']    = $role;
        $_SESSION['name']    = $fname;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Registration failed: " . $db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Companio</title>
  <link rel="stylesheet" href="assets/companio.css">
  <script src="js/register.js" defer></script>
</head>
<body>
  <header>
    <nav>
      <div class="logo">🌍 Companio</div>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="login.php">Sign In</a>
      </div>
    </nav>
  </header>
  <main>
    <div class="form-container">
      <h2>Join Companio</h2>
      <?php if (!empty($error)): ?><p style="color:red;"><?= $error; ?></p><?php endif; ?>
      <div class="form-toggle">
        <div class="toggle-buttons">
          <button type="button" class="toggle-btn active" onclick="setUserType('tourist')">Tourist</button>
          <button type="button" class="toggle-btn" onclick="setUserType('guide')">Local Guide</button>
        </div>
      </div>
      <form method="POST" action="register.php">
        <input type="hidden" id="role" name="role" value="tourist">
        <div class="form-group">
          <label for="firstName">First Name</label>
          <input type="text" id="firstName" name="first_name" required>
        </div>
        <div class="form-group">
          <label for="lastName">Last Name</label>
          <input type="text" id="lastName" name="last_name" required>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" required>
        </div>
        <div class="form-group">
          <label for="location">Location</label>
          <input type="text" id="location" name="location" placeholder="City, Country" required>
        </div>
        <div class="form-group">
          <label>Select Your Interests</label>
          <div class="interests-grid">
            <?php
              $result = $db->query("SELECT interest_id, name FROM interests ORDER BY name ASC");
              if ($result) {
                  while($row = $result->fetch_assoc()):
                      $iid = (int)$row['interest_id'];
                      $iname = htmlspecialchars($row['name']);
            ?>
            <label class="interest-tag">
              <input type="checkbox" name="interests[]" value="<?= $iid; ?>" style="display:none;">
              <?= $iname; ?>
            </label>
            <?php endwhile; } ?>
          </div>
        </div>
        <div id="touristFields">
          <div class="form-group">
            <label for="travelStyle">Travel Style</label>
            <select id="travelStyle" name="travel_style">
              <option value="budget">Budget Traveler</option>
              <option value="mid-range">Mid-range</option>
              <option value="luxury">Luxury</option>
            </select>
          </div>
          <div class="form-group">
            <label for="bio">Tell us about yourself</label>
            <textarea id="bio" name="bio" placeholder="What kind of experiences are you looking for?"></textarea>
          </div>
        </div>
        <div id="guideFields" class="hidden">
          <div class="form-group">
            <label for="languages">Languages Spoken</label>
            <input type="text" id="languages" name="languages" placeholder="e.g., English, Spanish, French">
          </div>
          <div class="form-group">
            <label for="experience">Years of Experience</label>
            <input type="number" id="experience" name="experience" min="0" max="50">
          </div>
          <div class="form-group">
            <label for="specialties">Specialties & Expertise</label>
            <textarea id="specialties" name="specialties" placeholder="What makes you unique as a local guide?"></textarea>
          </div>
          <div class="form-group">
            <label for="rate">Hourly Rate (USD)</label>
            <input type="number" id="rate" name="rate" min="5" step="5">
          </div>
          <div class="form-group">
            <label for="availability">Availability</label>
            <select id="availability" name="availability">
              <option value="weekdays">Weekdays Only</option>
              <option value="weekends">Weekends Only</option>
              <option value="flexible">Flexible Schedule</option>
              <option value="by appointment">By Appointment</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
      </form>
      <div style="text-align:center; margin-top:1rem;">
        <p>Already have an account? <a href="login.php" style="color:#667eea;">Sign in here</a></p>
      </div>
    </div>
  </main>
</body>
</html>
