<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$stmt = $pdo->prepare('
    SELECT t.*, u.nama AS nama_kasir
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE DATE(t.created_at) = :tgl
    ORDER BY t.created_at DESC
');
$stmt->execute(['tgl' => $tanggal]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_periode = array_sum(array_column($rows, 'total_harga'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - AWWAL Kitchen & Eatery</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #1e293b; color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #e2e8f0; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 20px; }
        .filter { background: #fff; padding: 14px; border-radius: 8px; margin-bottom: 18px; display: flex; gap: 10px; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
<header>
    <div><strong>AWWAL Kitchen & Eatery</strong> — Laporan Transaksi</div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Kelola Menu</a>
        <a href="laporan.php">Laporan</a>
        <a href="../kasir/transaksi.php">Buka Kasir</a>
        <a href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <form method="GET" class="filter">
        <label for="tanggal"><strong>Pilih Tanggal:</strong></label>
        <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
        <button type="submit">Tampilkan</button>
        <span style="margin-left:auto;"><strong>Total Tanggal Ini: Rp <?= number_format($total_periode, 0, ',', '.') ?></strong></span>
    </form>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Waktu</th>
                <th>Kasir</th>
                <th>Pelanggan</th>
                <th>Metode</th>
                <th>Total</th>
                <th>Struk</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">Tidak ada transaksi pada tanggal ini.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['kode_transaksi']) ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td><?= htmlspecialchars($r['nama_kasir']) ?></td>
                        <td><?= htmlspecialchars($r['nama_pelanggan']) ?></td>
                        <td><?= htmlspecialchars($r['metode_bayar']) ?></td>
                        <td>Rp <?= number_format((int) $r['total_harga'], 0, ',', '.') ?></td>
                        <td><a href="../kasir/struk.php?id=<?= (int) $r['id'] ?>" target="_blank">Lihat Struk</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>