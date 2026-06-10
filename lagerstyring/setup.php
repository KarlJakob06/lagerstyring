<?php
/**
 * OPPSETTSVEIVISER — kjøres KUN ÉN GANG.
 * Slett eller flytt denne filen etter at admin-brukeren er opprettet!
 */
require_once __DIR__ . '/config.php';

$error   = '';
$success = false;

// Opprett tabeller
function create_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username`      VARCHAR(50)  NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `is_admin`      TINYINT(1)   NOT NULL DEFAULT 0,
          `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `items` (
          `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`         VARCHAR(255) NOT NULL,
          `elnummer`     VARCHAR(50)  DEFAULT NULL,
          `quantity`     INT          NOT NULL DEFAULT 0,
          `min_quantity` INT          NOT NULL DEFAULT 0,
          `image_path`   VARCHAR(255) DEFAULT NULL,
          `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass1    = $_POST['password']  ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if (!$username || !$pass1)              $error = 'Brukernavn og passord er påkrevd.';
    elseif (strlen($username) < 3)          $error = 'Brukernavn må ha minst 3 tegn.';
    elseif (strlen($pass1) < 8)             $error = 'Passord må ha minst 8 tegn.';
    elseif ($pass1 !== $pass2)              $error = 'Passordene stemmer ikke overens.';
    else {
        try {
            create_tables($pdo);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Brukernavnet er allerede i bruk.';
            } else {
                $hash = password_hash($pass1, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 1)");
                $ins->execute([$username, $hash]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = 'Databasefeil: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Oppsett — Lagerstyring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#eef1f6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.box{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:480px;width:100%;overflow:hidden}
.box-top{background:#0d1f3c;padding:2rem;text-align:center;color:#fff}
.box-top .icon{font-size:2.5rem;margin-bottom:.5rem}
.box-top h1{font-size:1.3rem;font-weight:700}
.box-top p{font-size:.875rem;opacity:.75;margin-top:.35rem}
.box-body{padding:2rem}
.form-group{margin-bottom:1.2rem}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:#1a2535}
input{width:100%;padding:.6rem .85rem;border:1px solid #dde2ec;border-radius:7px;font-size:.9rem;outline:none;font-family:inherit}
input:focus{border-color:#7aa3d4;box-shadow:0 0 0 3px rgba(13,31,60,.1)}
.btn{width:100%;padding:.7rem;border-radius:7px;border:none;background:#0d1f3c;color:#fff;font-size:.95rem;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{background:#162f58}
.alert{padding:.8rem 1rem;border-radius:7px;margin-bottom:1.2rem;font-size:.875rem;border-left:4px solid}
.alert-error{background:#fef2f2;border-color:#f87171;color:#c53030}
.alert-success{background:#f0fdf4;border-color:#86efac;color:#15803d}
.note{background:#fffbeb;border:1px solid #fbbf24;border-radius:7px;padding:.9rem;font-size:.8rem;color:#92400e;margin-top:1.2rem;line-height:1.6}
</style>
</head>
<body>
<div class="box">
  <div class="box-top">
    <div class="icon">⚡</div>
    <h1>Oppsett av Lagerstyring</h1>
    <p>Opprett en administrator-bruker for å komme i gang.</p>
  </div>
  <div class="box-body">

  <?php if ($success): ?>
    <div class="alert alert-success">
      ✅ Admin-bruker opprettet! Du kan nå <a href="login.php">logge inn</a>.
    </div>
    <div class="note">
      ⚠️ <strong>Viktig:</strong> Slett eller gi nytt navn til <code>setup.php</code> nå.
      Denne filen gir tilgang til å opprette nye administratorer uten innlogging.
    </div>
  <?php else: ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Brukernavn</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label>Passord (min. 8 tegn)</label>
        <input type="password" name="password" autocomplete="new-password" required>
      </div>
      <div class="form-group">
        <label>Gjenta passord</label>
        <input type="password" name="password2" autocomplete="new-password" required>
      </div>
      <button class="btn" type="submit">Opprett administrator og sett opp database</button>
    </form>
    <div class="note">
      Denne siden oppretter databasetabellene og en administrator-bruker.
      <strong>Slett setup.php etter bruk!</strong>
    </div>
  <?php endif; ?>
  </div>
</div>
</body>
</html>
