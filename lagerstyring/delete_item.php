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

$lager = preg_match('/^(felles|mitt|user_\d+)$/', $_POST['lager'] ?? '') ? $_POST['lager'] : 'felles';

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("SELECT name, image_path, owner_id FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if ($item) {
        if (!can_modify_item($item['owner_id'] !== null ? (int)$item['owner_id'] : null)) {
            flash('error', 'Du har ikke tilgang til å slette denne varen.');
            header('Location: index.php?lager=' . $lager);
            exit;
        }
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
header('Location: index.php?lager=' . $lager);
exit;
