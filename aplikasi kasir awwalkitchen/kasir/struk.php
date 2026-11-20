<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$logo_url = '../assets/images/logo.jpg';
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
    SELECT t.*, u.nama AS nama_kasir
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = :id
');
$stmt->execute(['id' => $id]);
$trx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trx) {
    die('Transaksi tidak ditemukan.');
}

$stmtItems = $pdo->prepare('
    SELECT ti.*, m.nama_menu
    FROM transaction_items ti
    JOIN menus m ON ti.menu_id = m.id
    WHERE ti.transaction_id = :id
');
$stmtItems->execute(['id' => $id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk <?= htmlspecialchars($trx['kode_transaksi']) ?></title>
    <style>
        body { font-family: monospace; max-width: 340px; margin: 20px auto; padding: 14px 16px; border: 1px dashed #333; }
        .logo-struk-wrap { text-align: center; margin: 0 auto 4px; line-height: 0; }
        .logo-struk {
            width: 190px;
            height: 62px;
            object-fit: cover;
            object-position: center;
            display: inline-block;
        }
        p.center { text-align: center; margin: 2px 0; font-size: 12px; }
        p { margin: 3px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        td { padding: 2px 0; font-size: 13px; }
        .right { text-align: right; }
        hr { border: none; border-top: 1px dashed #333; margin: 7px 0; }
        .promo-box {
            margin-top: 8px;
            padding-top: 7px;
            border-top: 1px dashed #333;
            text-align: center;
            font-size: 11.5px;
            line-height: 1.45;
        }
        .promo-wa { margin-top: 4px; font-weight: bold; }
        .actions { margin-top: 14px; text-align: center; }
        @media print { .actions { display: none; } body { border: none; margin: 0; } }
    </style>
</head>
<body>
    <div class="logo-struk-wrap">
        <img src="<?= htmlspecialchars($logo_url) ?>" alt="AWWAL Kitchen & Eatery" class="logo-struk">
    </div>
    <p class="center">Kode: <?= htmlspecialchars($trx['kode_transaksi']) ?></p>
    <p class="center"><?= htmlspecialchars($trx['created_at']) ?></p>
    <hr>
    <p>Kasir: <?= htmlspecialchars($trx['nama_kasir']) ?></p>
    <p>Pelanggan: <?= htmlspecialchars($trx['nama_pelanggan']) ?></p>
    <p>Bayar via: <?= htmlspecialchars($trx['metode_bayar']) ?></p>
    <hr>
    <table>
        <?php foreach ($items as $it): ?>
            <tr>
                <td colspan="2"><?= htmlspecialchars($it['nama_menu']) ?></td>
            </tr>
            <tr>
                <td><?= $it['qty'] ?> x Rp <?= number_format($it['harga_satuan'], 0, ',', '.') ?></td>
                <td class="right">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <table>
        <tr><td><strong>TOTAL</strong></td><td class="right"><strong>Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?></strong></td></tr>
        <tr><td>Bayar</td><td class="right">Rp <?= number_format($trx['bayar'], 0, ',', '.') ?></td></tr>
        <tr><td>Kembalian</td><td class="right">Rp <?= number_format($trx['kembalian'], 0, ',', '.') ?></td></tr>
    </table>
    <p class="center">Terima kasih telah berkunjung!</p>
    <div class="promo-box">
        <div>Melayani pesanan nasi box untuk berbagai acara, seperti arisan, syukuran, acara keluarga, dan kantor.</div>
        <div class="promo-wa">WhatsApp: 08980538464</div>
    </div>
    <div class="actions">
        <button onclick="window.print()">Cetak Struk</button>
        <a href="transaksi.php">Transaksi Baru</a>
    </div>
</body>
</html>