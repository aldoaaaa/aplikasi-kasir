<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$pesan = '';
$tipe_pesan = 'info';
$edit_menu = null;

$kategori_options = [
    'Camilan',
    'Minuman',
    'Soto Ayam',
    'Lauk Tambahan',
    'Penyetan Daun Jeruk',
    'Penyetan Nasi Putih',
    'Kebuli Ayam',
    'Paket Rame-Rame',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_menu'])) {
    $nama_menu = trim($_POST['nama_menu'] ?? '');
    $kategori  = trim($_POST['kategori'] ?? '');
    $harga     = (int) ($_POST['harga'] ?? 0);

    if ($nama_menu === '' || $kategori === '' || $harga <= 0) {
        $pesan = 'Nama menu, kategori, dan harga valid wajib diisi.';
        $tipe_pesan = 'error';
    } else {
        $stmt = $pdo->prepare("INSERT INTO menus (nama_menu, kategori, harga, status) VALUES (:nama, :kat, :harga, 'tersedia')");
        $stmt->execute([
            'nama'  => $nama_menu,
            'kat'   => $kategori,
            'harga' => $harga,
        ]);
        $pesan = 'Menu baru berhasil ditambahkan.';
        $tipe_pesan = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_menu'])) {
    $id        = (int) ($_POST['id'] ?? 0);
    $nama_menu = trim($_POST['nama_menu'] ?? '');
    $kategori  = trim($_POST['kategori'] ?? '');
    $harga     = (int) ($_POST['harga'] ?? 0);
    $status    = ($_POST['status'] ?? 'tersedia') === 'habis' ? 'habis' : 'tersedia';

    if ($id <= 0 || $nama_menu === '' || $kategori === '' || $harga <= 0) {
        $pesan = 'Data edit tidak valid. Pastikan nama, kategori, dan harga terisi dengan benar.';
        $tipe_pesan = 'error';
    } else {
        $stmt = $pdo->prepare('
            UPDATE menus
            SET nama_menu = :nama, kategori = :kat, harga = :harga, status = :status
            WHERE id = :id
        ');
        $stmt->execute([
            'id'     => $id,
            'nama'   => $nama_menu,
            'kat'    => $kategori,
            'harga'  => $harga,
            'status' => $status,
        ]);
        header('Location: menu.php?updated=1');
        exit;
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $pesan = 'Perubahan menu / harga berhasil disimpan.';
    $tipe_pesan = 'success';
}

if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    if ($edit_id > 0) {
        $stmtEdit = $pdo->prepare('SELECT * FROM menus WHERE id = :id LIMIT 1');
        $stmtEdit->execute(['id' => $edit_id]);
        $edit_menu = $stmtEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (isset($_GET['toggle_status'])) {
    $id = (int) $_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE menus SET status = IF(status = 'tersedia', 'habis', 'tersedia') WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header('Location: menu.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    try {
        $stmt = $pdo->prepare('DELETE FROM menus WHERE id = :id');
        $stmt->execute(['id' => $id]);
        header('Location: menu.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $pesan = 'Menu ini tidak bisa dihapus karena sudah tercatat di riwayat transaksi. Gunakan "Edit" atau "Ubah Status" menjadi HABIS agar tidak muncul di kasir.';
            $tipe_pesan = 'warning';
        } else {
            $pesan = 'Gagal menghapus menu: ' . $e->getMessage();
            $tipe_pesan = 'error';
        }
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $pesan = 'Menu berhasil dihapus.';
    $tipe_pesan = 'success';
}

$menus = $pdo->query('SELECT * FROM menus ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - AWWAL Kitchen & Eatery</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #1e293b; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .logo-badge { background: #fff; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; }
        .logo-badge img { height: 28px; width: auto; display: block; }
        header a { color: #e2e8f0; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .container { max-width: 1150px; margin: 24px auto; padding: 0 20px; }
        .box { background: #fff; padding: 18px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .form-row { display: grid; grid-template-columns: 2fr 1.5fr 1fr auto; gap: 10px; align-items: end; }
        .edit-row { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto auto; gap: 10px; align-items: end; }
        label { display: block; font-size: 13px; margin-bottom: 4px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        button { padding: 9px 16px; background: #1e293b; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0f172a; }
        .btn-save { background: #15803d; }
        .btn-save:hover { background: #166534; }
        .btn-cancel { display: inline-block; padding: 9px 14px; background: #e2e8f0; color: #1e293b; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: #f1f5f9; }
        .badge-ok { color: #15803d; font-weight: bold; }
        .badge-no { color: #b91c1c; font-weight: bold; }
        .aksi-link { text-decoration: none; font-weight: bold; font-size: 13px; }
        .aksi-edit { color: #1d4ed8; }
        .aksi-status { color: #0f172a; }
        .aksi-hapus { color: #b91c1c; }
        .alert { padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
        .alert-warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        @media (max-width: 900px) {
            .form-row, .edit-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <span class="logo-badge">
            <img src="../assets/images/logo.jpg" alt="AWWAL Kitchen & Eatery">
        </span>
        <strong>AWWAL Kitchen & Eatery</strong> — Kelola Menu
    </div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="menu.php">Kelola Menu</a>
        <a href="laporan.php">Laporan</a>
        <a href="../kasir/transaksi.php">Buka Kasir</a>
        <a href="../logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($tipe_pesan) ?>"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <?php if ($edit_menu !== null): ?>
        <div class="box" style="border: 2px solid #1d4ed8;">
            <h3>Edit Menu (ID #<?= (int) $edit_menu['id'] ?>)</h3>
            <form method="POST" class="edit-row">
                <input type="hidden" name="id" value="<?= (int) $edit_menu['id'] ?>">
                <div>
                    <label>Nama Menu</label>
                    <input type="text" name="nama_menu" value="<?= htmlspecialchars($edit_menu['nama_menu']) ?>" required>
                </div>
                <div>
                    <label>Kategori</label>
                    <select name="kategori" required>
                        <?php foreach ($kategori_options as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>" <?= $edit_menu['kategori'] === $kat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!in_array($edit_menu['kategori'], $kategori_options, true)): ?>
                            <option value="<?= htmlspecialchars($edit_menu['kategori']) ?>" selected>
                                <?= htmlspecialchars($edit_menu['kategori']) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label>Harga (Rp)</label>
                    <input type="number" name="harga" min="500" step="500" value="<?= (int) $edit_menu['harga'] ?>" required>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="tersedia" <?= $edit_menu['status'] === 'tersedia' ? 'selected' : '' ?>>TERSEDIA</option>
                        <option value="habis" <?= $edit_menu['status'] === 'habis' ? 'selected' : '' ?>>HABIS</option>
                    </select>
                </div>
                <button type="submit" name="update_menu" class="btn-save">Simpan Edit</button>
                <a href="menu.php" class="btn-cancel">Batal</a>
            </form>
        </div>
    <?php else: ?>
        <div class="box">
            <h3>Tambah Menu Baru</h3>
            <form method="POST" class="form-row">
                <div>
                    <label>Nama Menu</label>
                    <input type="text" name="nama_menu" placeholder="Contoh: Ayam Geprek" required>
                </div>
                <div>
                    <label>Kategori</label>
                    <select name="kategori" required>
                        <?php foreach ($kategori_options as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Harga (Rp)</label>
                    <input type="number" name="harga" min="500" step="500" required>
                </div>
                <button type="submit" name="tambah_menu">Tambah</button>
            </form>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Menu</th>
                <th>Kategori</th>
                <th>Harga</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menus as $m): ?>
                <tr>
                    <td><?= (int) $m['id'] ?></td>
                    <td><?= htmlspecialchars($m['nama_menu']) ?></td>
                    <td><?= htmlspecialchars($m['kategori']) ?></td>
                    <td>Rp <?= number_format((int) $m['harga'], 0, ',', '.') ?></td>
                    <td>
                        <span class="<?= $m['status'] === 'tersedia' ? 'badge-ok' : 'badge-no' ?>">
                            <?= strtoupper(htmlspecialchars($m['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="?edit=<?= (int) $m['id'] ?>" class="aksi-link aksi-edit">Edit</a> |
                        <a href="?toggle_status=<?= (int) $m['id'] ?>" class="aksi-link aksi-status">Ubah Status</a> |
                        <a href="?hapus=<?= (int) $m['id'] ?>" class="aksi-link aksi-hapus" onclick="return confirm('Hapus menu ini?')">Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>