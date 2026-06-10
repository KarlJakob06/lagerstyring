<?php
// Forventede variabler fra inkluderende side:
// $page_title  — string: sidetittel
// $active_nav  — string: 'lager' | 'legg_til' | 'brukere' | 'passord'
require_once __DIR__ . '/bootstrap.php';
$flash = get_flash();
$css_version = @filemtime(__DIR__ . '/../assets/style.css') ?: 1;
$initials = mb_strtoupper(mb_substr($_SESSION['username'] ?? '?', 0, 2));
?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? 'Lagerstyring') ?> — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= $css_version ?>">
</head>
<body>

<div class="topbar">
  <button class="nav-toggle" id="nav-toggle" aria-label="Meny" style="margin-left:0">
    <span></span><span></span><span></span>
  </button>
  <div class="topbar__brand">lager<span>styring</span></div>
</div>

<div class="layout">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__logo">lager<span>styring</span></div>
  <div class="sidebar__version">Arbeidsbil</div>

  <nav class="sidebar__nav">
    <a href="index.php" class="<?= ($active_nav??'') === 'lager' ? 'active' : '' ?>">🗂 Lager</a>
    <a href="add_item.php" class="<?= ($active_nav??'') === 'legg_til' ? 'active' : '' ?>">＋ Legg til vare</a>
    <?php if (!empty($_SESSION['is_admin'])): ?>
    <a href="users.php" class="<?= ($active_nav??'') === 'brukere' ? 'active' : '' ?>">👥 Brukere</a>
    <?php endif; ?>
    <a href="change_password.php" class="<?= ($active_nav??'') === 'passord' ? 'active' : '' ?>">🔑 Bytt passord</a>
  </nav>

  <div class="sidebar__bottom">
    <div class="sidebar__user">
      <div class="avatar"><?= e($initials) ?></div>
      <div class="sidebar__user-name"><?= e($_SESSION['username'] ?? '') ?></div>
    </div>
    <a href="logout.php" class="btn-logout">↪ Logg ut</a>
  </div>
</aside>

<div class="sidebar-backdrop" id="backdrop"></div>

<main class="content">

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>">
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<script>
const sidebar  = document.getElementById('sidebar');
const backdrop = document.getElementById('backdrop');
const toggle   = document.getElementById('nav-toggle');
function closeNav(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); }
if (toggle) toggle.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  backdrop.classList.toggle('show', sidebar.classList.contains('open'));
});
backdrop.addEventListener('click', closeNav);
document.querySelectorAll('.sidebar__nav a').forEach(a => a.addEventListener('click', closeNav));
</script>
