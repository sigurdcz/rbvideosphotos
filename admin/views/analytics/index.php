<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Návštěvnost';
$deviceLabels = ['desktop' => 'Počítač', 'mobile' => 'Mobil', 'tablet' => 'Tablet', 'unknown' => 'Neznámé'];
$renderTop = static function (string $title, array $rows, ?array $labels = null) use ($h): void {
    $max = 0;
    foreach ($rows as $row) { $max = max($max, (int) $row['visits']); }
    echo '<div class="card" style="padding:16px"><div class="card-title">' . $h($title) . '</div>';
    if ($rows === []) {
        echo '<p class="help-text">Za zvolené období bez dat.</p></div>';
        return;
    }
    echo '<table class="data-table" style="width:100%"><tbody>';
    foreach ($rows as $row) {
        $label = $labels[$row['label']] ?? $row['label'];
        $width = $max > 0 ? round((int) $row['visits'] / $max * 100) : 0;
        echo '<tr><td style="overflow-wrap:anywhere">' . $h($label)
            . '<div style="height:4px;background:#4f7cff;border-radius:2px;margin-top:4px;width:' . $h($width) . '%"></div></td>'
            . '<td style="text-align:right;white-space:nowrap">' . $h(number_format((int) $row['visits'], 0, ',', ' ')) . '</td></tr>';
    }
    echo '</tbody></table></div>';
};
ob_start(); ?>
<div class="page-header"><h1>Návštěvnost (vlastní měření)</h1></div>
<p>Záloha k Google Analytics, nezávislá na GA4/GTM. Bez souhlasu s analytickými cookies se ukládá jen serverový záznam s denně rotujícím anonymním otiskem (IP adresa se neukládá); identifikátor návštěvníka v cookie <code>cms_vid</code> vzniká až po souhlasu. Data se uchovávají 14 měsíců.</p>
<?php if (!$enabled && $hasSite): ?><div class="alert alert-warning">Měření je vypnuté v <a href="/admin/settings">Nastavení</a> (karta Cookies, consent a analytics). Zobrazená data jsou historická.</div><?php endif; ?>
<?php if (!empty($loadError)): ?><div class="alert alert-danger"><?= $h($loadError) ?></div><?php endif; ?>
<?php if (!$hasSite): ?><div class="card"><p>Nejprve <a href="/admin/settings">nastavte web</a>.</p></div><?php endif; ?>

<form method="get" action="/admin/analytics" style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0;align-items:center">
  <label>Období
    <select name="days">
      <?php foreach ([7 => 'Posledních 7 dní', 30 => 'Posledních 30 dní', 90 => 'Posledních 90 dní', 365 => 'Posledních 365 dní'] as $value => $label): ?>
        <option value="<?= $h($value) ?>" <?= $days === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn-primary">Zobrazit</button>
</form>

<?php if (is_array($summary)): ?>
<p class="help-text">Období <?= $h($summary['from']) ?> až <?= $h($summary['to']) ?>.</p>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-num"><?= $h(number_format($summary['visits'], 0, ',', ' ')) ?></div><div class="stat-lbl">Návštěvy (zobrazení stránek)</div></div>
  <div class="stat-card"><div class="stat-num"><?= $h(number_format($summary['uniques'], 0, ',', ' ')) ?></div><div class="stat-lbl">Unikátní návštěvníci (součet denních)</div></div>
  <div class="stat-card"><div class="stat-num"><?= $h(number_format($summary['consented_uniques'], 0, ',', ' ')) ?></div><div class="stat-lbl">Unikátní se souhlasem (cookie)</div></div>
  <div class="stat-card"><div class="stat-num"><?= $h(number_format((float) $summary['consent_share'], 1, ',', ' ')) ?> %</div><div class="stat-lbl">Podíl návštěv se souhlasem</div></div>
  <div class="stat-card"><div class="stat-num"><?= $h(number_format($summary['bots'], 0, ',', ' ')) ?></div><div class="stat-lbl">Roboti (nezapočteni)</div></div>
</div>
<p class="help-text">Anonymní otisk se mění každý den, proto se stejný návštěvník bez souhlasu počítá v každém dni zvlášť. Unikátní návštěvníci se souhlasem se počítají přes celé období.</p>

<?php $maxDay = 0; foreach ($summary['daily'] as $row) { $maxDay = max($maxDay, (int) $row['visits']); } ?>
<div class="card" style="padding:16px">
  <div class="card-title">Denní návštěvy</div>
  <div style="overflow-x:auto">
    <table class="data-table" style="width:100%">
      <thead><tr><th>Den</th><th style="width:60%"></th><th style="text-align:right">Návštěvy</th><th style="text-align:right">Unikátní</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($summary['daily']) as $row): ?>
        <tr>
          <td style="white-space:nowrap"><?= $h($row['day']) ?></td>
          <td><div style="height:8px;background:#4f7cff;border-radius:2px;width:<?= $h($maxDay > 0 ? round($row['visits'] / $maxDay * 100) : 0) ?>%"></div></td>
          <td style="text-align:right"><?= $h($row['visits']) ?></td>
          <td style="text-align:right"><?= $h($row['uniques']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:16px">
  <?php $renderTop('Nejnavštěvovanější stránky', $summary['top_paths']); ?>
  <?php $renderTop('Odkud přišli (referrer)', $summary['top_referrers']); ?>
  <?php $renderTop('Zdroje kampaní (utm_source)', $summary['top_sources']); ?>
  <?php $renderTop('Zařízení', $summary['devices'], $deviceLabels); ?>
</div>
<?php endif; ?>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
