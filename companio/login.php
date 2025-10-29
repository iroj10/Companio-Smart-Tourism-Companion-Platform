<?php session_start(); require_once 'includes/db.php'; ?>
<?php
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // accept either email or the keyword 'admin' as identifier
    $identifier = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // map 'admin' shortcut to admin email
    $email = ($identifier === 'admin') ? 'admin@companio.local' : $identifier;
    $email = $db->real_escape_string($email);

    $res = $db->query("SELECT user_id, first_name, password_hash, role FROM users WHERE email='$email'");
    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($pass, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['first_name'];

            // role-based redirect
            if ($user['role'] === 'admin') {
                header("Location: admin/index.php"); exit;
            } else {
                header("Location: dashboard.php"); exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - Companio</title>
  <link rel="stylesheet" href="assets/companio.css">
</head>
<body>
  <header>
    <nav>
      <div class="logo">🌍 Companio</div>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="register.php">Join Now</a>
      </div>
    </nav>
  </header>
  <main>
    <div class="form-container">
      <h2>Welcome Back</h2>
      <?php if (!empty($error)): ?>
        <p style="color:red;"><?= $error; ?></p>
      <?php endif; ?>
      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="signInEmail">Email</label>
          <input type="email" id="signInEmail" name="email" required>
        </div>
        <div class="form-group">
          <label for="signInPassword">Password</label>
          <input type="password" id="signInPassword" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
      </form>
      <p style="text-align:center; margin-top:1rem;">
        Don't have an account? <a href="register.php" style="color:#667eea;">Join Companio</a>
      </p>
    </div>
  </main>
</body>
</html>
