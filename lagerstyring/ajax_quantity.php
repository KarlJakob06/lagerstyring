<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF mismatch']);
    exit;
}

$id    = (int)($_POST['id']    ?? 0);
$delta = (int)($_POST['delta'] ?? 0);

if (!$id || !in_array($delta, [-1, 1, -5, 5, -10, 10], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad request']);
    exit;
}

// Tilgangskontroll: felles varer kan endres av alle, personlige kun av eier/admin
$own = $pdo->prepare("SELECT owner_id FROM items WHERE id = ?");
$own->execute([$id]);
$owner = $own->fetch();
if (!$owner) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Varen finnes ikke']);
    exit;
}
if (!can_modify_item($owner['owner_id'] !== null ? (int)$owner['owner_id'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ingen tilgang']);
    exit;
}

// Oppdater — ikke gå under 0 (CASE i stedet for GREATEST for portabilitet)
$stmt = $pdo->prepare(
    "UPDATE items SET quantity = CASE WHEN quantity + ? < 0 THEN 0 ELSE quantity + ? END WHERE id = ?"
);
$stmt->execute([$delta, $delta, $id]);

$row = $pdo->prepare("SELECT quantity FROM items WHERE id = ?");
$row->execute([$id]);
$result = $row->fetch();

echo json_encode(['ok' => true, 'quantity' => (int)($result['quantity'] ?? 0)]);
