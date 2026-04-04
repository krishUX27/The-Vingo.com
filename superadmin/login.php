<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

// Production Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

function super_log($msg) {
    $log_path = __DIR__ . '/../admin/debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_path, "[$time] [SUPERADMIN] $msg\n", FILE_APPEND);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['u'] ?? '');
    $p = $_POST['p'] ?? '';

    if (empty($u) || empty($p)) {
        $error = 'Please enter both System ID and Root Key.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE (username = ? OR email = ?) AND role = 'superadmin' AND is_deleted = 0 LIMIT 1");
        if (!$stmt) {
            super_log("Query error: " . $conn->error);
            $error = 'System fault. Check logs.';
        } else {
            $stmt->bind_param('ss', $u, $u);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($p, $row['password'])) {
                    super_log("Superadmin '$u' authenticated.");
                    $_SESSION['super_logged_in'] = true;
                    $_SESSION['super_username']  = $row['username'];
                    
                    // Log Success
                    platform_log("Root Access Granted", $u, "VERIFIED");
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Incorrect Root Key.';
                }
            } else {
                // Check if account exists but is a regular Admin
                $check = $conn->prepare("SELECT role FROM users WHERE username = ? OR email = ? LIMIT 1");
                $check->bind_param('ss', $u, $u);
                $check->execute();
                $res = $check->get_result();
                if ($r = $res->fetch_assoc()) {
                    if ($r['role'] === 'admin') {
                        $error = "This account is a regular Admin. Please use the Operator Panel to sign in.";
                    } else {
                        $error = "Access Denied: System ID not found.";
                    }
                } else {
                    $error = "Access Denied: System ID not found.";
                }
            }
            $stmt->close();
        }
    }
}

if (isset($_SESSION['super_logged_in'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Superadmin | Vingo Master</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@800&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    :root { --super-bg: #0f172a; --super-accent: #f59e0b; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:var(--super-bg); display:flex; align-items:center; justify-content:center; height:100vh; color:#fff; overflow:hidden; }
    .card { width:400px; padding:40px; background:#1e293b; border-radius:30px; border:1px solid rgba(255,255,255,0.05); box-shadow:0 30px 60px rgba(0,0,0,0.4); text-align:center; position:relative; }
    .card::before { content:''; position:absolute; top:-2px; left:50%; transform:translateX(-50%); width:60px; height:4px; background:var(--super-accent); border-radius:2px; }
    h1 { font-family:'Montserrat',sans-serif; font-size:1.8rem; margin-bottom:10px; color:var(--super-accent); }
    p { color:#94a3b8; font-size:0.9rem; margin-bottom:30px; }
    .input-grp { margin-bottom:20px; text-align:left; }
    label { display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase; font-weight:700; margin-bottom:8px; }
    input { width:100%; padding:14px; background:#0f172a; border:1px solid #334155; border-radius:12px; color:#fff; font-family:inherit; outline:none; }
    input:focus { border-color:var(--super-accent); }
    button { width:100%; padding:16px; background:var(--super-accent); color:#0f172a; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:1rem; margin-top:10px; transition:0.2s; }
    button:hover { background:#fbbf24; transform:translateY(-2px); box-shadow:0 8px 20px rgba(245,158,11,0.2); }
    .error { background:#450a0a; color:#f87171; padding:12px; border-radius:10px; font-size:0.85rem; margin-bottom:20px; border:1px solid #7f1d1d; }
    .back { display:inline-block; margin-top:30px; font-size:0.8rem; color:#475569; text-decoration:none; }
    .back:hover { color:#94a3b8; }
    
    /* ── Animations ── */
    .shake { animation: shake 0.5s ease-in-out; }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20% { transform: translateX(-10px); }
      40% { transform: translateX(10px); }
      60% { transform: translateX(-8px); }
      80% { transform: translateX(8px); }
    }
  </style>
</head>
<body>

<div class="card <?= $error ? 'shake' : '' ?>">
  <h1>Vingo Master</h1>
  <p>System Root Management Layer</p>
  
  <?php if ($error): ?>
    <div class="error">🔒 <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="input-grp">
      <label>System Login (Username/Email)</label>
      <input type="text" name="u" placeholder="Username or Email" required autofocus autocomplete="off">
    </div>
    <div class="input-grp">
      <label>Root Key</label>
      <div style="position:relative">
        <input type="password" id="p" name="p" placeholder="••••••••" required style="width:100%; padding-right:45px">
        <span id="togglePassword" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:1.1rem; opacity:0.6; transition:0.2s">👁️</span>
      </div>
    </div>
    <button type="submit">Initialize Root Console</button>
  </form>

  <script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#p');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });
  </script>

  <a href="../admin/index.php" class="back">← Exit to Operator Panel</a>
</div>

</body>
</html>
