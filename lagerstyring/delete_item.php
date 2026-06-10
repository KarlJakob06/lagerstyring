<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Ugyldig sikkerhetstoken.');
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("SELECT name, image_path FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if ($item) {
        $del = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $del->execute([$id]);

        // Slett bilde fra disk
        if ($item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])) {
            unlink(UPLOAD_DIR . $item['image_path']);
        }
        flash('success', '🗑 «' . $item['name'] . '» ble slettet.');
    }
}

rotate_csrf();
header('Location: index.php');
exit;
