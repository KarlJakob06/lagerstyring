<?php
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, password_hash, is_admin FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = (bool) $user['is_admin'];
            header('Location: index.php');
            exit;
        } else {
            // Bevisst vag feilmelding
            $error = 'Feil brukernavn eller passord.';
        }
    } else {
        $error = 'Fyll inn brukernavn og passord.';
    }
}
?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Innlogging — <?= htmlspecialchars(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#eef1f6;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1rem}
.card{background:#fff;border-radius:14px;box-shadow:0 4px 28px rgba(0,0,0,.13);max-width:400px;width:100%;overflow:hidden}
.card-top{background:#1a6fe8;padding:2.25rem 2rem 1.75rem;text-align:center;color:#fff}
.logo{font-size:2.4rem;margin-bottom:.4rem}
h1{font-size:1.25rem;font-weight:700;letter-spacing:-.2px}
.subtitle{font-size:.82rem;color:rgba(255,255,255,.6);margin-top:.3rem}
.card-body{padding:1.75rem 2rem 2rem}
.form-group{margin-bottom:1.1rem}
label{display:block;font-size:.83rem;font-weight:600;margin-bottom:.4rem;color:#1a2535}
input[type=text],input[type=password]{
  width:100%;padding:.62rem .9rem;border:1px solid #dde2ec;border-radius:7px;
  font-size:.93rem;outline:none;font-family:inherit;color:#1a2535;transition:border-color .15s,box-shadow .15s;
}
input:focus{border-color:#1a6fe8;box-shadow:0 0 0 3px #e8f1fd}
.alert{background:#fef2f2;border:1px solid #fca5a5;border-left:4px solid #ef4444;border-radius:7px;padding:.7rem .95rem;margin-bottom:1.1rem;font-size:.875rem;color:#c53030}
.btn{
  width:100%;padding:.7rem;background:#1a6fe8;color:#fff;border:none;border-radius:8px;
  font-size:.95rem;font-weight:600;cursor:pointer;font-family:inherit;margin-top:.25rem;
  transition:background .15s;
}
.btn:hover{background:#1257bf}
footer{margin-top:1.5rem;font-size:.75rem;color:#a0aab7}
</style>
</head>
<body>

<div class="card">
  <div class="card-top">
    <div class="logo">⚡</div>
    <h1>Lagerstyring</h1>
    <p class="subtitle">Arbeidsbil</p>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="username">Brukernavn</label>
        <input id="username" type="text" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="form-group">
        <label for="password">Passord</label>
        <input id="password" type="password" name="password"
               autocomplete="current-password" required>
      </div>
      <button class="btn" type="submit">Logg inn</button>
    </form>
  </div>
</div>

<footer>Lagerstyring for arbeidsbil &mdash; <?= date('Y') ?></footer>
</body>
</html>
