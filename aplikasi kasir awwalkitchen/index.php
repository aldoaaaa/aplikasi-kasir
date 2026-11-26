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
        :root {
            --bg-cream: #f7efe5;
            --card-bg: #ffffff;
            --navy: #1e293b;
            --navy-soft: #334155;
            --accent: #ea580c;
            --accent-hover: #c2410c;
            --accent-soft: #ffedd5;
            --border: #e6d8c7;
            --text: #1e293b;
            --muted: #64748b;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: radial-gradient(circle at top, #fffaf4 0%, var(--bg-cream) 100%);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 18px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-top: 5px solid var(--accent);
            padding: 30px 28px;
            border-radius: 18px;
            width: 100%;
            max-width: 390px;
            box-shadow: 0 12px 28px rgba(30, 41, 59, 0.08);
            text-align: center;
        }

        .logo-wrap {
            background: #fff;
            border: 1px solid #f1e6d8;
            border-radius: 14px;
            padding: 12px 16px;
            margin: 0 auto 16px;
            display: inline-block;
        }

        .logo-login {
            max-width: 210px;
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        h2 {
            margin: 0 0 6px;
            font-size: 21px;
            font-weight: 800;
            color: var(--navy);
        }

        p.sub {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--navy-soft);
            text-align: left;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text);
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
        }

        button {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 14px rgba(234, 88, 12, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.36);
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <img src="assets/images/logo.jpg" alt="AWWAL Kitchen & Eatery" class="logo-login">
        </div>
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
