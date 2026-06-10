<?php
$page_title = 'Lager';
$active_nav = 'lager';
require_once __DIR__ . '/includes/header.php';

// Hent alle varer
$items = $pdo->query("SELECT * FROM items ORDER BY name ASC")->fetchAll();

// Finn varer med lav beholdning
$low = array_filter($items, fn($i) => $i['quantity'] <= $i['min_quantity']);
?>

<?php if ($low): ?>
<div class="low-stock-banner">
  <strong>⚠️ Lav beholdning (<?= count($low) ?> vare<?= count($low) > 1 ? 'r' : '' ?>):</strong>
  <ul>
    <?php foreach ($low as $l): ?>
    <li>
      <?= e($l['name']) ?>
      <?= $l['elnummer'] ? '&nbsp;<span style="color:#92400e;font-size:.8rem">[' . e($l['elnummer']) . ']</span>' : '' ?>
      — <strong><?= (int)$l['quantity'] ?></strong> stk
      (min. <?= (int)$l['min_quantity'] ?>)
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="page-title">🗂 Lageroversikt</span>
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="search" placeholder="Søk vare eller elnummer…" autocomplete="off">
      </div>
      <a href="add_item.php" class="btn btn-amber">＋ Legg til vare</a>
    </div>
  </div>

  <?php if (empty($items)): ?>
  <div class="card-body" style="text-align:center;padding:3rem 1rem;color:#68768a">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📦</div>
    <p style="font-size:1rem;font-weight:600;margin-bottom:.4rem">Ingen varer registrert</p>
    <p style="font-size:.875rem;margin-bottom:1.2rem">Kom i gang ved å legge til den første varen i bilen din.</p>
    <a href="add_item.php" class="btn btn-amber">＋ Legg til vare</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl" id="items-table">
      <thead>
        <tr>
          <th style="width:52px"></th>
          <th>Varenavn</th>
          <th>Elnummer</th>
          <th>Antall</th>
          <th>Min.antall</th>
          <th>Status</th>
          <th style="width:90px">Handling</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $item):
        $isLow  = $item['quantity'] <= $item['min_quantity'] && $item['min_quantity'] > 0;
        $isZero = $item['quantity'] == 0;
      ?>
        <tr class="<?= $isLow ? 'low-stock' : '' ?>" data-name="<?= e(strtolower($item['name'])) ?>" data-el="<?= e(strtolower($item['elnummer'] ?? '')) ?>">
          <td>
            <?php if ($item['image_path'] && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
              <img src="<?= e(UPLOAD_URL . rawurlencode($item['image_path'])) ?>" alt="" class="item-thumb">
            <?php else: ?>
              <div class="item-thumb-placeholder">📦</div>
            <?php endif; ?>
          </td>
          <td><strong><?= e($item['name']) ?></strong></td>
          <td style="font-family:monospace;font-size:.85rem;color:#4a5568"><?= $item['elnummer'] ? e($item['elnummer']) : '<span style="color:#b0b9c8">—</span>' ?></td>
          <td>
            <div class="qty-ctrl">
              <button class="qty-btn minus" onclick="updateQty(<?= (int)$item['id'] ?>, -1, this)" title="Reduser">−</button>
              <span class="qty-num" id="qty-<?= (int)$item['id'] ?>"><?= (int)$item['quantity'] ?></span>
              <button class="qty-btn plus" onclick="updateQty(<?= (int)$item['id'] ?>, 1, this)" title="Øk">+</button>
            </div>
          </td>
          <td><?= (int)$item['min_quantity'] ?></td>
          <td>
            <?php if ($isZero): ?>
              <span class="badge badge-zero">Tomt</span>
            <?php elseif ($isLow): ?>
              <span class="badge badge-low">Lav beholdning</span>
            <?php else: ?>
              <span class="badge badge-ok">OK</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem">
              <a href="edit_item.php?id=<?= (int)$item['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Rediger">✏️</a>
              <form method="POST" action="delete_item.php" onsubmit="return confirm('Slett «<?= e(addslashes($item['name'])) ?>»?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Slett">🗑</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
// Klientside søk
const searchInput = document.getElementById('search');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#items-table tbody tr').forEach(row => {
      const name = row.dataset.name || '';
      const el   = row.dataset.el   || '';
      row.style.display = (!q || name.includes(q) || el.includes(q)) ? '' : 'none';
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
      // Oppdater status-badge og low-stock rad
      const row = btn.closest('tr');
      const badge = row.querySelector('.badge');
      const min = parseInt(row.children[4].textContent) || 0;
      const qty = data.quantity;
      if (qty <= 0) {
        badge.className = 'badge badge-zero'; badge.textContent = 'Tomt';
        row.classList.add('low-stock');
      } else if (qty <= min && min > 0) {
        badge.className = 'badge badge-low'; badge.textContent = 'Lav beholdning';
        row.classList.add('low-stock');
      } else {
        badge.className = 'badge badge-ok'; badge.textContent = 'OK';
        row.classList.remove('low-stock');
      }
    }
  } catch (e) { alert('Kunne ikke oppdatere antall. Prøv igjen.'); }
  btn.disabled = false;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
