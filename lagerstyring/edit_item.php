<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error', 'Varen ble ikke funnet.'); header('Location: index.php'); exit; }

$errors = [];
$values = $item; // Start med eksisterende data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken. Last siden på nytt.';
    } else {
        $values['name']         = trim($_POST['name']         ?? '');
        $values['elnummer']     = trim($_POST['elnummer']     ?? '');
        $values['quantity']     = (int)($_POST['quantity']    ?? 0);
        $values['min_quantity'] = (int)($_POST['min_quantity']?? 0);

        if (!$values['name'])            $errors[] = 'Varenavn er påkrevd.';
        if ($values['quantity'] < 0)     $errors[] = 'Antall kan ikke være negativt.';
        if ($values['min_quantity'] < 0) $errors[] = 'Minimumsantall kan ikke være negativt.';

        // Nytt bilde?
        $image_path = $item['image_path']; // Behold eksisterende
        $delete_old = false;

        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Feil ved opplasting av bilde.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'Bildet er for stort (maks 5 MB).';
            } else {
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                $finfo   = new finfo(FILEINFO_MIME_TYPE);
                $mime    = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowed)) {
                    $errors[] = 'Kun JPEG, PNG, GIF og WebP er tillatt.';
                } else {
                    $ext      = explode('/', $mime)[1];
                    $ext      = $ext === 'jpeg' ? 'jpg' : $ext;
                    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                        $errors[] = 'Kunne ikke lagre bildet. Sjekk at uploads/-mappen er skrivbar.';
                    } else {
                        $delete_old = true;
                        $image_path = $filename;
                    }
                }
            }
        }

        // Bilde hentet fra EFObasen via knappen i skjemaet?
        if (!$delete_old && empty($_FILES['image']['name']) && !empty($_POST['fetched_image'])) {
            $f = basename($_POST['fetched_image']);
            if (preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $f) && is_file(UPLOAD_DIR . $f)) {
                $delete_old = (bool)$item['image_path'];
                $image_path = $f;
            }
        }

        // Fjern bilde?
        if (isset($_POST['delete_image']) && !$delete_old) {
            if ($item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])) {
                unlink(UPLOAD_DIR . $item['image_path']);
            }
            $image_path = null;
        }

        if (empty($errors)) {
            $upd = $pdo->prepare(
                "UPDATE items SET name=?, elnummer=?, quantity=?, min_quantity=?, image_path=? WHERE id=?"
            );
            $upd->execute([
                $values['name'],
                $values['elnummer'] ?: null,
                $values['quantity'],
                $values['min_quantity'],
                $image_path,
                $id,
            ]);
            // Slett gammelt bilde
            if ($delete_old && $item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])) {
                unlink(UPLOAD_DIR . $item['image_path']);
            }
            rotate_csrf();
            flash('success', '✅ «' . $values['name'] . '» ble oppdatert.');
            header('Location: index.php');
            exit;
        }
    }
}

$page_title = 'Rediger vare';
$active_nav = 'lager';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">✏️ Rediger vare</h1>
  <a href="index.php" class="btn btn-outline">← Tilbake til lager</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $err): ?><div>❌ <?= e($err) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-grid">

        <div class="form-group full">
          <label for="name">Varenavn *</label>
          <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required autofocus>
        </div>

        <div class="form-group full">
          <label for="elnummer">Elnummer</label>
          <input type="text" id="elnummer" name="elnummer" value="<?= e($values['elnummer'] ?? '') ?>">
          <div style="display:flex;gap:.6rem;margin-top:.4rem;align-items:center">
            <button type="button" class="btn btn-outline btn-sm" onclick="fetchImage(this)">🖼 Hent bilde fra elnummer</button>
            <img id="fetch-preview" src="" alt="" style="display:none;width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #e3e7ee">
          </div>
          <span class="hint" id="fetch-msg">Henter produktbilde fra EFObasen — erstatter nåværende bilde når du lagrer.</span>
          <input type="hidden" name="fetched_image" id="fetched_image" value="<?= e($_POST['fetched_image'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="quantity">Antall på lager *</label>
          <input type="number" id="quantity" name="quantity" value="<?= (int)$values['quantity'] ?>" min="0" required>
        </div>

        <div class="form-group">
          <label for="min_quantity">Minimumsantall (advarsel)</label>
          <input type="number" id="min_quantity" name="min_quantity" value="<?= (int)$values['min_quantity'] ?>" min="0">
        </div>

        <div class="form-group full">
          <label>Bilde</label>
          <?php if ($values['image_path'] && file_exists(UPLOAD_DIR . $values['image_path'])): ?>
          <div style="margin-bottom:.75rem;display:flex;align-items:center;gap:.75rem">
            <img src="<?= e(UPLOAD_URL . rawurlencode($values['image_path'])) ?>"
                 alt="Nåværende bilde" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid #dde2ec">
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;cursor:pointer;font-size:.875rem;color:#c53030">
              <input type="checkbox" name="delete_image"> Slett nåværende bilde
            </label>
          </div>
          <label for="image" style="font-weight:400;font-size:.85rem;color:#68768a">Last opp nytt bilde (erstatter nåværende):</label>
          <?php else: ?>
          <label for="image" style="font-weight:400;font-size:.85rem;color:#68768a">Ingen bilde — last opp ett valgfritt:</label>
          <?php endif; ?>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-top:.4rem">
          <span class="hint">JPEG, PNG, GIF eller WebP — maks 5 MB.</span>
        </div>

      </div><!-- /.form-grid -->

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Lagre endringer</button>
        <a href="index.php" class="btn btn-outline">Avbryt</a>
      </div>
    </form>
  </div>
</div>

<script>
async function fetchImage(btn) {
  const el  = document.getElementById('elnummer').value.trim();
  const msg = document.getElementById('fetch-msg');
  if (!el) { msg.textContent = 'Fyll inn elnummer først.'; return; }
  btn.disabled = true;
  msg.textContent = 'Henter bilde fra EFObasen…';
  try {
    const res = await fetch('ajax_fetch_image.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `elnummer=${encodeURIComponent(el)}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('fetched_image').value = data.filename;
      const prev = document.getElementById('fetch-preview');
      prev.src = data.url;
      prev.style.display = 'inline-block';
      msg.textContent = '✅ Bilde hentet — erstatter nåværende når du lagrer.';
    } else {
      msg.textContent = '❌ ' + (data.error || 'Fant ikke bilde.');
    }
  } catch (e) {
    msg.textContent = '❌ Noe gikk galt. Prøv igjen.';
  }
  btn.disabled = false;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
