<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='tourist') { header("Location: login.php"); exit; }
require_once 'includes/db.php';
$touristId = (int)$_SESSION['user_id'];
if (!isset($_GET['bid']) && !isset($_POST['booking_id'])) { die("No booking specified for review."); }
$bookingId = isset($_GET['bid']) ? (int)$_GET['bid'] : (int)$_POST['booking_id'];

$qry = "SELECT b.booking_id, b.guide_id, u.first_name, u.last_name, b.tour_date, b.status
        FROM bookings b JOIN users u ON b.guide_id = u.user_id
        WHERE b.booking_id = $bookingId AND b.tourist_id = $touristId";
$res = $db->query($qry);
if (!$res || $res->num_rows===0) { die("Booking not found or not authorized."); }
$booking = $res->fetch_assoc();
$guideId = (int)$booking['guide_id'];
$guideName = $booking['first_name']." ".$booking['last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = $db->real_escape_string($_POST['comment']);
    if ($rating<1 || $rating>5) $rating=5;
    $db->query("INSERT INTO reviews (booking_id, tourist_id, guide_id, rating, comment) VALUES ($bookingId, $touristId, $guideId, $rating, '$comment')");
    header("Location: dashboard.php?review_submitted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review <?= htmlspecialchars($guideName); ?> - Companio</title>
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
      <h2>Review for <?= htmlspecialchars($guideName); ?></h2>
      <p>Please rate your experience on <?= htmlspecialchars($booking['tour_date']); ?>:</p>
      <form method="POST" action="review.php">
        <input type="hidden" name="booking_id" value="<?= $bookingId; ?>">
        <div class="form-group">
          <label for="rating">Rating</label>
          <select id="rating" name="rating">
            <option value="5">⭐️⭐️⭐️⭐️⭐️ - Excellent</option>
            <option value="4">⭐️⭐️⭐️⭐️ - Good</option>
            <option value="3">⭐️⭐️⭐️ - Average</option>
            <option value="2">⭐️⭐️ - Poor</option>
            <option value="1">⭐️ - Terrible</option>
          </select>
        </div>
        <div class="form-group">
          <label for="comment">Comment (optional)</label>
          <textarea id="comment" name="comment" placeholder="Share details of your experience..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </form>
    </div>
  </main>
</body>
</html>
