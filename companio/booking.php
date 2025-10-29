<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='tourist') { 
    header("Location: login.php"); 
    exit; 
}
require_once 'includes/db.php';

$touristId = (int)$_SESSION['user_id'];

// Get guide ID either from GET (first visit) or POST (form submit)
$guideId = isset($_GET['gid']) ? (int)$_GET['gid'] : (isset($_POST['guide_id']) ? (int)$_POST['guide_id'] : 0);
if ($guideId <= 0) { die("Guide not specified."); }

// Fetch guide info
$res = $db->query("SELECT u.first_name, u.last_name, p.rate 
                   FROM users u 
                   JOIN profiles p ON u.user_id=p.user_id 
                   WHERE u.user_id=$guideId AND u.role='guide'");
if (!$res || $res->num_rows===0) { die("Guide not found."); }
$guide = $res->fetch_assoc();
$guideName = $guide['first_name']." ".$guide['last_name'];
$guideRate = (float)$guide['rate'];

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = $db->real_escape_string($_POST['tour_date']);
    $hours = (int)$_POST['hours'];
    if ($hours <= 0) $hours = 1;

    $sql = "INSERT INTO bookings (tourist_id, guide_id, tour_date, hours, status) 
            VALUES ($touristId, $guideId, '$date', $hours, 'confirmed')";
    if ($db->query($sql)) {
        $bookingId = $db->insert_id;
        header("Location: payment.php?bid=$bookingId");
        exit;
    } else {
        $error = "Failed to create booking: ".$db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book <?= htmlspecialchars($guideName); ?> - Companio</title>
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
    <div class="form-container">
      <h2>Book Guide: <?= htmlspecialchars($guideName); ?></h2>
      <?php if (!empty($error)): ?><p style="color:red;"><?= $error; ?></p><?php endif; ?>
      
      <form method="POST" action="booking.php">
        <input type="hidden" name="guide_id" value="<?= $guideId; ?>">

        <div class="form-group">
          <label for="tour_date">Choose a date for your tour</label>
          <input type="date" id="tour_date" name="tour_date" required>
        </div>

        <div class="form-group">
          <label for="hours">Duration (hours)</label>
          <input type="number" id="hours" name="hours" min="1" max="12" value="4" required>
        </div>

        <p>Guide's rate: $<?= number_format($guideRate,2); ?> per hour.</p>
        <p><strong>Estimated Cost: </strong> 
           $<span id="estCost"><?= number_format($guideRate * 4,2); ?></span>
        </p>

        <button type="submit" class="btn btn-primary">Proceed to Payment</button>
      </form>
    </div>

    <script>
      const hoursInput = document.getElementById('hours');
      const estCostSpan = document.getElementById('estCost');
      const rate = <?= $guideRate ?>;

      function updateCost() {
        const hrs = parseInt(hoursInput.value) || 0;
        estCostSpan.textContent = (hrs * rate).toFixed(2);
      }

      hoursInput.addEventListener('input', updateCost);
    </script>
  </main>
</body>
</html>
