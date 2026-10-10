<?php
// ============================================================
// DASHBOARD FINANCIER (lecture seule) : recettes, dépenses, bénéfice net
// Bénéfice net = total des recettes (commandes payées) − total des dépenses.
// L'agent consulte uniquement : aucune saisie ni modification de dépense.
// ============================================================
require_once __DIR__ . '/_garde.php';

$erreurs = [];

/* ---------- Période (facultative) ---------- */
$du = date_valide((string)($_GET['du'] ?? ''));
$au = date_valide((string)($_GET['au'] ?? ''));
if ($du && $au && $du > $au) { $du = $au = ''; $erreurs[] = "La date de début doit précéder la date de fin."; }

$cond_c = ''; $p_c = [];
$cond_d = ''; $p_d = [];
if ($du) { $cond_c .= " AND COALESCE(c.date_paiment, c.date_commande) >= ?"; $p_c[] = $du . ' 00:00:00'; $cond_d .= " AND d.date_depense >= ?"; $p_d[] = $du; }
if ($au) { $cond_c .= " AND COALESCE(c.date_paiment, c.date_commande) < ?";  $p_c[] = date('Y-m-d 00:00:00', strtotime($au . ' +1 day')); $cond_d .= " AND d.date_depense <= ?"; $p_d[] = $au; }

// Courbe : par jour si la période est courte (≤ 62 jours), sinon par mois
$par_jour = $du && $au && (strtotime($au) - strtotime($du)) <= 62 * 86400;
$fmt_sql  = $par_jour ? '%Y-%m-%d' : '%Y-%m';

/* ---------- Recettes ---------- */
$st = $pdo->prepare("SELECT COALESCE(SUM(c.montant_total), 0) FROM commande c WHERE c.statut_commande = 'payée'" . $cond_c);
$st->execute($p_c);
$recettes = (float)$st->fetchColumn();

// Le prix d'un billet est réparti à parts égales entre les jeux auxquels il donne accès
$st = $pdo->prepare(
    "SELECT j.nom, COALESCE(x.ca, 0) AS ca
     FROM jeu j
     LEFT JOIN (
        SELECT ct.id_jeu, SUM(b.prix_unitaire * b.quantite / n.nb_jeux) AS ca
        FROM commande c
        JOIN billet b ON b.num_commande = c.num_commande
        JOIN contient ct ON ct.id_billet = b.id_billet
        JOIN (SELECT id_billet, COUNT(*) AS nb_jeux FROM contient GROUP BY id_billet) n ON n.id_billet = b.id_billet
        WHERE c.statut_commande = 'payée'" . $cond_c . "
        GROUP BY ct.id_jeu
     ) x ON x.id_jeu = j.id_jeu
     ORDER BY ca DESC, j.nom"
);
$st->execute($p_c);
$par_jeu = $st->fetchAll();
$ca_global = max(0, round($recettes - array_sum(array_map(fn($r) => (float)$r['ca'], $par_jeu)), 2));

$st = $pdo->prepare(
    "SELECT DATE_FORMAT(COALESCE(c.date_paiment, c.date_commande), '$fmt_sql') AS p, SUM(c.montant_total) AS m
     FROM commande c WHERE c.statut_commande = 'payée'" . $cond_c . " GROUP BY p"
);
$st->execute($p_c);
$rec_periode = $st->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---------- Dépenses ---------- */
$st = $pdo->prepare("SELECT COALESCE(SUM(d.montant_depense), 0) FROM depense d WHERE 1 = 1" . $cond_d);
$st->execute($p_d);
$depenses_total = (float)$st->fetchColumn();

$st = $pdo->prepare(
    "SELECT DATE_FORMAT(d.date_depense, '$fmt_sql') AS p, SUM(d.montant_depense) AS m
     FROM depense d WHERE 1 = 1" . $cond_d . " GROUP BY p"
);
$st->execute($p_d);
$dep_periode = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare(
    "SELECT COALESCE(NULLIF(d.categorie_depense, ''), 'Autre') AS cat, SUM(d.montant_depense) AS m
     FROM depense d WHERE 1 = 1" . $cond_d . " GROUP BY cat ORDER BY m DESC"
);
$st->execute($p_d);
$par_categorie = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT d.categorie_depense, d.description_depense, d.montant_depense, d.date_depense, j.nom AS jeu
     FROM depense d LEFT JOIN jeu j ON j.id_jeu = d.id_jeu
     WHERE 1 = 1" . $cond_d . "
     ORDER BY d.date_depense DESC, d.id_depense DESC LIMIT 30"
);
$st->execute($p_d);
$depenses = $st->fetchAll();

$benefice = $recettes - $depenses_total;

/* ---------- Billetterie ---------- */
$bil = $pdo->query(
    "SELECT COALESCE(SUM(b.quantite), 0) AS vendus,
            COALESCE(SUM(CASE WHEN b.statut_billet = 'valide' THEN b.quantite END), 0) AS valides,
            COALESCE(SUM(CASE WHEN b.statut_billet = 'utilisé' THEN b.quantite END), 0) AS utilises
     FROM billet b JOIN commande c ON c.num_commande = b.num_commande
     WHERE c.statut_commande = 'payée'"
)->fetch();
$top_jeux = $pdo->query(
    "SELECT j.nom, COUNT(DISTINCT r.num_reservation) AS nb
     FROM choisir ch
     JOIN jeu j ON j.id_jeu = ch.id_jeu
     JOIN reservation r ON r.num_reservation = ch.num_reservation
     WHERE r.statut_reservation <> 'annulée'
     GROUP BY j.id_jeu, j.nom ORDER BY nb DESC, j.nom LIMIT 5"
)->fetchAll();
$max_top = max(1, (int)($top_jeux[0]['nb'] ?? 1));

/* ---------- Données des graphiques ---------- */
$periodes = array_unique(array_merge(array_keys($rec_periode), array_keys($dep_periode)));
sort($periodes);
$g_jeux_labels = array_column($par_jeu, 'nom');
$g_jeux_data   = array_map(fn($r) => round((float)$r['ca'], 2), $par_jeu);
if ($ca_global > 0) { $g_jeux_labels[] = 'Tous les jeux'; $g_jeux_data[] = $ca_global; }

$donnees = [
    'jeux'    => ['labels' => $g_jeux_labels, 'data' => $g_jeux_data],
    'periode' => [
        'labels'    => $periodes,
        'recettes'  => array_map(fn($p) => round((float)($rec_periode[$p] ?? 0), 2), $periodes),
        'depenses'  => array_map(fn($p) => round((float)($dep_periode[$p] ?? 0), 2), $periodes),
        'benefice'  => array_map(fn($p) => round((float)($rec_periode[$p] ?? 0) - (float)($dep_periode[$p] ?? 0), 2), $periodes),
        'unite'     => $par_jour ? 'jour' : 'mois',
    ],
    'categories' => ['labels' => array_column($par_categorie, 'cat'), 'data' => array_map(fn($r) => round((float)$r['m'], 2), $par_categorie)],
];

entete("Dashboard", false);
?>

<div class="row">
    <div class="col-12">
        <h1 class="page-title">Dashboard financier</h1>
        <p class="subtitle">Recettes, dépenses (consultation) et bénéfice net<?= ($du || $au) ? ' de la période sélectionnée' : ' (toutes périodes)' ?>.</p>
    </div>
</div>

<?php if ($erreurs): ?>
<div class="row"><div class="col-12"><div class="alert alert-danger">
    <?php foreach ($erreurs as $e): ?><div>❌ <?= h($e) ?></div><?php endforeach; ?>
</div></div></div>
<?php endif; ?>

<form method="get" class="row">
    <div class="col-12"><div class="filter-panel"><div class="row">
        <div class="col-12 col-sm-4 col-lg-3"><label class="form-label" for="du">Du</label><input type="date" id="du" name="du" class="form-control" value="<?= h($du) ?>"></div>
        <div class="col-12 col-sm-4 col-lg-3"><label class="form-label" for="au">Au</label><input type="date" id="au" name="au" class="form-control" value="<?= h($au) ?>"></div>
        <div class="col-12 col-sm-4 col-lg-3"><label class="form-label">&nbsp;</label>
            <div class="page-actions"><button class="btn btn-primary" type="submit">Filtrer</button><a href="index.php" class="btn btn-ghost">Réinitialiser</a></div>
        </div>
    </div></div></div>
</form>

<div class="row">
    <div class="col-12 col-md-4"><div class="kpi kpi--cyan"><span class="kpi-label">Total recettes</span><div class="kpi-value"><?= dh($recettes) ?></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi--dark"><span class="kpi-label">Total dépenses</span><div class="kpi-value"><?= dh($depenses_total) ?></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi--violet"><span class="kpi-label">Bénéfice net</span><div class="kpi-value"><?= dh($benefice) ?></div></div></div>
</div>

<div class="row mt-1">
    <div class="col-12 col-md-4"><div class="kpi kpi--cyan"><span class="kpi-label">Billets vendus</span><div class="kpi-value"><?= (int)$bil['vendus'] ?></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi--dark"><span class="kpi-label">Billets valides</span><div class="kpi-value"><?= (int)$bil['valides'] ?></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi--violet"><span class="kpi-label">Billets utilisés</span><div class="kpi-value"><?= (int)$bil['utilises'] ?></div></div></div>
</div>

<div class="row mt-1">
    <div class="col-12 col-lg-6">
        <h3 class="card-title">Jeux les plus réservés</h3>
        <div class="card">
            <?php foreach ($top_jeux as $i => $tj): ?>
                <div style="margin-bottom:.6rem">
                    <div style="display:flex;justify-content:space-between"><span><?= $i + 1 ?>. <?= h($tj['nom']) ?></span><strong><?= (int)$tj['nb'] ?></strong></div>
                    <div style="background:#e5e7eb;border-radius:6px;height:8px"><div style="background:#06b6d4;border-radius:6px;height:8px;width:<?= round(100 * (int)$tj['nb'] / $max_top) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$top_jeux): ?><p class="text-muted">Aucune réservation.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <h3 class="card-title">Billetterie</h3>
        <div class="card">
            <p class="text-muted">Consulter, rechercher ou réimprimer les billets, et contrôler un billet à l'entrée.</p>
            <div class="page-actions"><a class="btn btn-primary" href="tous_billets.php">🎟 Liste des billets</a><a class="btn btn-ghost" href="billets.php">Vérifier un billet</a></div>
        </div>
    </div>
</div>

<div class="row mt-1">
    <div class="col-12 col-lg-6">
        <h3 class="card-title">Recettes par jeu</h3>
        <div class="card"><div style="position:relative;height:300px"><canvas id="chart-jeux"></canvas></div></div>
    </div>
    <div class="col-12 col-lg-6">
        <h3 class="card-title">Répartition des dépenses</h3>
        <div class="card"><div style="position:relative;height:300px"><canvas id="chart-categories"></canvas></div></div>
    </div>
</div>

<div class="row mt-1">
    <div class="col-12">
        <h3 class="card-title">Recettes vs dépenses (par <?= $par_jour ? 'jour' : 'mois' ?>)</h3>
        <div class="card"><div style="position:relative;height:340px"><canvas id="chart-periode"></canvas></div></div>
    </div>
</div>

<div class="row mt-1">
    <div class="col-12">
        <h3 class="card-title">Dépenses (consultation)</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Jeu</th><th class="num">Montant</th></tr></thead>
                <tbody>
                <?php foreach ($depenses as $x): ?>
                    <tr>
                        <td><?= h(date_fr($x['date_depense'])) ?></td>
                        <td><span class="badge is-muted"><?= h($x['categorie_depense'] ?: 'Autre') ?></span></td>
                        <td><?= h($x['description_depense']) ?></td>
                        <td><?= h($x['jeu'] ?: '—') ?></td>
                        <td class="num"><?= dh($x['montant_depense']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$depenses): ?><tr><td colspan="5" class="empty">Aucune dépense sur cette période.</td></tr><?php endif; ?>
                </tbody>
                <tfoot><tr><td colspan="4">Total dépenses</td><td class="num"><?= dh($depenses_total) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const D = <?= json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (typeof Chart === 'undefined') return;
    const dh = v => new Intl.NumberFormat('fr-FR', {minimumFractionDigits: 2}).format(v) + ' DH';
    const VIOLET = '#a855f7', CYAN = '#22d3ee', BLEU = '#3b82f6';
    const TEXTE = '#e5e7eb', GRILLE = 'rgba(255,255,255,0.05)';
    const neon = [VIOLET, CYAN, BLEU, '#c084fc', '#67e8f9', '#60a5fa', '#818cf8', '#2dd4bf'];

    Chart.defaults.color = TEXTE;
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.plugins.legend.labels.color = TEXTE;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    const tooltip = {
        backgroundColor: 'rgba(15,10,30,0.95)', titleColor: '#fff', bodyColor: TEXTE, borderColor: VIOLET, borderWidth: 1,
        callbacks: {label: c => (c.dataset.label ? c.dataset.label + ' : ' : c.label + ' : ') + dh(c.parsed.y ?? c.parsed)}
    };
    const axes = {
        x: {ticks: {color: TEXTE}, grid: {color: GRILLE}, border: {display: false}},
        y: {beginAtZero: true, ticks: {color: TEXTE}, grid: {color: GRILLE}, border: {display: false}}
    };
    const vide = (id, vide) => { if (vide) document.getElementById(id).parentNode.insertAdjacentHTML('beforeend', '<p style="text-align:center;color:' + TEXTE + '">Aucune donnée.</p>'); };
    // Fond sombre derrière chaque graphique (texte clair lisible)
    ['chart-jeux', 'chart-categories', 'chart-periode'].forEach(id => {
        const carte = document.getElementById(id).closest('.card');
        if (carte) { carte.style.background = '#0f0a1e'; carte.style.borderColor = 'rgba(168,85,247,.25)'; carte.style.color = TEXTE; }
    });
    // Dégradé vertical calculé sur la zone réellement dessinée
    const degrade = (haut, bas) => ctx => {
        const a = ctx.chart.chartArea;
        if (!a) return haut;
        const g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
        g.addColorStop(0, haut); g.addColorStop(1, bas);
        return g;
    };

    new Chart('chart-jeux', {
        type: 'bar',
        data: {labels: D.jeux.labels, datasets: [{
            label: 'Recettes', data: D.jeux.data,
            backgroundColor: degrade('#c084fc', '#4c1d95'),
            borderWidth: 0, borderRadius: 8, borderSkipped: 'bottom', barThickness: 30
        }]},
        options: {maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip}, scales: axes}
    });

    new Chart('chart-categories', {
        type: 'doughnut',
        data: {labels: D.categories.labels, datasets: [{data: D.categories.data, backgroundColor: neon, borderWidth: 0, hoverOffset: 6}]},
        options: {
            maintainAspectRatio: false, cutout: '78%',
            plugins: {legend: {position: 'bottom', labels: {color: TEXTE}}, tooltip: {...tooltip, callbacks: {label: c => c.label + ' : ' + dh(c.parsed)}}}
        }
    });
    vide('chart-categories', !D.categories.labels.length);

    new Chart('chart-periode', {
        type: 'line',
        data: {labels: D.periode.labels, datasets: [
            {label: 'Recettes', data: D.periode.recettes, type: 'line', borderColor: VIOLET, borderWidth: 3, tension: 0.3,
             fill: true, backgroundColor: degrade('rgba(168,85,247,0.45)', 'rgba(168,85,247,0)'), pointBackgroundColor: VIOLET, pointRadius: 3},
            {label: 'Dépenses', data: D.periode.depenses, borderColor: CYAN, borderWidth: 2, tension: 0.3, fill: false, pointBackgroundColor: CYAN, pointRadius: 3},
            {label: 'Bénéfice net', data: D.periode.benefice, borderColor: BLEU, borderWidth: 2, borderDash: [6, 4], tension: 0.3, fill: false, pointRadius: 0}
        ]},
        options: {maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, plugins: {legend: {labels: {color: TEXTE}}, tooltip}, scales: axes}
    });
    vide('chart-periode', !D.periode.labels.length);})();
</script>

<?php pied(); ?>
