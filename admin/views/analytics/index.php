<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Analytika';
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
<div class="page-header"><h1>Analytika</h1></div>
<p>Jedno místo pro dostupnost webu a vlastní měření návštěvnosti. Health monitoring neukládá údaje návštěvníků a neběží přes analytický beacon.</p>
<?php if (!$enabled && $hasSite): ?><div class="alert alert-warning">Měření je vypnuté v <a href="/admin/settings">Nastavení</a> (karta Cookies, consent a analytics). Zobrazená data jsou historická.</div><?php endif; ?>
<?php if (!empty($loadError)): ?><div class="alert alert-danger"><?= $h($loadError) ?></div><?php endif; ?>
<?php if (!empty($healthLoadError)): ?><div class="alert alert-danger"><?= $h($healthLoadError) ?></div><?php endif; ?>
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

<section class="analytics-section" aria-labelledby="health-heading">
  <div class="analytics-section__head">
    <div><h2 id="health-heading">Zdraví webu</h2><p class="help-text">Kontrola <code>/api/health</code> každých 10 minut. Bez záznamu déle než 20 minut je monitoring označen jako zastaralý.</p></div>
    <a class="btn btn-secondary" href="/api/health" target="_blank" rel="noopener">Otevřít health API</a>
  </div>
  <?php if (is_array($healthSummary)): ?>
    <?php
      $latest = $healthSummary['latest'];
      $currentHealthy = !$healthSummary['stale'] && is_array($latest) && ($latest['status'] ?? '') === 'healthy';
      $stateClass = $currentHealthy ? 'health-state--healthy' : 'health-state--unhealthy';
      $stateLabel = $healthSummary['stale'] ? 'Monitoring neběží' : ($currentHealthy ? 'Web je zdravý' : 'Web má problém');
    ?>
    <div class="health-state <?= $h($stateClass) ?>">
      <span class="health-state__dot" aria-hidden="true"></span>
      <div><strong><?= $h($stateLabel) ?></strong><div><?= $latest ? 'Poslední kontrola: ' . $h($latest['checked_at']) . ' UTC' : 'Zatím neproběhla žádná kontrola.' ?></div></div>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-num"><?= $healthSummary['uptime'] === null ? '—' : $h(number_format((float) $healthSummary['uptime'], 2, ',', ' ')) . ' %' ?></div><div class="stat-lbl">Dostupnost z provedených kontrol</div></div>
      <div class="stat-card"><div class="stat-num"><?= $h(number_format((int) $healthSummary['checks'], 0, ',', ' ')) ?></div><div class="stat-lbl">Provedené kontroly</div></div>
      <div class="stat-card"><div class="stat-num"><?= $h(number_format((int) $healthSummary['unhealthy'], 0, ',', ' ')) ?></div><div class="stat-lbl">Nezdravé kontroly</div></div>
      <div class="stat-card"><div class="stat-num"><?= $h(number_format((int) $healthSummary['average_ms'], 0, ',', ' ')) ?> ms</div><div class="stat-lbl">Průměrná odezva</div></div>
    </div>
    <div class="card analytics-health-chart">
      <div class="card-title">Denní zdraví</div>
      <div class="health-chart" role="img" aria-label="Denní dostupnost webu">
        <?php foreach ($healthSummary['daily'] as $row): ?>
          <?php
            $uptime = $row['uptime'];
            $height = $uptime === null ? 4 : max(4, (int) round((float) $uptime));
            $barClass = $uptime === null ? 'health-chart__bar--empty' : ((int) $row['unhealthy'] > 0 ? 'health-chart__bar--warning' : 'health-chart__bar--healthy');
            $title = $row['day'] . ': ' . ($uptime === null ? 'bez dat' : number_format((float) $uptime, 2, ',', ' ') . ' %, kontrol ' . $row['checks']);
          ?>
          <div class="health-chart__day" title="<?= $h($title) ?>">
            <div class="health-chart__track"><span class="health-chart__bar <?= $h($barClass) ?>" style="height:<?= $h($height) ?>%"></span></div>
            <?php if (count($healthSummary['daily']) <= 31): ?><span><?= $h(substr($row['day'], 5)) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="help-text">Šedý sloupec znamená, že pro daný den nejsou data. Nezapočítává se automaticky jako dostupnost; zastavený cron proto zůstane viditelný.</p>
    </div>
    <div class="card analytics-health-failures">
      <div class="card-title">Poslední zaznamenané problémy</div>
      <?php if ($healthSummary['recent_failures'] === []): ?>
        <p class="help-text">Ve zvoleném období není uložený žádný nezdravý výsledek.</p>
      <?php else: ?>
        <div style="overflow-x:auto"><table class="data-table" style="width:100%">
          <thead><tr><th>Čas UTC</th><th>HTTP</th><th>Databáze</th><th>Kód</th><th>Odezva</th><th>Zdroj</th></tr></thead>
          <tbody><?php foreach ($healthSummary['recent_failures'] as $failure): ?><tr>
            <td><?= $h($failure['checked_at']) ?></td>
            <td><?= $failure['http_status'] === null ? '—' : $h($failure['http_status']) ?></td>
            <td><?= $h($failure['database_status']) ?></td>
            <td><code><?= $h($failure['error_code'] ?? 'unknown') ?></code></td>
            <td><?= $h($failure['response_time_ms']) ?> ms</td>
            <td><?= $h($failure['source']) ?></td>
          </tr><?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card"><p>Historie zdraví zatím není dostupná.</p></div>
  <?php endif; ?>
</section>

<section class="analytics-section" aria-labelledby="visits-heading">
<h2 id="visits-heading">Návštěvnost (vlastní měření)</h2>
<p>Záloha k Google Analytics, nezávislá na GA4/GTM. Bez souhlasu s analytickými cookies se ukládá jen serverový záznam s denně rotujícím anonymním otiskem (IP adresa se neukládá); identifikátor návštěvníka v cookie <code>cms_vid</code> vzniká až po souhlasu. Data se uchovávají 14 měsíců.</p>

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
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
