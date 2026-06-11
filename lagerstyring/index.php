<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/units.php';

$is_admin = !empty($_SESSION['is_admin']);

// Hvilket lager vises? 'felles' (standard), 'mitt', eller for admin 'user_<id>'
$view      = $_GET['lager'] ?? 'felles';
$view_user = null;

if ($view === 'mitt') {
    $owner_filter = (int)$_SESSION['user_id'];
} elseif ($is_admin && preg_match('/^user_(\d+)$/', $view, $m)) {
    $vu = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $vu->execute([(int)$m[1]]);
    $view_user = $vu->fetch();
    if ($view_user) {
        $owner_filter = (int)$view_user['id'];
    } else {
        $view = 'felles';
        $owner_filter = null;
    }
} else {
    $view = 'felles';
    $owner_filter = null;
}

// Admin kan bytte mellom alle lagre i nedtrekksmenyen
$lager_users = [];
if ($is_admin) {
    $lu = $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username ASC");
    $lu->execute([(int)$_SESSION['user_id']]);
    $lager_users = $lu->fetchAll();
}

$page_title = $view === 'mitt' ? 'Mitt lager'
            : ($view_user ? 'Lager: ' . $view_user['username'] : 'Felles lager');
$active_nav = $view === 'mitt' ? 'mitt' : ($view_user ? '' : 'lager');
require_once __DIR__ . '/includes/header.php';

// Hent varene i valgt lager
if ($owner_filter !== null) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE owner_id = ? ORDER BY name ASC");
    $stmt->execute([$owner_filter]);
} else {
    $stmt = $pdo->query("SELECT * FROM items WHERE owner_id IS NULL ORDER BY name ASC");
}
$items = $stmt->fetchAll();

// Finn varer med lav beholdning
$low = array_filter($items, fn($i) => $i['quantity'] <= $i['min_quantity'] && $i['min_quantity'] > 0);
?>

<?php if ($low): ?>
<div class="low-stock-banner">
  <strong>⚠️ Lav beholdning (<?= count($low) ?> vare<?= count($low) > 1 ? 'r' : '' ?>):</strong>
  <ul>
    <?php foreach ($low as $l): ?>
    <li>
      <?= e($l['name']) ?>
      <?= $l['elnummer'] ? '&nbsp;<span style="color:#92400e;font-size:.8rem">[' . e($l['elnummer']) . ']</span>' : '' ?>
      — <strong><?= (int)$l['quantity'] ?></strong> <?= unit_label($l['unit'] ?? 'stk') ?>
      (min. <?= (int)$l['min_quantity'] ?>)
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="toolbar-card">
  <?php if ($is_admin): ?>
  <select onchange="location.href='index.php?lager='+encodeURIComponent(this.value)"
          style="max-width:240px;font-weight:600" title="Velg lager">
    <option value="felles" <?= $view === 'felles' ? 'selected' : '' ?>>🗂 Felles lager</option>
    <option value="mitt" <?= $view === 'mitt' ? 'selected' : '' ?>>🚐 Mitt lager</option>
    <?php foreach ($lager_users as $lu): ?>
    <option value="user_<?= (int)$lu['id'] ?>" <?= $view === 'user_' . (int)$lu['id'] ? 'selected' : '' ?>>👤 <?= e($lu['username']) ?> sitt lager</option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <span style="font-weight:700;font-size:1.05rem;white-space:nowrap"><?= $view === 'mitt' ? '🚐 Mitt lager' : '🗂 Felles lager' ?></span>
  <?php endif; ?>
  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="search" placeholder="Søk etter varenavn eller elnummer…" autocomplete="off">
  </div>
  <a href="add_item.php?lager=<?= e($view) ?>" class="btn btn-primary">＋ Legg til vare</a>
</div>

<?php if (empty($items)): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:3rem 1rem;color:#7a8699">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📦</div>
    <p style="font-size:1rem;font-weight:600;margin-bottom:.4rem">Ingen varer registrert</p>
    <p style="font-size:.875rem;margin-bottom:1.2rem"><?= $view === 'mitt' ? 'Du har ingen varer i ditt personlige lager ennå.' : ($view_user ? 'Dette lageret er tomt.' : 'Kom i gang ved å legge til den første varen.') ?></p>
    <a href="add_item.php?lager=<?= e($view) ?>" class="btn btn-primary">＋ Legg til vare</a>
  </div>
</div>
<?php else: ?>
<div class="item-list" id="items-list">
  <?php foreach ($items as $item):
    $isLow  = $item['quantity'] <= $item['min_quantity'] && $item['min_quantity'] > 0;
    $isZero = $item['quantity'] == 0;
  ?>
  <div class="item-card <?= $isLow ? 'low-stock' : '' ?>"
       data-name="<?= e(strtolower($item['name'])) ?>"
       data-el="<?= e(strtolower($item['elnummer'] ?? '')) ?>"
       data-min="<?= (int)$item['min_quantity'] ?>">

    <?php if ($item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
      <img src="<?= e(UPLOAD_URL . rawurlencode($item['image_path'])) ?>" alt="" class="item-card__img">
    <?php else: ?>
      <div class="item-card__placeholder">📦</div>
    <?php endif; ?>

    <div class="item-card__info">
      <div class="item-card__meta">
        <?php if ($item['elnummer']): ?>
          <span class="elnr"><?= e($item['elnummer']) ?></span>
          <span>•</span>
        <?php endif; ?>
        <?php if ($isZero): ?>
          <span class="badge badge-zero">Tomt</span>
        <?php elseif ($isLow): ?>
          <span class="badge badge-low">Lav beholdning</span>
        <?php else: ?>
          <span class="badge badge-ok">OK</span>
        <?php endif; ?>
      </div>
      <div class="item-card__title"><?= e($item['name']) ?></div>
      <div class="item-card__sub">Min: <?= (int)$item['min_quantity'] ?> <?= unit_label($item['unit'] ?? 'stk') ?></div>
    </div>

    <div class="item-card__side">
      <div class="item-card__qty">
        <span id="qty-<?= (int)$item['id'] ?>"><?= (int)$item['quantity'] ?></span> <?= unit_label($item['unit'] ?? 'stk') ?>
        <small>på lager</small>
      </div>
      <div class="item-card__actions">
        <div class="qty-ctrl">
          <button class="qty-btn minus" onclick="updateQty(<?= (int)$item['id'] ?>, -1, this)" title="Reduser">−</button>
          <button class="qty-btn plus" onclick="updateQty(<?= (int)$item['id'] ?>, 1, this)" title="Øk">＋</button>
        </div>
        <a href="edit_item.php?id=<?= (int)$item['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Rediger">✏️</a>
        <form method="POST" action="delete_item.php" onsubmit="return confirm('Slett «<?= e(addslashes($item['name'])) ?>»?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <input type="hidden" name="lager" value="<?= e($view) ?>">
          <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Slett">🗑</button>
        </form>
      </div>
    </div>

  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Klientside søk
const searchInput = document.getElementById('search');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#items-list .item-card').forEach(card => {
      const name = card.dataset.name || '';
      const el   = card.dataset.el   || '';
      card.style.display = (!q || name.includes(q) || el.includes(q)) ? '' : 'none';
    });
  });
}

// AJAX-oppdatering av antall
async function updateQty(id, delta, btn) {
  btn.disabled = true;
  try {
    const res = await fetch('ajax_quantity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&delta=${delta}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    if (data.ok) {
      const span = document.getElementById('qty-' + id);
      if (span) span.textContent = data.quantity;
      // Oppdater status-badge og lav beholdning-markering
      const card  = btn.closest('.item-card');
      const badge = card.querySelector('.badge');
      const min   = parseInt(card.dataset.min) || 0;
      const qty   = data.quantity;
      if (qty <= 0) {
        badge.className = 'badge badge-zero'; badge.textContent = 'Tomt';
        card.classList.add('low-stock');
      } else if (qty <= min && min > 0) {
        badge.className = 'badge badge-low'; badge.textContent = 'Lav beholdning';
        card.classList.add('low-stock');
      } else {
        badge.className = 'badge badge-ok'; badge.textContent = 'OK';
        card.classList.remove('low-stock');
      }
    }
  } catch (e) { alert('Kunne ikke oppdatere antall. Prøv igjen.'); }
  btn.disabled = false;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
