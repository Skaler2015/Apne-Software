<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: manage-tools.php');
        exit;
    } else {
        $error = 'Wrong password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login | ApneSoftware</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  body{background:#0f0f1a;min-height:100vh;display:flex;align-items:center;justify-content:center}
  .box{background:#181828;border:1px solid #2a2a40;border-radius:16px;padding:36px;width:340px;box-shadow:0 20px 50px rgba(0,0,0,.4)}
  h1{color:#fff;font-size:1.3rem;margin-bottom:6px}
  p{color:#9a9ab0;font-size:.85rem;margin-bottom:22px}
  input{width:100%;padding:13px 14px;border-radius:10px;border:1px solid #2a2a40;background:#0f0f1a;color:#fff;font-size:.95rem;margin-bottom:14px;outline:none}
  input:focus{border-color:#7C5CFC}
  button{width:100%;padding:13px;border-radius:10px;border:none;background:#7C5CFC;color:#fff;font-weight:700;font-size:.95rem;cursor:pointer}
  button:hover{background:#6A48F0}
  .err{color:#ff6b6b;font-size:.85rem;margin-bottom:14px}
</style>
</head>
<body>
  <form class="box" method="POST">
    <h1>🔐 Admin Panel</h1>
    <p>ApneSoftware.com — Manage Tools & Analytics</p>
    <?php if ($error): ?><div class="err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <input type="password" name="password" placeholder="Admin password" autofocus required>
    <button type="submit">Sign In</button>
  </form>
</body>
</html>
