<?php
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Recenze klientu</h1>
  <a href="/admin/reviews/create" class="btn btn-primary">+ Nova recenze</a>
</div>

<?php if (empty($items)): ?>
<div class="card" style="text-align:center;padding:40px 0;color:var(--c-muted)">
  Zatím žádné recenze. Kliknete na <strong>+ Nova recenze</strong>.
</div>
<?php else: ?>
<div class="card" style="padding:0">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Autor</th>
        <th style="width:90px">Hvezdy</th>
        <th>Text</th>
        <th style="width:80px">Poradi</th>
        <th style="width:80px">Aktivni</th>
        <th style="width:120px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= (int)$item['id'] ?></td>
        <td>
          <strong><?= $h($item['author_name']) ?></strong>
          <?php if (!empty($item['context_label'])): ?>
          <div style="font-size:12px;color:var(--c-muted);margin-top:3px"><?= $h($item['context_label']) ?></div>
          <?php endif; ?>
        </td>
        <td style="text-align:center;color:#c4963c"><?= $h(str_repeat('★', max(1, min(5, (int)$item['rating'])))) ?></td>
        <td>
          <div style="max-width:520px;color:var(--c-muted)">
            <?= $h(mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags((string)$item['review_text']))), 0, 120, '...')) ?>
          </div>
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
          <a href="/admin/reviews/edit?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <form method="POST" action="/admin/reviews/delete" style="display:inline" data-confirm="Smazat tuto recenzi?">
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
  Aktivni recenze se vypisuji na homepage a na strance <code>/hodnoceni</code> podle poradi.
</p>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
