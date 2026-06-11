<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/units.php';

$is_admin    = !empty($_SESSION['is_admin']);
$other_users = $is_admin
    ? $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username ASC")
    : null;
if ($other_users) { $other_users->execute([(int)$_SESSION['user_id']]); $other_users = $other_users->fetchAll(); }
else { $other_users = []; }

/** Valider lager-valg: 'felles', 'mitt' eller (kun admin) 'user_<id>'. */
function normalize_lager(string $lager, bool $is_admin, array $other_users): string {
    if ($lager === 'mitt') return 'mitt';
    if ($is_admin && preg_match('/^user_(\d+)$/', $lager, $m)
        && in_array((int)$m[1], array_column($other_users, 'id'), false)) {
        return $lager;
    }
    return 'felles';
}

$errors = [];
$values = [
    'name' => '', 'elnummer' => '', 'quantity' => '0', 'min_quantity' => '0',
    'unit'  => 'auto',
    'lager' => normalize_lager($_GET['lager'] ?? 'felles', $is_admin, $other_users),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken. Last siden på nytt.';
    } else {
        $values['name']         = trim($_POST['name']         ?? '');
        $values['elnummer']     = trim($_POST['elnummer']     ?? '');
        $values['quantity']     = (int)($_POST['quantity']    ?? 0);
        $values['min_quantity'] = (int)($_POST['min_quantity']?? 0);
        $values['unit']         = in_array($_POST['unit'] ?? '', ['auto', 'stk', 'm'], true) ? $_POST['unit'] : 'auto';
        $values['lager']        = normalize_lager($_POST['lager'] ?? 'felles', $is_admin, $other_users);

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

        // Fortsatt ingen bilde? Prøv bilde valgt via Google-søket i skjemaet
        if (!$image_path && !empty($_POST['fetched_image'])) {
            $f = basename($_POST['fetched_image']);
            if (preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $f) && is_file(UPLOAD_DIR . $f)) {
                $image_path = $f;
            }
        }

        if (empty($errors)) {
            $unit = $values['unit'] === 'auto' ? detect_unit($values['name']) : $values['unit'];
            if ($values['lager'] === 'mitt')                         $owner_id = (int)$_SESSION['user_id'];
            elseif (preg_match('/^user_(\d+)$/', $values['lager'], $m)) $owner_id = (int)$m[1];
            else                                                     $owner_id = null;

            $stmt = $pdo->prepare(
                "INSERT INTO items (name, elnummer, quantity, min_quantity, image_path, unit, owner_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $values['name'],
                $values['elnummer'] ?: null,
                $values['quantity'],
                $values['min_quantity'],
                $image_path,
                $unit,
                $owner_id,
            ]);
            if ($values['lager'] === 'mitt')      $lager_navn = 'ditt lager';
            elseif ($owner_id !== null) {
                $u = array_values(array_filter($other_users, fn($x) => (int)$x['id'] === $owner_id));
                $lager_navn = ($u[0]['username'] ?? 'brukeren') . ' sitt lager';
            } else                                $lager_navn = 'felleslageret';

            rotate_csrf();
            flash('success', '✅ «' . $values['name'] . '» ble lagt til i '
                . $lager_navn . ' (måles i ' . unit_label($unit) . ').');
            header('Location: index.php?lager=' . $values['lager']);
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
          <span class="hint">Valgfritt — elnummer fra katalog/grossist. Brukes også i bildesøket.</span>
        </div>

        <div class="form-group">
          <label for="lager">Lager *</label>
          <select id="lager" name="lager">
            <option value="felles" <?= $values['lager'] === 'felles' ? 'selected' : '' ?>>🗂 Felles lager</option>
            <option value="mitt" <?= $values['lager'] === 'mitt' ? 'selected' : '' ?>>🚐 Mitt lager (<?= e($_SESSION['username'] ?? '') ?>)</option>
            <?php foreach ($other_users as $ou): ?>
            <option value="user_<?= (int)$ou['id'] ?>" <?= $values['lager'] === 'user_' . (int)$ou['id'] ? 'selected' : '' ?>>👤 <?= e($ou['username']) ?> sitt lager</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="unit">Enhet</label>
          <select id="unit" name="unit">
            <option value="auto" <?= $values['unit'] === 'auto' ? 'selected' : '' ?>>Automatisk</option>
            <option value="stk" <?= $values['unit'] === 'stk' ? 'selected' : '' ?>>Antall (stk)</option>
            <option value="m" <?= $values['unit'] === 'm' ? 'selected' : '' ?>>Meter (m)</option>
          </select>
          <span class="hint" id="unit-hint">Kabel, ledning og rør får automatisk meter — resten stk.</span>
        </div>

        <div class="form-group">
          <label for="quantity">Beholdning *</label>
          <input type="number" id="quantity" name="quantity" value="<?= (int)$values['quantity'] ?>" min="0" required>
        </div>

        <div class="form-group">
          <label for="min_quantity">Minimumsbeholdning (advarsel)</label>
          <input type="number" id="min_quantity" name="min_quantity" value="<?= (int)$values['min_quantity'] ?>" min="0">
          <span class="hint">Du får advarsel når beholdningen er under eller likt dette.</span>
        </div>

        <div class="form-group full">
          <label for="image">Bilde (valgfritt)</label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
          <div style="margin-top:.5rem">
            <button type="button" class="btn btn-outline btn-sm" id="img-search-btn" onclick="searchImages()">🔍 Søk etter bilde på Google</button>
          </div>
          <div class="img-results" id="img-results"></div>
          <span class="hint" id="fetch-msg">Last opp egen fil (JPEG/PNG/GIF/WebP, maks 5 MB) — eller søk frem et bilde og trykk på det du vil bruke.</span>
          <input type="hidden" name="fetched_image" id="fetched_image" value="<?= e($_POST['fetched_image'] ?? '') ?>">
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
// Live enhetsdeteksjon — speiler detect_unit() i includes/units.php
const meterRe = /kabel|ledning|wire|rør|slange|snor|lisse|\b(pfxp|pfsp|pn|pr|rk|ix|nym|liyy|liycy|h0[57]|eq|rq|fk)\b/i;
const stkRe   = /sko\b|strips|binder|klips|clips|merk|skjøt|gjennomf|innf|feste|klemme|muffe|holder|deksel|lokk|nippel|plugg|endehylse|beskytter|sperre/i;
function detectUnitJs(name) {
  return meterRe.test(name) && !stkRe.test(name) ? 'm' : 'stk';
}
function updateUnitHint() {
  const sel  = document.getElementById('unit');
  const hint = document.getElementById('unit-hint');
  const name = document.getElementById('name').value.trim();
  if (sel.value === 'auto' && name) {
    hint.textContent = 'Automatisk: «' + name.substring(0, 40) + '» registreres i '
      + (detectUnitJs(name) === 'm' ? 'meter (m) 📏' : 'antall (stk) 🔢') + '.';
  } else if (sel.value === 'auto') {
    hint.textContent = 'Kabel, ledning og rør får automatisk meter — resten stk.';
  } else {
    hint.textContent = '';
  }
}
document.getElementById('name').addEventListener('input', updateUnitHint);
document.getElementById('unit').addEventListener('change', updateUnitHint);
updateUnitHint();

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
      msg.textContent = '✅ Bilde valgt — lagres sammen med varen.';
    } else {
      msg.textContent = '❌ ' + (data.error || 'Kunne ikke hente bildet.');
    }
  } catch (e) {
    msg.textContent = '❌ Kunne ikke hente bildet. Prøv et annet treff.';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
