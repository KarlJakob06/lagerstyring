<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/units.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { flash('error', 'Varen ble ikke funnet.'); header('Location: index.php'); exit; }

$item_owner = $item['owner_id'] !== null ? (int)$item['owner_id'] : null;
if (!can_modify_item($item_owner)) {
    flash('error', 'Du har ikke tilgang til å endre denne varen.');
    header('Location: index.php');
    exit;
}
// Tilhører varen en annen bruker (admin-redigering)?
$foreign_owner = $item_owner !== null && $item_owner !== (int)$_SESSION['user_id'];

$errors = [];
$values = $item; // Start med eksisterende data
$values['unit']  = normalize_unit($item['unit'] ?? 'stk');
$values['lager'] = $item_owner === null ? 'felles' : ($foreign_owner ? 'behold' : 'mitt');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken. Last siden på nytt.';
    } else {
        $values['name']         = trim($_POST['name']         ?? '');
        $values['elnummer']     = trim($_POST['elnummer']     ?? '');
        $values['quantity']     = (int)($_POST['quantity']    ?? 0);
        $values['min_quantity'] = (int)($_POST['min_quantity']?? 0);
        $values['unit']         = normalize_unit($_POST['unit'] ?? 'stk');
        $lager_choices          = $foreign_owner ? ['felles', 'mitt', 'behold'] : ['felles', 'mitt'];
        $values['lager']        = in_array($_POST['lager'] ?? '', $lager_choices, true) ? $_POST['lager'] : $values['lager'];

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

        // Bilde valgt via Google-søket i skjemaet?
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
            if ($values['lager'] === 'felles')    $owner_id = null;
            elseif ($values['lager'] === 'mitt')  $owner_id = (int)$_SESSION['user_id'];
            else                                  $owner_id = $item_owner; // 'behold' — blir hos nåværende eier
            $upd = $pdo->prepare(
                "UPDATE items SET name=?, elnummer=?, quantity=?, min_quantity=?, image_path=?, unit=?, owner_id=? WHERE id=?"
            );
            $upd->execute([
                $values['name'],
                $values['elnummer'] ?: null,
                $values['quantity'],
                $values['min_quantity'],
                $image_path,
                $values['unit'],
                $owner_id,
                $id,
            ]);
            // Slett gammelt bilde
            if ($delete_old && $item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])) {
                unlink(UPLOAD_DIR . $item['image_path']);
            }
            rotate_csrf();
            flash('success', '✅ «' . $values['name'] . '» ble oppdatert.');
            header('Location: index.php?lager=' . ($owner_id === (int)$_SESSION['user_id'] ? 'mitt' : 'felles'));
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
          <span class="hint">Valgfritt — brukes også i bildesøket.</span>
        </div>

        <div class="form-group">
          <label for="lager">Lager *</label>
          <select id="lager" name="lager">
            <option value="felles" <?= $values['lager'] === 'felles' ? 'selected' : '' ?>>🗂 Felles lager</option>
            <option value="mitt" <?= $values['lager'] === 'mitt' ? 'selected' : '' ?>>🚐 Mitt lager (<?= e($_SESSION['username'] ?? '') ?>)</option>
            <?php if ($foreign_owner): ?>
            <option value="behold" <?= $values['lager'] === 'behold' ? 'selected' : '' ?>>👤 Behold hos nåværende eier</option>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="unit">Enhet</label>
          <select id="unit" name="unit">
            <option value="stk" <?= $values['unit'] === 'stk' ? 'selected' : '' ?>>Antall (stk)</option>
            <option value="m" <?= $values['unit'] === 'm' ? 'selected' : '' ?>>Meter (m)</option>
          </select>
        </div>

        <div class="form-group">
          <label for="quantity">Beholdning *</label>
          <input type="number" id="quantity" name="quantity" value="<?= (int)$values['quantity'] ?>" min="0" required>
        </div>

        <div class="form-group">
          <label for="min_quantity">Minimumsbeholdning (advarsel)</label>
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
          <div style="margin-top:.5rem">
            <button type="button" class="btn btn-outline btn-sm" id="img-search-btn" onclick="searchImages()">🔍 Søk etter bilde på Google</button>
          </div>
          <div class="img-results" id="img-results"></div>
          <span class="hint" id="fetch-msg">Last opp egen fil (JPEG/PNG/GIF/WebP, maks 5 MB) — eller søk frem et bilde og trykk på det du vil bruke. Erstatter nåværende bilde når du lagrer.</span>
          <input type="hidden" name="fetched_image" id="fetched_image" value="<?= e($_POST['fetched_image'] ?? '') ?>">
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
// Google-bildesøk: søk → klikk på treffet du vil bruke
async function searchImages() {
  const btn = document.getElementById('img-search-btn');
  const msg = document.getElementById('fetch-msg');
  const box = document.getElementById('img-results');
  const q = [document.getElementById('name').value, document.getElementById('elnummer').value]
    .map(s => s.trim()).filter(Boolean).join(' ');
  if (!q) { msg.textContent = 'Fyll inn varenavn eller elnummer først.'; return; }
  btn.disabled = true;
  msg.textContent = '🔍 Søker etter bilder…';
  box.innerHTML = '';
  try {
    const res = await fetch('ajax_image_search.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `q=${encodeURIComponent(q)}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    if (data.ok) {
      msg.textContent = 'Trykk på bildet du vil bruke:';
      data.results.forEach(r => {
        const img = document.createElement('img');
        img.src = r.thumb;
        img.title = r.title;
        img.loading = 'lazy';
        img.onclick = () => pickImage(r.url, img);
        box.appendChild(img);
      });
    } else {
      msg.textContent = '❌ ' + (data.error || 'Søket feilet.');
    }
  } catch (e) {
    msg.textContent = '❌ Noe gikk galt. Prøv igjen.';
  }
  btn.disabled = false;
}

async function pickImage(url, el) {
  const msg = document.getElementById('fetch-msg');
  msg.textContent = '⬇️ Henter bildet…';
  try {
    const res = await fetch('ajax_pick_image.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `url=${encodeURIComponent(url)}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('fetched_image').value = data.filename;
      document.querySelectorAll('#img-results img').forEach(i => i.classList.remove('selected'));
      el.classList.add('selected');
      msg.textContent = '✅ Bilde valgt — erstatter nåværende når du lagrer.';
    } else {
      msg.textContent = '❌ ' + (data.error || 'Kunne ikke hente bildet.');
    }
  } catch (e) {
    msg.textContent = '❌ Kunne ikke hente bildet. Prøv et annet treff.';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
