<?php
require_once __DIR__ . '/../config/database.php';

$nama = 'Admin Awwal';
$username = 'username_baru';
$passwordAsli = 'password_baru';
$role = 'admin';

$hash = password_hash($passwordAsli, PASSWORD_DEFAULT);

$cek = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$cek->execute(['username' => $username]);

if ($cek->fetch()) {
    $stmt = $pdo->prepare('UPDATE users SET nama = :nama, password = :password, role = :role WHERE username = :username');
    $stmt->execute([
        'nama' => $nama,
        'password' => $hash,
        'role' => $role,
        'username' => $username
    ]);
    echo "Akun '$username' berhasil di-update.";
} else {
    $stmt = $pdo->prepare('INSERT INTO users (nama, username, password, role) VALUES (:nama, :username, :password, :role)');
    $stmt->execute([
        'nama' => $nama,
        'username' => $username,
        'password' => $hash,
        'role' => $role
    ]);
    echo "Akun '$username' berhasil dibuat.";
}