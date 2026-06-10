<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$errors   = [];
$add_vals = ['username' => '', 'is_admin' => false];

// Legg til bruker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken.';
    } else {
        $add_vals['username'] = trim($_POST['username'] ?? '');
        $pass1                = $_POST['password']  ?? '';
        $pass2                = $_POST['password2'] ?? '';
        $add_vals['is_admin'] = isset($_POST['is_admin']);

        if (!$add_vals['username'])     $errors[] = 'Brukernavn er påkrevd.';
        elseif (strlen($add_vals['username']) < 3) $errors[] = 'Brukernavn må ha minst 3 tegn.';
        elseif (!$pass1)                $errors[] = 'Passord er påkrevd.';
        elseif (strlen($pass1) < 8)     $errors[] = 'Passordet må ha minst 8 tegn.';
        elseif ($pass1 !== $pass2)      $errors[] = 'Passordene stemmer ikke overens.';
        else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $chk->execute([$add_vals['username']]);
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'Brukernavnet «' . e($add_vals['username']) . '» er allerede i bruk.';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, ?)"
                );
                $ins->execute([
                    $add_vals['username'],
                    password_hash($pass1, PASSWORD_DEFAULT),
                    $add_vals['is_admin'] ? 1 : 0,
                ]);
                rotate_csrf();
                flash('success', '✅ Brukeren «' . $add_vals['username'] . '» ble opprettet.');
                header('Location: users.php');
                exit;
            }
        }
    }
}

// Slett bruker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Ugyldig sikkerhetstoken.');
    } else {
        $del_id = (int)($_POST['user_id'] ?? 0);
        if ($del_id === (int)$_SESSION['user_id']) {
            flash('error', 'Du kan ikke slette din egen bruker.');
        } elseif ($del_id) {
            // Flytt brukerens personlige varer til felleslageret først
            $pdo->prepare("UPDATE items SET owner_id = NULL WHERE owner_id = ?")->execute([$del_id]);
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$del_id]);
            rotate_csrf();
            flash('success', 'Brukeren ble slettet. Varene fra det personlige lageret ble flyttet til felleslageret.');
        }
    }
    header('Location: users.php');
    exit;
}

// Bytt admin-status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_admin') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Ugyldig sikkerhetstoken.');
    } else {
        $t_id = (int)($_POST['user_id'] ?? 0);
        if ($t_id === (int)$_SESSION['user_id']) {
            flash('error', 'Du kan ikke endre din egen admin-status.');
        } elseif ($t_id) {
            $upd = $pdo->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?");
            $upd->execute([$t_id]);
            rotate_csrf();
            flash('success', 'Admin-status oppdatert.');
        }
    }
    header('Location: users.php');
    exit;
}

// Tilbakestill passord for en bruker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Ugyldig sikkerhetstoken.');
    } else {
        $r_id  = (int)($_POST['user_id'] ?? 0);
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (!$r_id)                 flash('error', 'Velg en bruker.');
        elseif (strlen($pass1) < 8) flash('error', 'Nytt passord må ha minst 8 tegn.');
        elseif ($pass1 !== $pass2)  flash('error', 'Passordene stemmer ikke overens.');
        else {
            $pdo->prepare("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
                ->execute([password_hash($pass1, PASSWORD_DEFAULT), $r_id]);
            rotate_csrf();
            flash('success', '🔑 Passordet ble tilbakestilt, og kontoen er låst opp.');
        }
    }
    header('Location: users.php');
    exit;
}

// Lås opp en konto som er sperret etter mislykkede innloggingsforsøk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Ugyldig sikkerhetstoken.');
    } else {
        $u_id = (int)($_POST['user_id'] ?? 0);
        if ($u_id) {
            $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$u_id]);
            rotate_csrf();
            flash('success', '🔓 Kontoen ble låst opp.');
        }
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query(
    "SELECT id, username, is_admin, created_at, last_login, locked_until
     FROM users ORDER BY id ASC"
)->fetchAll();

$page_title = 'Brukere';
$active_nav = 'brukere';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">👥 Brukere</h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e_msg): ?><div>❌ <?= e($e_msg) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Brukerliste -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><span style="font-weight:600">Registrerte brukere (<?= count($users) ?>)</span></div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr>
        <th>Brukernavn</th>
        <th>Rolle</th>
        <th>Opprettet</th>
        <th>Sist innlogget</th>
        <th style="width:150px">Handlinger</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u):
        $is_locked = $u['locked_until'] && strtotime($u['locked_until']) > time();
      ?>
        <tr>
          <td>
            <strong><?= e($u['username']) ?></strong>
            <?= (int)$u['id'] === (int)$_SESSION['user_id'] ? ' <span style="font-size:.75rem;color:#68768a">(deg)</span>' : '' ?>
            <?= $is_locked ? ' <span class="badge badge-zero">🔒 Låst</span>' : '' ?>
          </td>
          <td>
            <?php if ($u['is_admin']): ?>
              <span class="badge" style="background:#e0e7ff;color:#3730a3">Administrator</span>
            <?php else: ?>
              <span class="badge" style="background:#f1f5f9;color:#64748b">Bruker</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:#68768a"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
          <td style="font-size:.82rem;color:#68768a"><?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?></td>
          <td>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
              <!-- Bytt admin -->
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" title="<?= $u['is_admin'] ? 'Fjern admin' : 'Gjør til admin' ?>">
                  <?= $u['is_admin'] ? '👤' : '⭐' ?>
                </button>
              </form>
              <?php if ($is_locked): ?>
              <!-- Lås opp -->
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unlock">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Lås opp konto">🔓</button>
              </form>
              <?php endif; ?>
              <!-- Slett -->
              <form method="POST" onsubmit="return confirm('Slett brukeren «<?= e(addslashes($u['username'])) ?>»? Varene i det personlige lageret flyttes til felleslageret.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Slett bruker">🗑</button>
              </form>
              <?php else: ?>
              <span style="font-size:.78rem;color:#a0aab7;padding:.35rem .5rem">–</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tilbakestill passord -->
<div class="card" style="max-width:520px;margin-bottom:1.5rem">
  <div class="card-header"><span style="font-weight:600">🔁 Tilbakestill passord</span></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <div class="form-grid">
        <div class="form-group full">
          <label for="reset_user">Bruker</label>
          <select id="reset_user" name="user_id" required>
            <option value="">— Velg bruker —</option>
            <?php foreach ($users as $u): if ((int)$u['id'] === (int)$_SESSION['user_id']) continue; ?>
            <option value="<?= (int)$u['id'] ?>"><?= e($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Bruk «Bytt passord» i menyen for å endre ditt eget.</span>
        </div>
        <div class="form-group">
          <label for="reset_password">Nytt passord (min. 8 tegn)</label>
          <input type="password" id="reset_password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="reset_password2">Gjenta passord</label>
          <input type="password" id="reset_password2" name="password2" required autocomplete="new-password">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-outline">🔁 Tilbakestill</button>
      </div>
    </form>
  </div>
</div>

<!-- Ny bruker -->
<div class="card" style="max-width:520px">
  <div class="card-header"><span style="font-weight:600">＋ Legg til ny bruker</span></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group full">
          <label for="username">Brukernavn</label>
          <input type="text" id="username" name="username" value="<?= e($add_vals['username']) ?>" required autocomplete="off">
        </div>
        <div class="form-group">
          <label for="password">Passord (min. 8 tegn)</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="password2">Gjenta passord</label>
          <input type="password" id="password2" name="password2" required autocomplete="new-password">
        </div>
        <div class="form-group full">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:400">
            <input type="checkbox" name="is_admin" <?= $add_vals['is_admin'] ? 'checked' : '' ?>>
            Gi administrator-tilgang (kan administrere brukere)
          </label>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">👤 Opprett bruker</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
