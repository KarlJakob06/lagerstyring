<?php
require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$values = ['name' => '', 'elnummer' => '', 'quantity' => '0', 'min_quantity' => '0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken. Last siden på nytt.';
    } else {
        $values['name']         = trim($_POST['name']         ?? '');
        $values['elnummer']     = trim($_POST['elnummer']     ?? '');
        $values['quantity']     = (int)($_POST['quantity']    ?? 0);
        $values['min_quantity'] = (int)($_POST['min_quantity']?? 0);

        if (!$values['name'])           $errors[] = 'Varenavn er påkrevd.';
        if ($values['quantity'] < 0)    $errors[] = 'Antall kan ikke være negativt.';
        if ($values['min_quantity'] < 0)$errors[] = 'Minimumsantall kan ikke være negativt.';

        // Bildeopplasting
        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Feil ved opplasting av bilde.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'Bildet er for stort (maks 5 MB).';
            } else {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowed_types)) {
                    $errors[] = 'Kun JPEG, PNG, GIF og WebP er tillatt.';
                } else {
                    $ext        = explode('/', $mime)[1];
                    $ext        = $ext === 'jpeg' ? 'jpg' : $ext;
                    $filename   = bin2hex(random_bytes(12)) . '.' . $ext;
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                        $errors[] = 'Kunne ikke lagre bildet. Sjekk at uploads/-mappen er skrivbar.';
                    } else {
                        $image_path = $filename;
                    }
                }
            }
        }

        // Bilde hentet fra EFObasen via knappen i skjemaet?
        if (!$image_path && !empty($_POST['fetched_image'])) {
            $f = basename($_POST['fetched_image']);
            if (preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $f) && is_file(UPLOAD_DIR . $f)) {
                $image_path = $f;
            }
        }

        // Fortsatt ingen bilde? Prøv å hente automatisk fra EFObasen
        if (empty($errors) && !$image_path && $values['elnummer']) {
            require_once __DIR__ . '/includes/product_image.php';
            $auto = fetch_product_image($values['elnummer']);
            if ($auto['ok']) {
                $image_path = $auto['filename'];
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "INSERT INTO items (name, elnummer, quantity, min_quantity, image_path)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $values['name'],
                $values['elnummer'] ?: null,
                $values['quantity'],
                $values['min_quantity'],
                $image_path,
            ]);
            rotate_csrf();
            flash('success', '✅ «' . $values['name'] . '» ble lagt til i lageret.');
            header('Location: index.php');
            exit;
        }
    }
}

$page_title = 'Legg til vare';
$active_nav = 'legg_til';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">＋ Legg til ny vare</h1>
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
          <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required autofocus placeholder="f.eks. Kabelsko 10mm²">
        </div>

        <div class="form-group full">
          <label for="elnummer">Elnummer</label>
          <input type="text" id="elnummer" name="elnummer" value="<?= e($values['elnummer']) ?>" placeholder="f.eks. 1022045">
          <div style="display:flex;gap:.6rem;margin-top:.4rem;align-items:center">
            <button type="button" class="btn btn-outline btn-sm" onclick="fetchImage(this)">🖼 Hent bilde fra elnummer</button>
            <img id="fetch-preview" src="" alt="" style="display:none;width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #e3e7ee">
          </div>
          <span class="hint" id="fetch-msg">Bilde hentes automatisk fra EFObasen (grossistenes felles produktregister) når du lagrer — eller trykk knappen for å se det først.</span>
          <input type="hidden" name="fetched_image" id="fetched_image" value="<?= e($_POST['fetched_image'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="quantity">Antall på lager *</label>
          <input type="number" id="quantity" name="quantity" value="<?= (int)$values['quantity'] ?>" min="0" required>
        </div>

        <div class="form-group">
          <label for="min_quantity">Minimumsantall (advarsel)</label>
          <input type="number" id="min_quantity" name="min_quantity" value="<?= (int)$values['min_quantity'] ?>" min="0">
          <span class="hint">Du får advarsel når antallet er under eller likt dette.</span>
        </div>

        <div class="form-group full">
          <label for="image">Bilde (valgfritt)</label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">JPEG, PNG, GIF eller WebP — maks 5 MB.</span>
        </div>

      </div><!-- /.form-grid -->

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Lagre vare</button>
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
      msg.textContent = '✅ Bilde hentet — lagres sammen med varen.';
      const nameInput = document.getElementById('name');
      if (data.name && nameInput && !nameInput.value.trim()) nameInput.value = data.name;
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
