<?php
require_once __DIR__ . '/_garde.php';

$jeux   = mes_jeux($pdo, $email_resp);
$id_jeu = filter_input(INPUT_GET, 'id_jeu', FILTER_VALIDATE_INT) ?: 0;
$cat    = trim($_GET['categorie'] ?? '');
$du     = $_GET['du'] ?? '';
$au     = $_GET['au'] ?? '';
$erreur = '';

$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re_date, $du)) $du = '';
if (!preg_match($re_date, $au)) $au = '';
if ($du && $au && $du > $au) {
    $erreur = "La date de début doit être antérieure à la date de fin.";
    $du = $au = '';
}

// Catégories disponibles (uniquement celles de MES jeux)
$st = $pdo->prepare(
    "SELECT DISTINCT d.categorie_depense
     FROM DEPENSE d JOIN JEU j ON j.id_jeu = d.id_jeu
     WHERE j.email_responsable = ? AND d.categorie_depense IS NOT NULL
     ORDER BY d.categorie_depense"
);
$st->execute([$email_resp]);
$categories = $st->fetchAll(PDO::FETCH_COLUMN);

// Dépenses filtrées (toujours limitées à mes jeux)
$sql = "SELECT d.*, j.nom AS nom_jeu
        FROM DEPENSE d JOIN JEU j ON j.id_jeu = d.id_jeu
        WHERE j.email_responsable = ?";
$p = [$email_resp];
if ($id_jeu)     { $sql .= " AND d.id_jeu = ?";            $p[] = $id_jeu; }
if ($cat !== '') { $sql .= " AND d.categorie_depense = ?"; $p[] = $cat; }
if ($du)         { $sql .= " AND d.date_depense >= ?";     $p[] = $du; }
if ($au)         { $sql .= " AND d.date_depense <= ?";     $p[] = $au; }
$sql .= " ORDER BY d.date_depense DESC, d.id_depense DESC";

$st = $pdo->prepare($sql);
$st->execute($p);
$deps = $st->fetchAll();

// Indicateurs
$nb      = count($deps);
$total   = array_sum(array_column($deps, 'montant_depense'));
$moyenne = $nb ? $total / $nb : 0;
$maxdep  = $nb ? max(array_column($deps, 'montant_depense')) : 0;

// Données des graphiques
$par_jour = $par_cat = $par_jeu = [];
foreach ($deps as $d) {
    $m = (float)$d['montant_depense'];
    $c = $d['categorie_depense'] ?: 'Autre';
    $par_jour[$d['date_depense']] = ($par_jour[$d['date_depense']] ?? 0) + $m;
    $par_cat[$c]                  = ($par_cat[$c] ?? 0) + $m;
    $par_jeu[$d['nom_jeu']]       = ($par_jeu[$d['nom_jeu']] ?? 0) + $m;
}
ksort($par_jour);
arsort($par_cat);
arsort($par_jeu);

$J = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$donnees = [
    'jourL' => array_map(fn($d) => date('d/m', strtotime($d)), array_keys($par_jour)),
    'jourV' => array_values($par_jour),
    'catL'  => array_keys($par_cat),
    'catV'  => array_values($par_cat),
    'jeuL'  => array_keys($par_jeu),
    'jeuV'  => array_values($par_jeu),
];

$nb_filtres = ($id_jeu ? 1 : 0) + ($cat !== '' ? 1 : 0) + ($du ? 1 : 0) + ($au ? 1 : 0);

entete("Dépenses", false);
?>

<!-- ===== En-tête : titre + bouton Filtrer ===== -->
<div class="row align-center justify-between">
    <div class="col-12 col-md">
        <h1 class="page-title">Dépenses de vos jeux</h1>
        <p class="subtitle">Suivi en lecture seule des dépenses enregistrées pour vos attractions.</p>
    </div>
    <div class="col-12 col-md-auto">
        <div class="page-actions">
            <?php if ($nb_filtres): ?>
                <a class="link-muted" href="depenses.php">✕ Réinitialiser</a>
            <?php endif; ?>
            <button type="button" id="btnFiltre" class="btn btn-pill <?= $nb_filtres ? 'is-open' : '' ?>">
                ⚙ Filtrer<?= $nb_filtres ? ' (' . $nb_filtres . ')' : '' ?>
            </button>
        </div>
    </div>
</div>

<?php if ($erreur): ?>
<div class="row">
    <div class="col-12"><div class="alert alert-danger"><?= h($erreur) ?></div></div>
</div>
<?php endif; ?>

<!-- ===== Panneau de filtres (caché tant qu'aucun filtre n'est actif) ===== -->
<form method="get" id="panneauFiltre" class="row" <?= $nb_filtres ? '' : 'hidden' ?>>
    <div class="col-12">
        <div class="filter-panel">
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="f-jeu">Jeu</label>
                    <select name="id_jeu" id="f-jeu" class="form-control">
                        <option value="0">Tous mes jeux</option>
                        <?php foreach ($jeux as $j): ?>
                            <option value="<?= (int)$j['id_jeu'] ?>" <?= $j['id_jeu'] == $id_jeu ? 'selected' : '' ?>><?= h($j['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="f-cat">Catégorie</label>
                    <select name="categorie" id="f-cat" class="form-control">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= h($c) ?>" <?= $c === $cat ? 'selected' : '' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="f-du">Du</label>
                    <input type="date" name="du" id="f-du" class="form-control" value="<?= h($du) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="f-au">Au</label>
                    <input type="date" name="au" id="f-au" class="form-control" value="<?= h($au) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ===== Indicateurs : 1 / 2 / 4 par ligne ===== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--violet">
            <span class="kpi-label">Total dépenses</span>
            <div class="kpi-value"><?= number_format($total, 2) ?> <small>DH</small></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--cyan">
            <span class="kpi-label">Nombre de dépenses</span>
            <div class="kpi-value"><?= $nb ?></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--dark">
            <span class="kpi-label">Moyenne par dépense</span>
            <div class="kpi-value"><?= number_format($moyenne, 2) ?> <small>DH</small></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--dark">
            <span class="kpi-label">Plus grosse dépense</span>
            <div class="kpi-value"><?= number_format($maxdep, 2) ?> <small>DH</small></div>
        </div>
    </div>
</div>

<?php if ($deps): ?>
<!-- ===== Graphiques ===== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <h3 class="chart-title">Évolution des dépenses</h3>
            <div class="chart-box"><canvas id="chLigne"></canvas></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <h3 class="chart-title">Répartition par catégorie</h3>
            <div class="chart-box"><canvas id="chDonut"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <h3 class="chart-title">Dépenses par jeu</h3>
            <div class="chart-box"><canvas id="chBarres"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Tableau ===== -->
<div class="row">
    <div class="col-12">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Jeu</th>
                        <th>Catégorie</th>
                        <th>Description</th>
                        <th class="num">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deps as $d): ?>
                    <tr>
                        <td><?= h($d['date_depense']) ?></td>
                        <td><?= h($d['nom_jeu']) ?></td>
                        <td><span class="badge is-muted"><?= h($d['categorie_depense']) ?></span></td>
                        <td><?= h($d['description_depense']) ?></td>
                        <td class="num"><?= number_format($d['montant_depense'], 2) ?> DH</td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!$deps): ?>
                    <tr><td colspan="5" class="empty">Aucune dépense pour ces critères.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total</td>
                        <td class="num"><?= number_format($total, 2) ?> DH</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// ----- Bouton Filtrer -----
const btn = document.getElementById('btnFiltre');
const panneau = document.getElementById('panneauFiltre');
btn.addEventListener('click', () => {
  panneau.hidden = !panneau.hidden;
  btn.classList.toggle('is-open', !panneau.hidden);
});
panneau.addEventListener('submit', (e) => {
  const du = panneau.du.value, au = panneau.au.value;
  if (du && au && du > au) {
    e.preventDefault();
    alert("La date de début doit être antérieure à la date de fin.");
  }
});

// ----- Graphiques -----
const D = <?= $J($donnees) ?>;

if (document.getElementById('chLigne')) {
  if (typeof Chart === 'undefined') {
    document.querySelectorAll('.chart-box').forEach(b =>
      b.innerHTML = '<p class="empty">Graphiques indisponibles (connexion Internet requise).</p>');
  } else {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Segoe UI', Tahoma, sans-serif";
    const grille = 'rgba(255,255,255,.06)';
    const palette = ['#4f46e5', '#22d3ee', '#c471f5', '#10b981', '#f59e0b', '#f43f5e'];
    const fmt = (v) => Number(v).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' DH';

    // Courbe : évolution par jour
    const ctxL = document.getElementById('chLigne').getContext('2d');
    const gL = ctxL.createLinearGradient(0, 0, 0, 300);
    gL.addColorStop(0, 'rgba(129,140,248,.55)');
    gL.addColorStop(1, 'rgba(129,140,248,0)');
    new Chart(ctxL, {
      type: 'line',
      data: { labels: D.jourL, datasets: [{
        label: 'Dépenses', data: D.jourV, borderColor: '#818cf8', backgroundColor: gL,
        fill: true, tension: .4, borderWidth: 3, pointRadius: 5, pointBackgroundColor: '#c471f5'
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmt(c.parsed.y) } } },
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: grille } } }
      }
    });

    // Donut : par catégorie
    new Chart(document.getElementById('chDonut'), {
      type: 'doughnut',
      data: { labels: D.catL, datasets: [{ data: D.catV, backgroundColor: palette, borderWidth: 0 }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '68%',
        plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => c.label + ' : ' + fmt(c.parsed) } } }
      }
    });

    // Barres : par jeu
    const ctxB = document.getElementById('chBarres').getContext('2d');
    const gB = ctxB.createLinearGradient(0, 0, 0, 300);
    gB.addColorStop(0, '#818cf8');
    gB.addColorStop(1, '#4f46e5');
    new Chart(ctxB, {
      type: 'bar',
      data: { labels: D.jeuL, datasets: [{ label: 'Dépenses', data: D.jeuV, backgroundColor: gB, borderRadius: 8, maxBarThickness: 48 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmt(c.parsed.y) } } },
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: grille } } }
      }
    });
  }
}
</script>

<?php pied(); ?>
