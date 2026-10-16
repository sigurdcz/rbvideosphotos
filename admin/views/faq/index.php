<?php
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>FAQ - Časté otázky</h1>
  <a href="/admin/faq/create" class="btn btn-primary">+ Přidat otázku</a>
</div>

<?php if (empty($items)): ?>
<div class="card" style="text-align:center;padding:40px 0;color:var(--c-muted)">
  Zatím žádné otázky. Klikněte na <strong>+ Přidat otázku</strong>.
</div>
<?php else: ?>
<div class="card" style="padding:0">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Otázka</th>
        <th style="width:80px">Pořadí</th>
        <th style="width:80px">Aktivní</th>
        <th style="width:120px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= (int)$item['id'] ?></td>
        <td>
          <strong><?= $h($item['question']) ?></strong>
          <?php if ($item['answer']): ?>
          <div style="font-size:12px;color:var(--c-muted);margin-top:3px;max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= $h(mb_strimwidth(strip_tags($item['answer']), 0, 100, '…')) ?>
          </div>
          <?php endif; ?>
        </td>
        <td style="color:var(--c-muted);text-align:center"><?= (int)$item['sort_order'] ?></td>
        <td style="text-align:center">
          <?php if ($item['is_active']): ?>
            <span style="color:#16a34a;font-weight:700">✓</span>
          <?php else: ?>
            <span style="color:var(--c-muted)">-</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="/admin/faq/edit?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <form method="POST" action="/admin/faq/delete" style="display:inline" data-confirm="Smazat tuto otázku?">
            <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Smazat</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--c-muted);margin-top:8px">
  První 3 aktivní otázky se automaticky zobrazí na homepage. Seřaďte pomocí pole Pořadí.
</p>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
