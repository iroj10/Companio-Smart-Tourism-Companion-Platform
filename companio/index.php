    <?php session_start(); ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Companio</title>
      <link rel="stylesheet" href="assets/companio.css">
    </head>
    <body>
      <?php
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<header>
  <nav>
    <div class="logo">🌍 Companio</div>
    <div class="nav-links">
      <a href="index.php">Home</a>
      <?php if(isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Sign Out</a>
      <?php else: ?>
        <a href="login.php">Sign In</a>
        <a href="register.php">Join Now</a>
      <?php endif; ?>
    </div>
  </nav>
</header>

      <main>
        <div class="landing container">
          <h1>Welcome to Companio</h1>
          <p class="subtitle">Your Smart Tourism Companion Platform</p>
          <p>Connect with verified local guides who share your interests and discover
             authentic, personalized travel experiences. From cultural immersion to
             adventure seeking, find your perfect travel companion.</p>
          <div class="features">
            <h2>Why Choose Companio?</h2>
            <div class="features-grid">
              <div class="feature">
                <div class="feature-icon">🎯</div>
                <h3>Interest-Based Matching</h3>
                <p>Our smart algorithm connects you with guides based on your cultural
                   interests and travel preferences.</p>
              </div>
              <div class="feature">
                <div class="feature-icon">🛡️</div>
                <h3>Verified Profiles</h3>
                <p>All guides undergo identity verification for your safety and peace of mind.</p>
              </div>
              <div class="feature">
                <div class="feature-icon">💬</div>
                <h3>Secure Communication</h3>
                <p>Chat safely through our encrypted in-app messaging system (simulated).</p>
              </div>
              <div class="feature">
                <div class="feature-icon">⭐</div>
                <h3>Transparent Reviews</h3>
                <p>Two-way rating system ensures quality experiences for everyone.</p>
              </div>
            </div>
          </div>
          <div class="cta-buttons">
            <a href="register.php" class="btn btn-primary">Start Exploring</a>
            <a href="login.php" class="btn btn-secondary">Sign In</a>
          </div>
        </div>
      </main>
    </body>
    </html>
