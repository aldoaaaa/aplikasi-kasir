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

        .filter {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 6px 18px rgba(30, 41, 59, 0.05);
        }

        .filter label {
            font-size: 14px;
            color: var(--navy-soft);
        }

        .filter input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text);
            background: #fff;
        }

        .filter input[type="date"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
        }

        .filter button {
            padding: 9px 18px;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.24);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .filter button:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(234, 88, 12, 0.32);
        }

        .total-pill {
            margin-left: auto;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: var(--accent-hover);
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
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
            padding: 12px 14px;
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

        .metode-badge {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--accent-hover);
            font-weight: 700;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .struk-link {
            color: var(--accent-hover);
            font-weight: 700;
            text-decoration: none;
        }

        .struk-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .total-pill {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="brand-text">
        <strong>AWWAL Kitchen & Eatery</strong>
        <span class="brand-sub">— Laporan Transaksi</span>
    </div>
    <div class="nav-links">
        <a class="nav-btn" href="dashboard.php">Dashboard</a>
        <a class="nav-btn" href="menu.php">Kelola Menu</a>
        <a class="nav-btn active" href="laporan.php">Laporan</a>
        <a class="nav-btn" href="../kasir/transaksi.php">Buka Kasir</a>
        <a class="nav-btn" href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <form method="GET" class="filter">
        <label for="tanggal"><strong>Pilih Tanggal:</strong></label>
        <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
        <button type="submit">Tampilkan</button>
        <span class="total-pill">Total Tanggal Ini: Rp <?= number_format($total_periode, 0, ',', '.') ?></span>
    </form>

    <div class="table-wrap">
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
                            <td><strong><?= htmlspecialchars($r['kode_transaksi']) ?></strong></td>
                            <td><?= htmlspecialchars($r['created_at']) ?></td>
                            <td><?= htmlspecialchars($r['nama_kasir']) ?></td>
                            <td><?= htmlspecialchars($r['nama_pelanggan']) ?></td>
                            <td><span class="metode-badge"><?= htmlspecialchars($r['metode_bayar']) ?></span></td>
                            <td><strong>Rp <?= number_format((int) $r['total_harga'], 0, ',', '.') ?></strong></td>
                            <td><a class="struk-link" href="../kasir/struk.php?id=<?= (int) $r['id'] ?>" target="_blank">Lihat Struk</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
