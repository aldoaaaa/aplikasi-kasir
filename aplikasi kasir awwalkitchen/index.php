<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'kasir/transaksi.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nama, username, password, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: kasir/transaksi.php');
            }
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AWWAL Kitchen & Eatery</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f5f7; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 16px; box-sizing: border-box; }
        .card { background: #fff; padding: 28px; border-radius: 10px; width: 100%; max-width: 360px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); text-align: center; }
        .logo-login { max-width: 220px; width: 100%; height: auto; margin: 0 auto 12px; display: block; }
        h2 { margin: 0 0 6px; font-size: 20px; }
        p.sub { margin: 0 0 20px; color: #666; font-size: 13px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: bold; text-align: left; }
        input { width: 100%; padding: 10px; margin-bottom: 14px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 11px; background: #1e293b; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0f172a; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left; }
    </style>
</head>
<body>
    <div class="card">
        <img src="assets/images/logo.jpg" alt="AWWAL Kitchen & Eatery" class="logo-login">
        <h2>AWWAL Kitchen & Eatery</h2>
        <p class="sub">Silakan login untuk masuk ke sistem kasir</p>
        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>