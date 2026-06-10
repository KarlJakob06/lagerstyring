<?php
// Forventede variabler fra inkluderende side:
// $page_title  — string: sidetittel
// $active_nav  — string: 'lager' | 'legg_til' | 'brukere' | 'passord'
require_once __DIR__ . '/bootstrap.php';
$flash = get_flash();
$css_version = @filemtime(__DIR__ . '/../assets/style.css') ?: 1;
?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? 'Lagerstyring') ?> — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= $css_version ?>">
</head>
<body>

<nav class="topnav">
  <div class="topnav__brand">⚡ <span>Lager</span>styring</div>

  <div class="topnav__links" id="nav-links">
    <a href="index.php" class="<?= ($active_nav??'') === 'lager' ? 'active' : '' ?>">🗂 Lager</a>
    <a href="add_item.php" class="<?= ($active_nav??'') === 'legg_til' ? 'active' : '' ?>">＋ Legg til vare</a>
    <?php if (!empty($_SESSION['is_admin'])): ?>
    <a href="users.php" class="<?= ($active_nav??'') === 'brukere' ? 'active' : '' ?>">👥 Brukere</a>
    <?php endif; ?>
    <a href="change_password.php" class="<?= ($active_nav??'') === 'passord' ? 'active' : '' ?>">🔑 Bytt passord</a>
  </div>

  <div class="topnav__right">
    <span class="topnav__user">👤 <?= e($_SESSION['username'] ?? '') ?></span>
    <a href="logout.php" class="topnav__logout">Logg ut</a>
  </div>

  <button class="nav-toggle" id="nav-toggle" aria-label="Meny">
    <span></span><span></span><span></span>
  </button>
</nav>

<main class="main">

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>">
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<script>
const toggle = document.getElementById('nav-toggle');
const links  = document.getElementById('nav-links');
if(toggle) toggle.addEventListener('click',()=>links.classList.toggle('open'));
document.querySelectorAll('#nav-links a').forEach(a=>a.addEventListener('click',()=>links.classList.remove('open')));
</script>
