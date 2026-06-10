<?php
// Forventede variabler fra inkluderende side:
// $page_title  — string: sidetittel
// $active_nav  — string: 'lager' | 'legg_til' | 'brukere' | 'passord'
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
$flash = get_flash();
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
<style>
/* ── Reset & Variables ─────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:       #0d1f3c;
  --navy-hover: #162f58;
  --amber:      #f5a623;
  --amber-dark: #d4880e;
  --bg:         #eef1f6;
  --surface:    #ffffff;
  --border:     #dde2ec;
  --text:       #1a2535;
  --muted:      #68768a;
  --success:    #16803d;
  --success-bg: #f0fdf4;
  --warn:       #b45309;
  --warn-bg:    #fffbeb;
  --warn-border:#fbbf24;
  --danger:     #c53030;
  --danger-bg:  #fef2f2;
  --danger-border:#f87171;
  --radius:     8px;
  --shadow:     0 1px 4px rgba(0,0,0,.10);
  --shadow-md:  0 4px 14px rgba(0,0,0,.12);
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:15px;line-height:1.55}
a{color:var(--navy);text-decoration:none}
a:hover{text-decoration:underline}
img{max-width:100%;height:auto}

/* ── Topnav ────────────────────────────────────────────── */
.topnav{
  background:var(--navy);
  padding:0 1.25rem;
  display:flex;align-items:center;gap:1rem;
  position:sticky;top:0;z-index:100;
  box-shadow:0 2px 8px rgba(0,0,0,.25);
}
.topnav__brand{
  color:#fff;font-weight:700;font-size:1.05rem;
  padding:.75rem 0;white-space:nowrap;flex-shrink:0;
  letter-spacing:-.2px;
}
.topnav__brand span{color:var(--amber)}
.topnav__links{
  display:flex;align-items:center;gap:.15rem;flex:1;
}
.topnav__links a{
  color:rgba(255,255,255,.72);
  padding:.55rem .75rem;
  border-radius:6px;
  font-size:.875rem;font-weight:500;
  transition:background .15s,color .15s;
  white-space:nowrap;
}
.topnav__links a:hover{background:var(--navy-hover);color:#fff;text-decoration:none}
.topnav__links a.active{color:#fff;background:rgba(245,166,35,.18);border-bottom:2px solid var(--amber)}
.topnav__right{margin-left:auto;display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.topnav__user{color:rgba(255,255,255,.6);font-size:.8rem}
.topnav__logout{
  color:rgba(255,255,255,.8);
  background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.2);
  border-radius:5px;
  padding:.35rem .7rem;
  font-size:.8rem;font-weight:500;
  transition:background .15s;
  cursor:pointer;
}
.topnav__logout:hover{background:rgba(255,255,255,.2);text-decoration:none;color:#fff}

/* Hamburger */
.nav-toggle{display:none;background:none;border:none;cursor:pointer;padding:.5rem;margin-left:auto}
.nav-toggle span{display:block;width:22px;height:2px;background:#fff;margin:4px 0;transition:.2s}

/* ── Layout ────────────────────────────────────────────── */
.main{max-width:1100px;margin:0 auto;padding:1.5rem 1.25rem}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.page-title{font-size:1.3rem;font-weight:700;letter-spacing:-.3px}

/* ── Flash / Alert ─────────────────────────────────────── */
.alert{padding:.85rem 1.1rem;border-radius:var(--radius);border-left:4px solid;margin-bottom:1.25rem;font-size:.9rem}
.alert-success{background:var(--success-bg);border-color:var(--success);color:var(--success)}
.alert-error{background:var(--danger-bg);border-color:var(--danger-border);color:var(--danger)}
.alert-warn{background:var(--warn-bg);border-color:var(--warn-border);color:var(--warn)}

/* ── Buttons ───────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.5rem 1rem;border-radius:var(--radius);
  font-size:.875rem;font-weight:500;cursor:pointer;
  border:1px solid transparent;transition:background .15s,border-color .15s,opacity .15s;
  line-height:1;
}
.btn:hover{text-decoration:none;opacity:.9}
.btn-primary{background:var(--navy);color:#fff;border-color:var(--navy)}
.btn-primary:hover{background:var(--navy-hover);opacity:1}
.btn-amber{background:var(--amber);color:#fff;border-color:var(--amber)}
.btn-amber:hover{background:var(--amber-dark);opacity:1}
.btn-outline{background:transparent;color:var(--navy);border-color:var(--border)}
.btn-outline:hover{background:var(--bg);opacity:1}
.btn-danger{background:var(--danger-bg);color:var(--danger);border-color:var(--danger-border)}
.btn-danger:hover{background:#fee2e2;opacity:1}
.btn-sm{padding:.35rem .7rem;font-size:.8rem}
.btn-icon{width:34px;height:34px;padding:0;justify-content:center}

/* ── Cards ─────────────────────────────────────────────── */
.card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden}
.card-header{padding:.9rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.card-body{padding:1.2rem}

/* ── Table ─────────────────────────────────────────────── */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table.tbl{width:100%;border-collapse:collapse;font-size:.9rem}
.tbl th{background:var(--bg);padding:.7rem 1rem;text-align:left;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);white-space:nowrap;border-bottom:2px solid var(--border)}
.tbl td{padding:.7rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:#f8fafd}
.tbl tr.low-stock td{background:var(--warn-bg)}
.tbl tr.low-stock td:first-child{border-left:3px solid var(--warn-border)}

/* ── Badges ────────────────────────────────────────────── */
.badge{display:inline-block;padding:.25rem .6rem;border-radius:99px;font-size:.75rem;font-weight:600;line-height:1}
.badge-ok{background:#dcfce7;color:var(--success)}
.badge-low{background:var(--warn-bg);color:var(--warn);border:1px solid var(--warn-border)}
.badge-zero{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)}

/* ── Qty Controls ──────────────────────────────────────── */
.qty-ctrl{display:flex;align-items:center;gap:.35rem}
.qty-btn{
  width:28px;height:28px;border-radius:6px;border:1px solid var(--border);
  background:var(--surface);color:var(--text);font-size:1rem;font-weight:600;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:background .12s,border-color .12s;line-height:1;
}
.qty-btn:hover{background:var(--bg);border-color:#b0baca}
.qty-btn.minus:hover{background:#fee2e2;border-color:#fca5a5;color:var(--danger)}
.qty-btn.plus:hover{background:#dcfce7;border-color:#86efac;color:var(--success)}
.qty-num{min-width:2.5rem;text-align:center;font-weight:600;font-size:.9rem}

/* ── Forms ─────────────────────────────────────────────── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.1rem}
.form-group{display:flex;flex-direction:column;gap:.4rem}
.form-group.full{grid-column:1/-1}
label{font-size:.85rem;font-weight:600;color:var(--text)}
.hint{font-size:.78rem;color:var(--muted);margin-top:.15rem}
input[type=text],input[type=number],input[type=password],input[type=file],select,textarea{
  width:100%;padding:.55rem .8rem;
  border:1px solid var(--border);border-radius:6px;
  font-size:.9rem;font-family:inherit;color:var(--text);
  background:var(--surface);
  transition:border-color .15s,box-shadow .15s;
  outline:none;
}
input:focus,select:focus,textarea:focus{
  border-color:#7aa3d4;
  box-shadow:0 0 0 3px rgba(13,31,60,.12);
}
input[type=file]{padding:.45rem .7rem;cursor:pointer}
.form-actions{display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap}

/* ── Item thumbnail ────────────────────────────────────── */
.item-thumb{width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border)}
.item-thumb-placeholder{
  width:44px;height:44px;border-radius:6px;border:1px solid var(--border);
  background:var(--bg);display:flex;align-items:center;justify-content:center;
  color:var(--muted);font-size:1.2rem;
}

/* ── Search ────────────────────────────────────────────── */
.search-wrap{position:relative;max-width:340px}
.search-wrap input{padding-left:2.3rem}
.search-icon{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}

/* ── Low-stock warning banner ──────────────────────────── */
.low-stock-banner{
  background:var(--warn-bg);border:1px solid var(--warn-border);
  border-radius:var(--radius);padding:1rem 1.2rem;
  margin-bottom:1.25rem;
}
.low-stock-banner strong{color:var(--warn)}
.low-stock-banner ul{margin:.5rem 0 0 1.2rem;font-size:.875rem}

/* ── Responsive ────────────────────────────────────────── */
@media(max-width:700px){
  .topnav__links{
    display:none;flex-direction:column;align-items:flex-start;
    position:fixed;top:0;left:0;width:100%;height:100vh;
    background:var(--navy);z-index:200;padding:4rem 1.5rem 2rem;gap:.25rem;
  }
  .topnav__links.open{display:flex}
  .topnav__links a{font-size:1rem;padding:.75rem 1rem;width:100%}
  .topnav__right{display:none}
  .nav-toggle{display:block}
  .main{padding:1rem .9rem}
  .form-grid{grid-template-columns:1fr}
  .form-group.full{grid-column:1}
  .page-header{flex-direction:column;align-items:flex-start}
  .tbl th:nth-child(n+5),.tbl td:nth-child(n+5){display:none}
  .btn-sm span{display:none}
}
</style>
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
