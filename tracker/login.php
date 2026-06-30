<?php
require_once 'includes/config.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Support BOTH: bcrypt hash AND plain text password
            $ok = false;
            if (password_verify($password, $user['password'])) {
                // bcrypt match
                $ok = true;
            } elseif ($password === $user['password']) {
                // plain text match — auto upgrade to bcrypt
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                    ->execute([$newHash, $user['id']]);
                $ok = true;
            }

            if ($ok) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['name'] ?? 'Admin';
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Website Change Tracker Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--accent:#7c5cfc;--bg:#0d1117;--bg2:#161b27;--bg3:#1e2533;--border:rgba(255,255,255,.1);--txt:#e6eaf2;--dim:rgba(230,234,242,.55)}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--txt);font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{width:100%;max-width:420px}
.login-card{background:var(--bg2);border:1px solid var(--border);border-radius:20px;padding:36px 32px}
.brand{text-align:center;margin-bottom:28px}
.brand-icon{font-size:2.5rem;display:block;margin-bottom:8px}
.brand h1{font-size:1.3rem;font-weight:800;margin:0}
.brand h1 span{color:var(--accent)}
.brand p{color:var(--dim);font-size:.85rem;margin-top:4px}
.form-control{background:var(--bg3)!important;border:1px solid var(--border)!important;color:var(--txt)!important;border-radius:9px;padding:11px 14px;font-size:.92rem}
.form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(124,92,252,.2)!important;color:var(--txt)!important}
.form-control::placeholder{color:rgba(230,234,242,.3)!important}
.form-label{color:var(--dim);font-weight:600;font-size:.85rem;margin-bottom:6px}
.btn-login{background:var(--accent);border:none;color:#fff;font-weight:700;font-size:1rem;padding:12px;border-radius:10px;width:100%;transition:.2s;cursor:pointer}
.btn-login:hover{background:#6b5ee0;transform:translateY(-1px)}
.alert-danger{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;border-radius:10px;font-size:.88rem}
.hint-box{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-top:20px;font-size:.8rem;color:var(--dim);text-align:center}
.hint-box b{color:var(--txt)}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <span class="brand-icon">🔍</span>
      <h1>Change<span>Tracker</span> Pro</h1>
      <p>Website Change Monitoring System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label"><i class="bi bi-envelope me-1"></i>Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="your@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Dashboard
      </button>
    </form>

    <div class="hint-box">
      Use your registered email and password to login
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
