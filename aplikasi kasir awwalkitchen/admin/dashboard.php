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
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #1e293b; color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #e2e8f0; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .card small { color: #64748b; font-size: 13px; }
        .card .val { font-size: 24px; font-weight: bold; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        th, td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #f1f5f9; }
        @media (max-width: 768px) { .cards { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <div><strong>AWWAL Kitchen & Eatery</strong> — Admin: <?= htmlspecialchars($_SESSION['nama']) ?></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Kelola Menu</a>
        <a href="laporan.php">Laporan</a>
        <a href="../kasir/transaksi.php">Buka Kasir</a>
        <a href="../logout.php">Logout</a>
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

    <h3>5 Menu Terlaris</h3>
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
                        <td><?= htmlspecialchars($row['nama_menu']) ?></td>
                        <td><?= (int) $row['total_terjual'] ?></td>
                        <td>Rp <?= number_format((int) $row['total_rupiah'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>