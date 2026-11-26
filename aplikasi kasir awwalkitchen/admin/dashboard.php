<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$today = date('Y-m-d');

$stmtOmzet = $pdo->prepare('SELECT COALESCE(SUM(total_harga), 0) AS omzet, COUNT(*) AS jml_trx FROM transactions WHERE DATE(created_at) = :tgl');
$stmtOmzet->execute(['tgl' => $today]);
$ringkasan = $stmtOmzet->fetch(PDO::FETCH_ASSOC);

$jmlMenu = (int) $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();

$stmtTerlaris = $pdo->query('
    SELECT m.nama_menu, SUM(ti.qty) AS total_terjual, SUM(ti.subtotal) AS total_rupiah
    FROM transaction_items ti
    JOIN menus m ON ti.menu_id = m.id
    GROUP BY m.id, m.nama_menu
    ORDER BY total_terjual DESC
    LIMIT 5
');
$terlaris = $stmtTerlaris->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - AWWAL Kitchen & Eatery</title>
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
            margin: 0;
            background: var(--bg-cream);
            color: var(--text);
        }

        header {
            background: var(--navy);
            color: #fff;
            padding: 13px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
        }

        .brand-text {
            font-size: 15px;
            line-height: 1.3;
        }

        .brand-sub {
            font-size: 13px;
            color: #cbd5e1;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-btn {
            color: #f8fafc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.2s ease;
        }

        .nav-btn:hover,
        .nav-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .container {
            max-width: 1180px;
            margin: 24px auto;
            padding: 0 20px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }

        .card {
            background: var(--card-bg);
            padding: 20px 22px;
            border-radius: 16px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--accent);
            box-shadow: 0 6px 18px rgba(30, 41, 59, 0.05);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(30, 41, 59, 0.08);
        }

        .card small {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            display: block;
        }

        .card .val {
            font-size: 26px;
            font-weight: 800;
            margin-top: 8px;
            color: var(--navy);
        }

        .card:first-child .val {
            color: var(--accent-hover);
        }

        .section-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 700;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-badge {
            font-size: 12px;
            background: var(--accent-soft);
            color: var(--accent-hover);
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
        }

        .table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(30, 41, 59, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px 16px;
            border-bottom: 1px solid #f1e6d8;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #fff7ed;
            color: var(--navy-soft);
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fffaf4;
        }

        .qty-pill {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--accent-hover);
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 13px;
        }

        @media (max-width: 820px) {
            .cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand-text">
        <strong>AWWAL Kitchen & Eatery</strong>
        <span class="brand-sub">— Admin: <?= htmlspecialchars($_SESSION['nama']) ?></span>
    </div>
    <div class="nav-links">
        <a class="nav-btn active" href="dashboard.php">Dashboard</a>
        <a class="nav-btn" href="menu.php">Kelola Menu</a>
        <a class="nav-btn" href="laporan.php">Laporan</a>
        <a class="nav-btn" href="../kasir/transaksi.php">Buka Kasir</a>
        <a class="nav-btn" href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <div class="cards">
        <div class="card">
            <small>Omzet Hari Ini (<?= htmlspecialchars($today) ?>)</small>
            <div class="val">Rp <?= number_format((int) $ringkasan['omzet'], 0, ',', '.') ?></div>
        </div>
        <div class="card">
            <small>Transaksi Hari Ini</small>
            <div class="val"><?= (int) $ringkasan['jml_trx'] ?> Transaksi</div>
        </div>
        <div class="card">
            <small>Total Item Menu Aktif</small>
            <div class="val"><?= $jmlMenu ?> Menu</div>
        </div>
    </div>

    <h3 class="section-title">
        5 Menu Terlaris
        <span class="section-badge">Top Performa</span>
    </h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama Menu</th>
                    <th>Terjual</th>
                    <th>Total Penjualan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($terlaris)): ?>
                    <tr><td colspan="3">Belum ada data transaksi.</td></tr>
                <?php else: ?>
                    <?php foreach ($terlaris as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['nama_menu']) ?></strong></td>
                            <td><span class="qty-pill"><?= (int) $row['total_terjual'] ?></span></td>
                            <td><strong>Rp <?= number_format((int) $row['total_rupiah'], 0, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
