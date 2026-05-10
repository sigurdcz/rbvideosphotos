<?php
$isEdit    = $item !== null;
$pageTitle = $isEdit ? 'Upravit otázku' : 'Nová otázka';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/faq" class="btn btn-secondary">&larr; Zpět</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/faq/update' : '/admin/faq/store' ?>">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
  <?php endif; ?>

  <div class="card">
    <div class="form-group">
      <label for="faq-question">Otázka *</label>
      <input type="text" id="faq-question" name="question"
             value="<?= $h($item['question'] ?? '') ?>"
             placeholder="Např.: Jak probíhá spolupráce?" required>
    </div>

    <div class="form-group">
      <label for="faq-answer">Odpověď *</label>
      <textarea id="faq-answer" name="answer" style="min-height:120px"
                placeholder="Napište odpověď na otázku…"><?= $h($item['answer'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="faq-sort">Pořadí</label>
        <input type="number" id="faq-sort" name="sort_order"
               value="<?= (int)($item['sort_order'] ?? 0) ?>" min="0" style="max-width:120px">
        <div class="form-hint">Nižší číslo = výše v seznamu</div>
      </div>
      <div class="form-group" style="justify-content:flex-end;align-items:center;display:flex;gap:10px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
          <input type="checkbox" name="is_active" value="1"
                 <?= ($item['is_active'] ?? 1) ? 'checked' : '' ?>>
          Zobrazit na webu
        </label>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:8px;margin-top:16px">
    <button type="submit" class="btn btn-primary">Uložit otázku</button>
    <a href="/admin/faq" class="btn btn-secondary">Zrušit</a>
  </div>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
