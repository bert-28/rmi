<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['staff', 'admin']);
$db = get_db();

// ── Date range filter ──
$range  = $_GET['range'] ?? '30';   // 7 | 30 | 90 | 365
$ranges = ['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days', '365' => 'This Year'];
$days   = (int)$range;

// ── KPI Cards ──
$revenue_total = $db->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
);
$revenue_total->execute([$days]);
$revenue_total = (float)$revenue_total->fetchColumn();

$revenue_prev = $db->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales
     WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
       AND sale_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
);
$revenue_prev->execute([$days * 2, $days]);
$revenue_prev = (float)$revenue_prev->fetchColumn();

$orders_total = $db->prepare("SELECT COUNT(*) FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
$orders_total->execute([$days]);
$orders_total = (int)$orders_total->fetchColumn();

$orders_prev = $db->prepare(
    "SELECT COUNT(*) FROM sales
     WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
       AND sale_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
);
$orders_prev->execute([$days * 2, $days]);
$orders_prev = (int)$orders_prev->fetchColumn();

$avg_order = $orders_total > 0 ? $revenue_total / $orders_total : 0;
$avg_prev  = $orders_prev  > 0 ? $revenue_prev  / $orders_prev  : 0;

$unique_customers = $db->prepare(
    "SELECT COUNT(DISTINCT customer_id) FROM sales
     WHERE customer_id IS NOT NULL AND sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
);
$unique_customers->execute([$days]);
$unique_customers = (int)$unique_customers->fetchColumn();

// ── Daily revenue for chart ──
$daily_stmt = $db->prepare(
    "SELECT DATE(sale_date) as d, SUM(total_amount) as rev, COUNT(*) as cnt
     FROM sales
     WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(sale_date)
     ORDER BY d ASC"
);
$daily_stmt->execute([$days]);
$daily_raw = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in missing dates with 0
$daily_map = [];
foreach ($daily_raw as $r) $daily_map[$r['d']] = $r;
$daily_data = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_data[] = [
        'd'   => $date,
        'rev' => isset($daily_map[$date]) ? (float)$daily_map[$date]['rev'] : 0,
        'cnt' => isset($daily_map[$date]) ? (int)$daily_map[$date]['cnt']   : 0,
    ];
}

// ── Revenue by payment method ──
$payment_stmt = $db->prepare(
    "SELECT payment_method, SUM(total_amount) as rev, COUNT(*) as cnt
     FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY payment_method ORDER BY rev DESC"
);
$payment_stmt->execute([$days]);
$payment_data = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Top selling products ──
$top_products = $db->prepare(
    "SELECT p.product_name, p.category, p.price,
            SUM(si.quantity) as units_sold,
            SUM(si.quantity * si.unit_price) as revenue
     FROM sale_items si
     JOIN products p ON si.product_id = p.product_id
     JOIN sales s ON si.sale_id = s.sale_id
     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY si.product_id
     ORDER BY revenue DESC
     LIMIT 8"
);
$top_products->execute([$days]);
$top_products = $top_products->fetchAll(PDO::FETCH_ASSOC);

// ── Sales by category ──
$cat_stmt = $db->prepare(
    "SELECT p.category, SUM(si.quantity) as units, SUM(si.quantity * si.unit_price) as rev
     FROM sale_items si
     JOIN products p ON si.product_id = p.product_id
     JOIN sales s ON si.sale_id = s.sale_id
     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY p.category
     ORDER BY rev DESC"
);
$cat_stmt->execute([$days]);
$cat_data = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Hourly sales heatmap (avg per hour) ──
$hourly_stmt = $db->prepare(
    "SELECT HOUR(sale_date) as hr, COUNT(*) as cnt, SUM(total_amount) as rev
     FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY HOUR(sale_date)"
);
$hourly_stmt->execute([$days]);
$hourly_raw = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
$hourly_map = [];
foreach ($hourly_raw as $r) $hourly_map[(int)$r['hr']] = $r;

// ── Recent high-value sales ──
$big_sales = $db->prepare(
    "SELECT s.sale_id, s.total_amount, s.payment_method, s.sale_date,
            u.full_name AS customer_name
     FROM sales s
     LEFT JOIN users u ON s.customer_id = u.user_id
     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY s.total_amount DESC LIMIT 5"
);
$big_sales->execute([$days]);
$big_sales = $big_sales->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ──
function pct_change(float $new, float $old): ?float {
    if ($old == 0) return null;
    return (($new - $old) / $old) * 100;
}

$page_title = 'Analytics';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Analytics page ── */
.an-wrap { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

/* Range tabs */
.range-tabs { display: flex; gap: .25rem; }
.range-tab {
  padding: .4rem .9rem;
  font-family: var(--font-cond);
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  border: 1.5px solid var(--border);
  background: transparent;
  color: var(--text-2);
  cursor: pointer;
  text-decoration: none;
  transition: all .15s;
}
.range-tab:hover { border-color: var(--text-0); color: var(--text-0); }
.range-tab.active { background: var(--text-0); color: var(--bg-0); border-color: var(--text-0); }

/* KPI cards */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1.75rem 0; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
  background: var(--bg-1);
  border: 1px solid var(--border);
  border-top: 3px solid var(--text-0);
  padding: 1.25rem 1.4rem;
}
.kpi-label {
  font-family: var(--font-cond);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--text-2);
  margin-bottom: .5rem;
}
.kpi-value {
  font-family: var(--font-display);
  font-size: 2rem;
  color: var(--text-0);
  line-height: 1;
  margin-bottom: .4rem;
}
.kpi-change {
  font-size: .75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: .3rem;
}
.kpi-change.up   { color: var(--green, #22c55e); }
.kpi-change.down { color: var(--red); }
.kpi-change.flat { color: var(--text-2); }

/* Chart sections */
.an-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
.an-grid-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
@media (max-width: 900px) { .an-grid-2, .an-grid-3 { grid-template-columns: 1fr; } }

.an-card {
  background: var(--bg-1);
  border: 1px solid var(--border);
}
.an-card-head {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.an-card-head h3 {
  font-family: var(--font-display);
  font-size: 1rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--text-0);
}
.an-card-body { padding: 1.25rem; }

/* Revenue chart canvas */
.chart-wrap { position: relative; height: 220px; }

/* Category bar chart */
.cat-bar-list { display: flex; flex-direction: column; gap: .75rem; }
.cat-bar-item {}
.cat-bar-meta { display: flex; justify-content: space-between; margin-bottom: .3rem; }
.cat-bar-name { font-size: .8rem; font-weight: 600; color: var(--text-1); }
.cat-bar-val  { font-size: .8rem; color: var(--text-2); }
.cat-bar-track { height: 6px; background: var(--bg-3, rgba(255,255,255,.06)); border-radius: 3px; overflow: hidden; }
.cat-bar-fill  { height: 100%; border-radius: 3px; background: var(--gold, #c99526); transition: width .6s ease; }

/* Payment donut */
.donut-wrap { position: relative; height: 180px; display: flex; align-items: center; justify-content: center; }
.donut-center {
  position: absolute;
  text-align: center;
  pointer-events: none;
}
.donut-center .dc-val { font-family: var(--font-display); font-size: 1.3rem; color: var(--text-0); }
.donut-center .dc-lbl { font-size: .68rem; color: var(--text-2); text-transform: uppercase; letter-spacing: .08em; }

.pay-legend { margin-top: 1rem; display: flex; flex-direction: column; gap: .5rem; }
.pay-leg-row { display: flex; align-items: center; justify-content: space-between; font-size: .8rem; }
.pay-leg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.pay-leg-name { flex: 1; margin-left: .5rem; color: var(--text-1); }
.pay-leg-pct  { color: var(--text-2); }

/* Hourly heatmap */
.heatmap-grid {
  display: grid;
  grid-template-columns: repeat(24, 1fr);
  gap: 3px;
}
.hm-cell {
  aspect-ratio: 1;
  border-radius: 3px;
  cursor: default;
  position: relative;
}
.hm-labels { display: grid; grid-template-columns: repeat(24, 1fr); gap: 3px; margin-top: .4rem; }
.hm-label  { font-size: .55rem; text-align: center; color: var(--text-2); }

/* Top products table */
.top-table { width: 100%; border-collapse: collapse; }
.top-table th {
  font-family: var(--font-cond);
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-2);
  padding: .5rem .75rem;
  text-align: left;
  border-bottom: 1px solid var(--border);
}
.top-table td { padding: .7rem .75rem; font-size: .85rem; border-bottom: 1px solid var(--border); color: var(--text-1); }
.top-table tbody tr:last-child td { border-bottom: none; }
.top-table tbody tr:hover { background: rgba(255,255,255,.02); }
.rank-num { font-family: var(--font-display); font-size: 1rem; color: var(--text-2); width: 28px; display: inline-block; text-align: right; }
.rank-num.gold   { color: #f59e0b; }
.rank-num.silver { color: #94a3b8; }
.rank-num.bronze { color: #b45309; }

/* Big sales table */
.big-sale-list { display: flex; flex-direction: column; }
.big-sale-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 0;
  border-bottom: 1px solid var(--border);
  gap: 1rem;
}
.big-sale-row:last-child { border-bottom: none; }
.bsr-id   { font-family: var(--font-display); font-size: .85rem; color: var(--text-2); width: 52px; flex-shrink: 0; }
.bsr-cust { font-size: .85rem; font-weight: 600; color: var(--text-0); flex: 1; }
.bsr-method { font-size: .75rem; color: var(--text-2); }
.bsr-date { font-size: .75rem; color: var(--text-2); }
.bsr-amount { font-family: var(--font-display); font-size: 1.1rem; color: var(--gold); white-space: nowrap; }

/* Tooltip */
[data-tip] { position: relative; }
[data-tip]:hover::after {
  content: attr(data-tip);
  position: absolute;
  bottom: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--bg-0);
  border: 1px solid var(--border);
  color: var(--text-0);
  font-size: .72rem;
  padding: .3rem .6rem;
  white-space: nowrap;
  z-index: 10;
  pointer-events: none;
}
</style>

<div class="an-wrap">

  <!-- Header -->
  <div class="page-header" style="margin-bottom:1.5rem">
    <div>
      <div class="section-label">Reports</div>
      <h1>Analytics</h1>
      <p class="page-subtitle">Sales performance & insights</p>
    </div>
    <div class="range-tabs">
      <?php foreach ($ranges as $val => $label): ?>
        <a href="?range=<?= $val ?>" class="range-tab <?= $range === $val ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- KPI Cards -->
  <?php
  $rev_chg  = pct_change($revenue_total, $revenue_prev);
  $ord_chg  = pct_change($orders_total,  $orders_prev);
  $avg_chg  = pct_change($avg_order,     $avg_prev);

  function kpi_change(?float $pct): string {
      if ($pct === null) return '<span class="kpi-change flat">— no prior data</span>';
      $dir = $pct >= 0 ? 'up' : 'down';
      $arrow = $pct >= 0 ? '▲' : '▼';
      return '<span class="kpi-change '.$dir.'">'.$arrow.' '.number_format(abs($pct),1).'% vs prev period</span>';
  }
  ?>
  <div class="kpi-grid">
    <div class="kpi-card" style="border-top-color:var(--gold)">
      <div class="kpi-label">Total Revenue</div>
      <div class="kpi-value"><?= format_money($revenue_total) ?></div>
      <?= kpi_change($rev_chg) ?>
    </div>
    <div class="kpi-card" style="border-top-color:var(--blue,#3b82f6)">
      <div class="kpi-label">Total Orders</div>
      <div class="kpi-value"><?= number_format($orders_total) ?></div>
      <?= kpi_change($ord_chg) ?>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Avg. Order Value</div>
      <div class="kpi-value"><?= format_money($avg_order) ?></div>
      <?= kpi_change($avg_chg) ?>
    </div>
    <div class="kpi-card" style="border-top-color:var(--green,#22c55e)">
      <div class="kpi-label">Unique Customers</div>
      <div class="kpi-value"><?= number_format($unique_customers) ?></div>
      <span class="kpi-change flat">registered accounts</span>
    </div>
  </div>

  <!-- Revenue line chart + Payment donut -->
  <div class="an-grid-3" style="grid-template-columns:2fr 1fr">
    <div class="an-card">
      <div class="an-card-head">
        <h3>Revenue Over Time</h3>
        <span style="font-size:.75rem;color:var(--text-2)"><?= $ranges[$range] ?></span>
      </div>
      <div class="an-card-body">
        <div class="chart-wrap">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
    <div class="an-card">
      <div class="an-card-head"><h3>By Payment Method</h3></div>
      <div class="an-card-body">
        <div class="donut-wrap">
          <canvas id="donutChart" width="160" height="160"></canvas>
          <div class="donut-center">
            <div class="dc-val"><?= count($payment_data) ?></div>
            <div class="dc-lbl">Methods</div>
          </div>
        </div>
        <div class="pay-legend" id="payLegend"></div>
      </div>
    </div>
  </div>

  <!-- Category bars + Hourly heatmap -->
  <div class="an-grid-2">
    <div class="an-card">
      <div class="an-card-head"><h3>Revenue by Category</h3></div>
      <div class="an-card-body">
        <?php if (empty($cat_data)): ?>
          <p style="color:var(--text-2);font-size:.85rem">No data for this period.</p>
        <?php else:
          $max_rev = max(array_column($cat_data, 'rev'));
          foreach ($cat_data as $c):
            $pct = $max_rev > 0 ? ($c['rev'] / $max_rev * 100) : 0;
        ?>
          <div class="cat-bar-item" style="margin-bottom:.85rem">
            <div class="cat-bar-meta">
              <span class="cat-bar-name"><?= h($c['category']) ?></span>
              <span class="cat-bar-val"><?= format_money($c['rev']) ?> · <?= $c['units'] ?> units</span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="an-card">
      <div class="an-card-head"><h3>Sales by Hour</h3></div>
      <div class="an-card-body">
        <p style="font-size:.72rem;color:var(--text-2);margin-bottom:.85rem;margin-top:-.25rem">Darker = more sales activity</p>
        <div class="heatmap-grid" id="heatmapGrid"></div>
        <div class="hm-labels">
          <?php for ($h = 0; $h < 24; $h++): ?>
            <div class="hm-label"><?= $h ?></div>
          <?php endfor; ?>
        </div>
        <div style="margin-top:1.25rem;display:flex;flex-direction:column;gap:.4rem">
          <?php
          $max_hr = !empty($hourly_raw) ? max(array_column($hourly_raw, 'cnt')) : 0;
          $best_hr = array_reduce($hourly_raw, fn($carry, $r) => (!$carry || $r['cnt'] > $carry['cnt']) ? $r : $carry, null);
          if ($best_hr):
          ?>
            <div style="font-size:.78rem;color:var(--text-2)">
              Peak hour: <strong style="color:var(--text-0)"><?= date('g A', mktime($best_hr['hr'])) ?></strong>
              — <?= $best_hr['cnt'] ?> sales · <?= format_money($best_hr['rev']) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Products + Biggest Sales -->
  <div class="an-grid-2">
    <div class="an-card">
      <div class="an-card-head">
        <h3>Top Products</h3>
        <span style="font-size:.75rem;color:var(--text-2)">by revenue</span>
      </div>
      <div class="an-card-body" style="padding:0">
        <?php if (empty($top_products)): ?>
          <p style="padding:1.25rem;color:var(--text-2);font-size:.85rem">No sales data yet.</p>
        <?php else: ?>
        <table class="top-table">
          <thead>
            <tr><th>#</th><th>Product</th><th>Units</th><th>Revenue</th></tr>
          </thead>
          <tbody>
            <?php foreach ($top_products as $i => $prod):
              $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
            ?>
            <tr>
              <td><span class="rank-num <?= $rankClass ?>"><?= $i + 1 ?></span></td>
              <td>
                <div style="font-weight:600;font-size:.85rem"><?= h($prod['product_name']) ?></div>
                <div style="font-size:.72rem;color:var(--text-2)"><?= h($prod['category']) ?></div>
              </td>
              <td style="text-align:center;color:var(--text-2)"><?= $prod['units_sold'] ?></td>
              <td style="font-family:var(--font-display);color:var(--gold)"><?= format_money($prod['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <h3>Highest Value Sales</h3>
        <span style="font-size:.75rem;color:var(--text-2)">this period</span>
      </div>
      <div class="an-card-body">
        <?php if (empty($big_sales)): ?>
          <p style="color:var(--text-2);font-size:.85rem">No sales yet.</p>
        <?php else: ?>
        <div class="big-sale-list">
          <?php foreach ($big_sales as $s): ?>
            <div class="big-sale-row">
              <span class="bsr-id">#<?= $s['sale_id'] ?></span>
              <div style="flex:1">
                <div class="bsr-cust"><?= $s['customer_name'] ? h($s['customer_name']) : 'Walk-in' ?></div>
                <div class="bsr-method"><?= h($s['payment_method']) ?> · <?= date('M j, g:i A', strtotime($s['sale_date'])) ?></div>
              </div>
              <span class="bsr-amount"><?= format_money($s['total_amount']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
// ── Data from PHP ──
const dailyData   = <?= json_encode($daily_data) ?>;
const paymentData = <?= json_encode($payment_data) ?>;
const hourlyMap   = <?= json_encode($hourly_map) ?>;

// ── Palette ──
const GOLD   = getComputedStyle(document.documentElement).getPropertyValue('--gold').trim()   || '#c99526';
const TEXT0  = getComputedStyle(document.documentElement).getPropertyValue('--text-0').trim() || '#ffffff';
const TEXT2  = getComputedStyle(document.documentElement).getPropertyValue('--text-2').trim() || '#888';
const BG1    = getComputedStyle(document.documentElement).getPropertyValue('--bg-1').trim()   || '#1a1a1a';
const BORDER = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#333';
const PAY_COLORS = ['#c99526','#3b82f6','#22c55e','#ef4444','#a855f7','#f97316'];

// ── Revenue Line Chart ──
(function() {
  const canvas = document.getElementById('revenueChart');
  const ctx    = canvas.getContext('2d');
  const W = canvas.offsetWidth, H = canvas.offsetHeight;
  canvas.width  = W * devicePixelRatio;
  canvas.height = H * devicePixelRatio;
  ctx.scale(devicePixelRatio, devicePixelRatio);

  const vals   = dailyData.map(d => d.rev);
  const maxVal = Math.max(...vals, 1);
  const PAD    = { top: 20, right: 16, bottom: 36, left: 64 };
  const cW = W - PAD.left - PAD.right;
  const cH = H - PAD.top  - PAD.bottom;
  const n  = vals.length;

  function xPos(i) { return PAD.left + (i / Math.max(n - 1, 1)) * cW; }
  function yPos(v) { return PAD.top  + (1 - v / maxVal) * cH; }

  // Grid lines
  ctx.strokeStyle = BORDER;
  ctx.lineWidth   = 1;
  for (let i = 0; i <= 4; i++) {
    const y = PAD.top + (i / 4) * cH;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + cW, y); ctx.stroke();
    const label = '₱' + ((maxVal * (1 - i / 4)) / 1000).toFixed(0) + 'k';
    ctx.fillStyle   = TEXT2;
    ctx.font        = '10px sans-serif';
    ctx.textAlign   = 'right';
    ctx.fillText(label, PAD.left - 8, y + 4);
  }

  // X-axis labels (show ~6 evenly)
  const step = Math.max(1, Math.floor(n / 6));
  ctx.fillStyle = TEXT2;
  ctx.font = '10px sans-serif';
  ctx.textAlign = 'center';
  for (let i = 0; i < n; i += step) {
    const d   = new Date(dailyData[i].d + 'T00:00:00');
    const lbl = (d.getMonth() + 1) + '/' + d.getDate();
    ctx.fillText(lbl, xPos(i), H - PAD.bottom + 16);
  }

  // Gradient fill
  const grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top + cH);
  grad.addColorStop(0, GOLD + '44');
  grad.addColorStop(1, GOLD + '00');
  ctx.beginPath();
  vals.forEach((v, i) => i === 0 ? ctx.moveTo(xPos(i), yPos(v)) : ctx.lineTo(xPos(i), yPos(v)));
  ctx.lineTo(xPos(n - 1), PAD.top + cH);
  ctx.lineTo(xPos(0), PAD.top + cH);
  ctx.closePath();
  ctx.fillStyle = grad;
  ctx.fill();

  // Line
  ctx.beginPath();
  ctx.strokeStyle = GOLD;
  ctx.lineWidth   = 2;
  ctx.lineJoin    = 'round';
  vals.forEach((v, i) => i === 0 ? ctx.moveTo(xPos(i), yPos(v)) : ctx.lineTo(xPos(i), yPos(v)));
  ctx.stroke();

  // Hover tooltip
  let hoverIdx = null;
  canvas.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    const mx   = e.clientX - rect.left;
    let   best = 0, bestDist = Infinity;
    for (let i = 0; i < n; i++) {
      const d = Math.abs(xPos(i) - mx);
      if (d < bestDist) { bestDist = d; best = i; }
    }
    hoverIdx = bestDist < 40 ? best : null;
    redraw();
  });
  canvas.addEventListener('mouseleave', () => { hoverIdx = null; redraw(); });

  function redraw() {
    ctx.clearRect(0, 0, W, H);

    // Grid
    ctx.strokeStyle = BORDER; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = PAD.top + (i / 4) * cH;
      ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + cW, y); ctx.stroke();
      ctx.fillStyle = TEXT2; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
      ctx.fillText('₱' + ((maxVal * (1 - i / 4)) / 1000).toFixed(0) + 'k', PAD.left - 8, y + 4);
    }
    ctx.fillStyle = TEXT2; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
    for (let i = 0; i < n; i += step) {
      const d = new Date(dailyData[i].d + 'T00:00:00');
      ctx.fillText((d.getMonth()+1)+'/'+d.getDate(), xPos(i), H - PAD.bottom + 16);
    }

    // Fill
    ctx.beginPath();
    vals.forEach((v, i) => i === 0 ? ctx.moveTo(xPos(i), yPos(v)) : ctx.lineTo(xPos(i), yPos(v)));
    ctx.lineTo(xPos(n-1), PAD.top + cH); ctx.lineTo(xPos(0), PAD.top + cH); ctx.closePath();
    ctx.fillStyle = grad; ctx.fill();

    // Line
    ctx.beginPath(); ctx.strokeStyle = GOLD; ctx.lineWidth = 2; ctx.lineJoin = 'round';
    vals.forEach((v, i) => i === 0 ? ctx.moveTo(xPos(i), yPos(v)) : ctx.lineTo(xPos(i), yPos(v)));
    ctx.stroke();

    // Hover
    if (hoverIdx !== null) {
      const x = xPos(hoverIdx), y = yPos(vals[hoverIdx]);
      // Vertical line
      ctx.beginPath(); ctx.strokeStyle = TEXT2; ctx.lineWidth = 1; ctx.setLineDash([4, 3]);
      ctx.moveTo(x, PAD.top); ctx.lineTo(x, PAD.top + cH); ctx.stroke();
      ctx.setLineDash([]);
      // Dot
      ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2);
      ctx.fillStyle = GOLD; ctx.fill();
      ctx.strokeStyle = BG1; ctx.lineWidth = 2; ctx.stroke();
      // Tooltip box
      const d    = dailyData[hoverIdx];
      const tip1 = d.d;
      const tip2 = '₱' + d.rev.toLocaleString('en-PH', {minimumFractionDigits:2});
      const tip3 = d.cnt + ' order' + (d.cnt !== 1 ? 's' : '');
      const bw   = 130, bh = 52;
      let bx = x + 10, by = y - bh / 2;
      if (bx + bw > W - PAD.right) bx = x - bw - 10;
      if (by < PAD.top) by = PAD.top;
      ctx.fillStyle   = TEXT0; ctx.globalAlpha = .95;
      ctx.fillRect(bx, by, bw, bh);
      ctx.globalAlpha = 1;
      ctx.fillStyle   = '#000';
      ctx.font        = 'bold 11px sans-serif'; ctx.textAlign = 'left';
      ctx.fillText(tip1, bx + 8, by + 16);
      ctx.font = '11px sans-serif';
      ctx.fillText(tip2, bx + 8, by + 30);
      ctx.fillStyle = '#555';
      ctx.fillText(tip3, bx + 8, by + 44);
    }
  }
  redraw();
})();

// ── Payment Donut ──
(function() {
  const canvas = document.getElementById('donutChart');
  const ctx    = canvas.getContext('2d');
  const dpr    = devicePixelRatio;
  canvas.width  = 160 * dpr;
  canvas.height = 160 * dpr;
  ctx.scale(dpr, dpr);

  const total  = paymentData.reduce((s, p) => s + parseFloat(p.rev), 0);
  const cx = 80, cy = 80, outerR = 72, innerR = 46;
  let   startAngle = -Math.PI / 2;
  let   hoveredIdx = null;

  const slices = paymentData.map((p, i) => ({
    pct:   total > 0 ? parseFloat(p.rev) / total : 0,
    color: PAY_COLORS[i % PAY_COLORS.length],
    label: p.payment_method,
    rev:   parseFloat(p.rev),
    cnt:   parseInt(p.cnt),
  }));

  function draw() {
    ctx.clearRect(0, 0, 160, 160);
    let angle = -Math.PI / 2;
    slices.forEach((s, i) => {
      const sweep  = s.pct * Math.PI * 2;
      const hov    = i === hoveredIdx;
      const r      = hov ? outerR + 4 : outerR;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, angle, angle + sweep);
      ctx.closePath();
      ctx.fillStyle = s.color;
      ctx.globalAlpha = hov ? 1 : .85;
      ctx.fill();
      ctx.globalAlpha = 1;
      // Gap
      ctx.beginPath();
      ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
      ctx.fillStyle = BG1;
      ctx.fill();
      angle += sweep;
    });
    if (slices.length === 0) {
      ctx.beginPath(); ctx.arc(cx, cy, outerR, 0, Math.PI * 2);
      ctx.fillStyle = BORDER; ctx.fill();
      ctx.beginPath(); ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
      ctx.fillStyle = BG1; ctx.fill();
    }
  }

  canvas.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    const mx   = (e.clientX - rect.left) * (160 / rect.width);
    const my   = (e.clientY - rect.top)  * (160 / rect.height);
    const dx   = mx - cx, dy = my - cy;
    const dist = Math.sqrt(dx * dx + dy * dy);
    if (dist < innerR || dist > outerR + 8) { hoveredIdx = null; draw(); return; }
    let angle = Math.atan2(dy, dx) - (-Math.PI / 2);
    if (angle < 0) angle += Math.PI * 2;
    let cum = 0;
    hoveredIdx = null;
    slices.forEach((s, i) => {
      if (angle >= cum && angle < cum + s.pct * Math.PI * 2) hoveredIdx = i;
      cum += s.pct * Math.PI * 2;
    });
    draw();
  });
  canvas.addEventListener('mouseleave', () => { hoveredIdx = null; draw(); });

  draw();

  // Legend
  const leg = document.getElementById('payLegend');
  slices.forEach((s, i) => {
    const row = document.createElement('div');
    row.className = 'pay-leg-row';
    row.innerHTML = `<span class="pay-leg-dot" style="background:${s.color}"></span>
      <span class="pay-leg-name">${s.label}</span>
      <span class="pay-leg-pct">${(s.pct*100).toFixed(1)}%</span>`;
    leg.appendChild(row);
  });
})();

// ── Hourly Heatmap ──
(function() {
  const grid   = document.getElementById('heatmapGrid');
  const counts = Array.from({length:24}, (_,h) => hourlyMap[h] ? parseInt(hourlyMap[h].cnt) : 0);
  const maxC   = Math.max(...counts, 1);
  counts.forEach((cnt, h) => {
    const cell  = document.createElement('div');
    cell.className = 'hm-cell';
    const intensity = cnt / maxC;
    // From dim bg to gold
    const r = Math.round(201 * intensity);
    const g = Math.round(149 * intensity);
    const b = Math.round(38  * intensity);
    const a = .15 + intensity * .85;
    cell.style.background = `rgba(${r},${g},${b},${a})`;
    const rev = hourlyMap[h] ? parseFloat(hourlyMap[h].rev) : 0;
    cell.setAttribute('data-tip', `${h}:00 — ${cnt} sales\n₱${rev.toLocaleString('en-PH',{minimumFractionDigits:2})}`);
    cell.title = `${h}:00  ${cnt} sale${cnt!==1?'s':''} · ₱${rev.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
    grid.appendChild(cell);
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>