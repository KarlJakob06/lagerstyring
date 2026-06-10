<?php
require_once __DIR__ . '/includes/bootstrap.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ugyldig sikkerhetstoken.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new1    = $_POST['new_password']     ?? '';
        $new2    = $_POST['new_password2']    ?? '';

        // Hent nåværende hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$current)                          $errors[] = 'Skriv inn nåværende passord.';
        elseif (!password_verify($current, $user['password_hash'] ?? ''))
                                                $errors[] = 'Nåværende passord er feil.';
        elseif (!$new1)                         $errors[] = 'Skriv inn nytt passord.';
        elseif (strlen($new1) < 8)              $errors[] = 'Nytt passord må ha minst 8 tegn.';
        elseif ($new1 !== $new2)                $errors[] = 'De nye passordene stemmer ikke overens.';
        elseif ($new1 === $current)             $errors[] = 'Nytt passord kan ikke være likt det nåværende.';

        if (empty($errors)) {
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([password_hash($new1, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            rotate_csrf();
            flash('success', '🔑 Passordet ble endret.');
            header('Location: change_password.php');
            exit;
        }
    }
}

$page_title = 'Bytt passord';
$active_nav = 'passord';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">🔑 Bytt passord</h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $err): ?><div>❌ <?= e($err) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:420px">
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group" style="margin-bottom:1.1rem">
        <label for="current_password">Nåværende passord</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group" style="margin-bottom:1.1rem">
        <label for="new_password">Nytt passord (min. 8 tegn)</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
      </div>
      <div class="form-group" style="margin-bottom:1.5rem">
        <label for="new_password2">Gjenta nytt passord</label>
        <input type="password" id="new_password2" name="new_password2" autocomplete="new-password" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Endre passord</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
