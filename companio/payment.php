<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='tourist') { header("Location: login.php"); exit; }
require_once 'includes/db.php';
$touristId = (int)$_SESSION['user_id'];
if (!isset($_GET['bid']) && !isset($_POST['booking_id'])) { die("No booking specified for payment."); }
$bookingId = isset($_GET['bid']) ? (int)$_GET['bid'] : (int)$_POST['booking_id'];

$qry = "SELECT b.booking_id, b.tour_date, b.hours, u.first_name AS guide_fname, u.last_name AS guide_lname, p.rate
        FROM bookings b 
        JOIN users u ON b.guide_id = u.user_id 
        JOIN profiles p ON u.user_id = p.user_id
        WHERE b.booking_id = $bookingId AND b.tourist_id = $touristId";
$res = $db->query($qry);
if (!$res || $res->num_rows===0) { die("Booking not found or not authorized."); }
$booking = $res->fetch_assoc();
$guideFullName = $booking['guide_fname']." ".$booking['guide_lname'];
$tourDate = $booking['tour_date'];
$hours = (int)$booking['hours'];
$rate = (float)$booking['rate'];
$amount = $rate * $hours;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $db->real_escape_string($_POST['method']);
    $account = $db->real_escape_string($_POST['account_info']);
    $amountPosted = (float)$_POST['amount'];
    if ($amountPosted != $amount) { $amountPosted = $amount; }
    $db->query("INSERT INTO payments (booking_id, amount, method, account_info) VALUES ($bookingId, $amountPosted, '$method', '$account')");
    $db->query("UPDATE bookings SET status='confirmed' WHERE booking_id=$bookingId");
    header("Location: dashboard.php?paid=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment - Book <?= htmlspecialchars($guideFullName); ?></title>
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
      <h2>Confirm Payment</h2>
      <p>You are about to pay <strong>$<?= number_format($amount,2); ?></strong> for a tour with <strong><?= htmlspecialchars($guideFullName); ?></strong>.</p>
      <p><small>Tour Date: <?= htmlspecialchars($tourDate); ?>, Duration: <?= $hours; ?> hour(s), Rate: $<?= number_format($rate,2); ?>/hr</small></p>
      <form method="POST" action="payment.php">
        <input type="hidden" name="booking_id" value="<?= $bookingId; ?>">
        <input type="hidden" name="amount" value="<?= $amount; ?>">
        <div class="form-group">
          <label for="method">Payment Method</label>
          <select id="method" name="method">
            <option value="Credit Card">Credit Card</option>
            <option value="Bank Transfer">Bank Transfer</option>
          </select>
        </div>
        <div class="form-group">
          <label for="account_info">Card/Account Number</label>
          <input type="text" id="account_info" name="account_info" placeholder="XXXX-XXXX-XXXX-1234" required>
        </div>
        <button type="submit" class="btn btn-primary">Pay $<?= number_format($amount,2); ?></button>
      </form>
    </div>
  </main>
</body>
</html>
