<?php
$isEdit = $item !== null;
$pageTitle = $isEdit ? 'Upravit recenzi' : 'Nova recenze';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/reviews" class="btn btn-secondary">&larr; Zpet</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/reviews/update' : '/admin/reviews/store' ?>">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
  <?php endif; ?>

  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label for="review-author">Jmeno autora *</label>
        <input type="text" id="review-author" name="author_name"
               value="<?= $h($item['author_name'] ?? '') ?>"
               placeholder="Napr. Jan Novak" required>
      </div>
      <div class="form-group">
        <label for="review-context">Typ / zdroj</label>
        <input type="text" id="review-context" name="context_label"
               value="<?= $h($item['context_label'] ?? '') ?>"
               placeholder="Napr. Svatba M+H nebo Firemni akce">
      </div>
    </div>

    <div class="form-group">
      <label for="review-text">Text recenze *</label>
      <textarea id="review-text" name="review_text" style="min-height:140px"
                placeholder="Sem napiste plne zneni recenze..." required><?= $h($item['review_text'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="review-rating">Hodnoceni</label>
        <select id="review-rating" name="rating" style="max-width:140px">
          <?php for ($rating = 5; $rating >= 1; $rating--): ?>
          <option value="<?= $rating ?>" <?= (int)($item['rating'] ?? 5) === $rating ? 'selected' : '' ?>>
            <?= $rating ?> / 5
          </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="review-sort">Poradi</label>
        <input type="number" id="review-sort" name="sort_order"
               value="<?= (int)($item['sort_order'] ?? 0) ?>" min="0" style="max-width:120px">
        <div class="form-hint">Nizsi cislo = vyse v seznamu</div>
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
    <button type="submit" class="btn btn-primary">Ulozit recenzi</button>
    <a href="/admin/reviews" class="btn btn-secondary">Zrusit</a>
  </div>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
