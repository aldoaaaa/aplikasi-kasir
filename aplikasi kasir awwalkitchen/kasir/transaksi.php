<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kasir', 'admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$logo_url = '../assets/images/logo.jpg';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_transaksi'])) {
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '') ?: 'Umum';
    $metode_bayar   = $_POST['metode_bayar'] ?? 'Tunai';
    $bayar          = (int) ($_POST['bayar'] ?? 0);
    $items_raw      = $_POST['qty'] ?? [];

    $selected_items = [];
    $total_harga = 0;

    foreach ($items_raw as $menu_id => $qty) {
        $qty = (int) $qty;
        if ($qty > 0) {
            $stmt = $pdo->prepare("SELECT id, nama_menu, harga FROM menus WHERE id = :id AND status = 'tersedia'");
            $stmt->execute(['id' => (int) $menu_id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($menu) {
                $subtotal = $menu['harga'] * $qty;
                $total_harga += $subtotal;
                $selected_items[] = [
                    'menu_id'      => $menu['id'],
                    'nama_menu'    => $menu['nama_menu'],
                    'qty'          => $qty,
                    'harga_satuan' => $menu['harga'],
                    'subtotal'     => $subtotal,
                ];
            }
        }
    }

    if (empty($selected_items)) {
        $error = 'Pilih minimal 1 menu terlebih dahulu.';
    } elseif ($bayar < $total_harga) {
        $error = 'Nominal bayar kurang dari total belanja (Rp ' . number_format($total_harga, 0, ',', '.') . ').';
    } else {
        try {
            $pdo->beginTransaction();
            $kembalian = $bayar - $total_harga;
            $kode_trx  = 'AWL-' . date('YmdHis') . '-' . rand(10, 99);

            $stmtTrx = $pdo->prepare('
                INSERT INTO transactions (kode_transaksi, user_id, nama_pelanggan, metode_bayar, total_harga, bayar, kembalian)
                VALUES (:kode, :user_id, :pelanggan, :metode, :total, :bayar, :kembali)
            ');
            $stmtTrx->execute([
                'kode'      => $kode_trx,
                'user_id'   => $_SESSION['user_id'],
                'pelanggan' => $nama_pelanggan,
                'metode'    => $metode_bayar,
                'total'     => $total_harga,
                'bayar'     => $bayar,
                'kembali'   => $kembalian,
            ]);

            $trx_id = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare('
                INSERT INTO transaction_items (transaction_id, menu_id, qty, harga_satuan, subtotal)
                VALUES (:trx_id, :menu_id, :qty, :harga, :subtotal)
            ');
            foreach ($selected_items as $it) {
                $stmtItem->execute([
                    'trx_id'   => $trx_id,
                    'menu_id'  => $it['menu_id'],
                    'qty'      => $it['qty'],
                    'harga'    => $it['harga_satuan'],
                    'subtotal' => $it['subtotal'],
                ]);
            }

            $pdo->commit();
            header('Location: struk.php?id=' . $trx_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal menyimpan transaksi: ' . $e->getMessage();
        }
    }
}

$kategori_aktif = $_GET['kategori'] ?? 'Semua';
$stmtKat = $pdo->query("SELECT DISTINCT kategori FROM menus WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC");
$daftar_kategori = $stmtKat->fetchAll(PDO::FETCH_COLUMN);

if ($kategori_aktif !== 'Semua') {
    $stmtMenu = $pdo->prepare("SELECT * FROM menus WHERE status = 'tersedia' AND kategori = :kat ORDER BY id ASC");
    $stmtMenu->execute(['kat' => $kategori_aktif]);
} else {
    $stmtMenu = $pdo->query("SELECT * FROM menus WHERE status = 'tersedia' ORDER BY id ASC");
}
$menus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir POS - AWWAL Kitchen & Eatery</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #1e293b; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .logo-badge { background: #fff; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; }
        .logo-badge img { height: 28px; width: auto; display: block; }
        header a { color: #e2e8f0; text-decoration: none; margin-left: 14px; font-size: 14px; }
        .container { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; padding: 20px; max-width: 1300px; margin: auto; }
        .filter-bar { margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 8px; }
        .filter-btn { padding: 7px 12px; border-radius: 20px; border: 1px solid #cbd5e1; background: #fff; text-decoration: none; color: #334155; font-size: 13px; }
        .filter-btn.active { background: #1e293b; color: #fff; border-color: #1e293b; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #f1f5f9; }
        .qty-input { width: 65px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .summary-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); position: sticky; top: 20px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 4px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .total-display { font-size: 22px; font-weight: bold; margin: 12px 0; color: #0f172a; }
        .btn-submit { width: 100%; padding: 12px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <span class="logo-badge">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="AWWAL Kitchen & Eatery">
        </span>
        <strong>AWWAL Kitchen & Eatery</strong> — Kasir: <?= htmlspecialchars($_SESSION['nama']) ?>
    </div>
    <div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="../admin/dashboard.php">Dashboard Admin</a>
        <?php endif; ?>
        <a href="../logout.php">Logout</a>
    </div>
</header>

<form method="POST" id="posForm">
<div class="container">
    <div>
        <div class="filter-bar">
            <a class="filter-btn <?= $kategori_aktif === 'Semua' ? 'active' : '' ?>" href="?kategori=Semua">Semua</a>
            <?php foreach ($daftar_kategori as $kat): ?>
                <a class="filter-btn <?= $kategori_aktif === $kat ? 'active' : '' ?>" href="?kategori=<?= urlencode($kat) ?>">
                    <?= htmlspecialchars($kat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Menu</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nama_menu']) ?></td>
                        <td><?= htmlspecialchars($m['kategori']) ?></td>
                        <td>Rp <?= number_format($m['harga'], 0, ',', '.') ?></td>
                        <td>
                            <input type="number" min="0" value="0" name="qty[<?= $m['id'] ?>]"
                                   data-harga="<?= $m['harga'] ?>" class="qty-input" oninput="hitungTotal()">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="summary-box">
        <h3>Ringkasan Transaksi</h3>
        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label for="nama_pelanggan">Nama Pelanggan</label>
            <input type="text" id="nama_pelanggan" name="nama_pelanggan" placeholder="Umum">
        </div>

        <div class="form-group">
            <label for="metode_bayar">Metode Pembayaran</label>
            <select id="metode_bayar" name="metode_bayar">
                <option value="Tunai">Tunai</option>
                <option value="QRIS">QRIS</option>
                <option value="Transfer">Transfer</option>
            </select>
        </div>

        <div class="total-display">Total: <span id="totalText">Rp 0</span></div>

        <div class="form-group">
            <label for="bayar">Nominal Bayar (Rp)</label>
            <input type="number" id="bayar" name="bayar" min="0" value="0" oninput="hitungTotal()" required>
        </div>

        <p><strong>Kembalian:</strong> <span id="kembaliText">Rp 0</span></p>

        <button type="submit" name="simpan_transaksi" class="btn-submit">Simpan & Cetak Struk</button>
    </div>
</div>
</form>

<script>
function formatRp(num) {
    return 'Rp ' + Math.max(0, num).toLocaleString('id-ID');
}

function hitungTotal() {
    let total = 0;
    document.querySelectorAll('.qty-input').forEach(inp => {
        const qty = parseInt(inp.value) || 0;
        const harga = parseInt(inp.dataset.harga) || 0;
        total += qty * harga;
    });
    const bayar = parseInt(document.getElementById('bayar').value) || 0;
    document.getElementById('totalText').textContent = formatRp(total);
    document.getElementById('kembaliText').textContent = formatRp(bayar - total);
}
</script>
</body>
</html>
