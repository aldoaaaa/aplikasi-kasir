<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kasir', 'admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$logo_url = '../assets/images/logo.jpg';
$error = '';

$gambar_kategori = [
    'Camilan'             => '../assets/images/kreasi-camilan-dari-kentang-paling-enak-camilan-dari-kentang-resep-camilan-kentang-cara-membuat-camilan-kentang-resep-french-fries-kentang-goreng-9cde86371d7fc78c91ae80a6ffab250e-733d02774b17fbcd87af1ce2143ce28a.webp',
    'Lauk Tambahan'       => '../assets/images/ayam-penyet-atau-penyetan-ayam-ayam-goreng-disajikan-dengan-sambal-pedas_511235-10920.webp',
    'Paket Rame-Rame'     => '../assets/images/paket-rame-rame.webp',
    'Penyetan Daun Jeruk' => '../assets/images/ayam-penyet-atau-penyetan-ayam-ayam-goreng-disajikan-dengan-sambal-pedas_511235-10920.webp',
    'Penyetan Nasi Putih' => '../assets/images/ayam-penyet-atau-penyetan-ayam-ayam-goreng-disajikan-dengan-sambal-pedas_511235-10920.webp',
    'Kebuli Ayam'         => '../assets/images/nasi-kebuli-ayam-chicken-rice-signature-dish-combines-deliciousnees-spiced-perfectly-cooked-various-417600013.webp',
    'Minuman'             => '../assets/images/pexels-photo-792613.webp',
    'Soto Ayam'           => '../assets/images/soto-ayam1.webp',
];

function getGambarMenu(array $menu, array $mapKategori): string
{
    $kat = trim((string) ($menu['kategori'] ?? ''));
    $file = '';

    if (isset($mapKategori[$kat])) {
        $file = basename($mapKategori[$kat]);
    } else {
        $lower = strtolower($kat);
        if (strpos($lower, 'camilan') !== false) {
            $file = 'kreasi-camilan-dari-kentang-paling-enak-camilan-dari-kentang-resep-camilan-kentang-cara-membuat-camilan-kentang-resep-french-fries-kentang-goreng-9cde86371d7fc78c91ae80a6ffab250e-733d02774b17fbcd87af1ce2143ce28a.webp';
        } elseif (strpos($lower, 'lauk') !== false || strpos($lower, 'penyetan') !== false) {
            $file = 'ayam-penyet-atau-penyetan-ayam-ayam-goreng-disajikan-dengan-sambal-pedas_511235-10920.webp';
        } elseif (strpos($lower, 'rame') !== false || strpos($lower, 'paket') !== false) {
            $file = 'paket-rame-rame.webp';
        } elseif (strpos($lower, 'kebuli') !== false) {
            $file = 'nasi-kebuli-ayam-chicken-rice-signature-dish-combines-deliciousnees-spiced-perfectly-cooked-various-417600013.webp';
        } elseif (strpos($lower, 'minuman') !== false) {
            $file = 'pexels-photo-792613.webp';
        } elseif (strpos($lower, 'soto') !== false) {
            $file = 'soto-ayam1.webp';
        }
    }

    if ($file === '') {
        return '';
    }

    $kandidatRelatif = [
        '../assets/images/' . $file,
        '../../assets/images/' . $file,
    ];

    foreach ($kandidatRelatif as $relPath) {
        if (is_file(__DIR__ . '/' . $relPath)) {
            return $relPath;
        }
    }

    return '../assets/images/' . $file;
}

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
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-badge {
            background: #fff;
            padding: 4px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
        }

        .logo-badge img {
            height: 28px;
            width: auto;
            display: block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-btn {
            color: #f8fafc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
        }

        .container {
            display: grid;
            grid-template-columns: 2.1fr 1fr;
            gap: 22px;
            padding: 22px;
            max-width: 1360px;
            margin: auto;
            align-items: start;
        }

        .filter-bar {
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            text-decoration: none;
            color: var(--navy-soft);
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .filter-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }

        .filter-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .menu-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 3px 10px rgba(30, 41, 59, 0.05);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .menu-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(30, 41, 59, 0.08);
            border-color: #fdba74;
        }

        .menu-thumb-box {
            width: 82px;
            height: 82px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffedd5, #fed7aa);
            color: var(--accent-hover);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }

        .menu-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .menu-info {
            flex: 1;
            min-width: 0;
        }

        .menu-kat {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            color: var(--accent-hover);
            background: var(--accent-soft);
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 5px;
        }

        .menu-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 6px;
            line-height: 1.35;
        }

        .menu-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .menu-price {
            font-size: 15px;
            font-weight: 800;
            color: var(--navy);
        }

        .qty-control {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 999px;
            padding: 3px 6px;
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: background 0.15s ease;
        }

        .qty-btn:hover {
            background: var(--accent-hover);
        }

        .qty-input {
            width: 38px;
            border: none;
            background: transparent;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
            padding: 2px 0;
        }

        .qty-input:focus {
            outline: none;
        }

        .summary-box {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.08);
            position: sticky;
            top: 20px;
        }

        .summary-box h3 {
            margin: 0 0 16px;
            font-size: 18px;
            color: var(--navy);
            border-bottom: 2px solid var(--bg-cream);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
            font-weight: 700;
            color: var(--navy-soft);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
        }

        .total-card {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 14px 0;
        }

        .total-display {
            font-size: 22px;
            font-weight: 800;
            color: var(--accent-hover);
        }

        .kembali-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            margin: 12px 0 16px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
        }

        .btn-submit {
            width: 100%;
            padding: 13px 16px;
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

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.36);
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 13px;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .container { grid-template-columns: 1fr; }
            .summary-box { position: static; }
        }

        @media (max-width: 640px) {
            .menu-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <span class="logo-badge">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="AWWAL Kitchen & Eatery">
        </span>
        <div>
            <strong>AWWAL Kitchen & Eatery</strong>
            <span style="font-size: 13px; color: #cbd5e1;"> — Kasir: <?= htmlspecialchars($_SESSION['nama']) ?></span>
        </div>
    </div>
    <div class="nav-links">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a class="nav-btn" href="../admin/dashboard.php">Dashboard Admin</a>
        <?php endif; ?>
        <a class="nav-btn" href="../logout.php">Logout</a>
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

        <div class="menu-grid">
            <?php foreach ($menus as $m): ?>
                <?php
                    $kata = preg_split('/\s+/', trim($m['nama_menu']));
                    $inisial = strtoupper(substr($kata[0] ?? 'M', 0, 1) . substr($kata[1] ?? '', 0, 1));
                    $imgSrc = getGambarMenu($m, $gambar_kategori);
                ?>
                <div class="menu-card">
                    <div class="menu-thumb-box">
                        <?php if ($imgSrc !== ''): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($m['nama_menu']) ?>"
                                 onerror="this.remove(); this.parentElement.textContent='<?= htmlspecialchars($inisial) ?>';">
                        <?php else: ?>
                            <?= htmlspecialchars($inisial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="menu-info">
                        <span class="menu-kat"><?= htmlspecialchars($m['kategori']) ?></span>
                        <h4 class="menu-title"><?= htmlspecialchars($m['nama_menu']) ?></h4>
                        <div class="menu-bottom">
                            <span class="menu-price">Rp <?= number_format($m['harga'], 0, ',', '.') ?></span>
                            <div class="qty-control">
                                <button type="button" class="qty-btn" onclick="ubahQty(<?= (int) $m['id'] ?>, -1)">-</button>
                                <input type="number" min="0" value="0" id="qty_<?= (int) $m['id'] ?>"
                                       name="qty[<?= (int) $m['id'] ?>]" data-harga="<?= (int) $m['harga'] ?>"
                                       class="qty-input" oninput="hitungTotal()">
                                <button type="button" class="qty-btn" onclick="ubahQty(<?= (int) $m['id'] ?>, 1)">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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

        <div class="total-card">
            <div style="font-size: 12px; color: #7c2d12; font-weight: 700; margin-bottom: 2px;">Total Pembayaran</div>
            <div class="total-display"><span id="totalText">Rp 0</span></div>
        </div>

        <div class="form-group">
            <label for="bayar">Nominal Bayar (Rp)</label>
            <input type="number" id="bayar" name="bayar" min="0" value="0" oninput="hitungTotal()" required>
        </div>

        <div class="kembali-row">
            <strong>Kembalian</strong>
            <strong id="kembaliText" style="color: #15803d;">Rp 0</strong>
        </div>

        <button type="submit" name="simpan_transaksi" class="btn-submit">Simpan & Cetak Struk</button>
    </div>
</div>
</form>

<script>
function formatRp(num) {
    return 'Rp ' + Math.max(0, num).toLocaleString('id-ID');
}

function ubahQty(menuId, delta) {
    const input = document.getElementById('qty_' + menuId);
    if (!input) return;
    const sekarang = parseInt(input.value) || 0;
    input.value = Math.max(0, sekarang + delta);
    hitungTotal();
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
