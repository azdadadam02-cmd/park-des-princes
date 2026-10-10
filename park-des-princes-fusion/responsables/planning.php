<?php
require_once __DIR__ . '/_garde.php';

$STATUTS = ['en attente', 'confirmée', 'payée', 'utilisée', 'annulée'];
$JOURS_C = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$MOIS    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

/* ---------- Paramètres (tous validés) ---------- */
$jeux   = mes_jeux($pdo, $email_resp);
$ids_ok = array_map('intval', array_column($jeux, 'id_jeu'));

$id_jeu = filter_input(INPUT_GET, 'id_jeu', FILTER_VALIDATE_INT) ?: 0;
if ($id_jeu && !in_array($id_jeu, $ids_ok, true)) $id_jeu = 0;

$statut = $_GET['statut'] ?? '';
if (!in_array($statut, $STATUTS, true)) $statut = '';

$vue = (($_GET['vue'] ?? 'agenda') === 'liste') ? 'liste' : 'agenda';

/* ---------- Semaine affichée (du lundi au dimanche) ---------- */
$aujourdhui = date('Y-m-d');
$sem = $_GET['sem'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $sem);
if (!$d || $d->format('Y-m-d') !== $sem) $d = new DateTime($aujourdhui);
$d->setTime(0, 0);
$d->modify('-' . ((int)$d->format('N') - 1) . ' days');
$lundi    = $d;
$dimanche = (clone $lundi)->modify('+6 days');
$fin_sem  = (clone $lundi)->modify('+7 days');   // borne exclusive
$prec     = (clone $lundi)->modify('-7 days')->format('Y-m-d');
$suiv     = (clone $lundi)->modify('+7 days')->format('Y-m-d');
$semaine_courante = ($aujourdhui >= $lundi->format('Y-m-d') && $aujourdhui <= $dimanche->format('Y-m-d'));

if ($lundi->format('n') === $dimanche->format('n')) {
    $label_sem = $lundi->format('j') . ' – ' . $dimanche->format('j') . ' '
               . $MOIS[$dimanche->format('n') - 1] . ' ' . $dimanche->format('Y');
} else {
    $label_sem = $lundi->format('j') . ' ' . $MOIS[$lundi->format('n') - 1] . ' – '
               . $dimanche->format('j') . ' ' . $MOIS[$dimanche->format('n') - 1] . ' ' . $dimanche->format('Y');
}

/* ---------- Requête (toujours limitée à MES jeux) ---------- */
function charger_reservations(PDO $pdo, string $email, string $debut, ?string $fin, int $id_jeu, string $statut): array {
    $sql = "SELECT r.num_reservation, r.date_visite, r.nb_personnes, r.montant, r.statut_reservation,
                   u.nom, u.prenom,
                   GROUP_CONCAT(DISTINCT j.nom ORDER BY j.nom SEPARATOR ', ') AS jeux
            FROM RESERVATION r
            JOIN choisir ch    ON ch.num_reservation = r.num_reservation
            JOIN JEU j         ON j.id_jeu = ch.id_jeu
            JOIN UTILISATEUR u ON u.email = r.email_client
            WHERE j.email_responsable = ? AND r.date_visite >= ?";
    $p = [$email, $debut . ' 00:00:00'];
    if ($fin !== null)  { $sql .= " AND r.date_visite < ?";           $p[] = $fin . ' 00:00:00'; }
    if ($id_jeu)        { $sql .= " AND j.id_jeu = ?";                $p[] = $id_jeu; }
    if ($statut !== '') { $sql .= " AND r.statut_reservation = ?";    $p[] = $statut; }
    $sql .= " GROUP BY r.num_reservation, r.date_visite, r.nb_personnes, r.montant,
                       r.statut_reservation, u.nom, u.prenom
              ORDER BY r.date_visite ASC, r.num_reservation ASC";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function heure_res(array $r): string {
    $h = substr($r['date_visite'], 11, 5);
    return $h === '00:00' ? '' : $h;
}
function est_annulee(array $r): bool {
    return mb_strtolower(trim($r['statut_reservation'])) === 'annulée';
}

$avenir  = charger_reservations($pdo, $email_resp, $aujourdhui, null, $id_jeu, $statut);
$semaine = charger_reservations($pdo, $email_resp, $lundi->format('Y-m-d'), $fin_sem->format('Y-m-d'), $id_jeu, $statut);

/* ---------- Indicateurs (réservations à venir) ---------- */
$actives    = array_values(array_filter($avenir, fn($r) => !est_annulee($r)));
$nb_avenir  = count($actives);
$pers_total = array_sum(array_map(fn($r) => (int)$r['nb_personnes'], $actives));
$nb_attente = count(array_filter($avenir, fn($r) => mb_strtolower(trim($r['statut_reservation'])) === 'en attente'));

$prochaine = '—';
if ($actives) {
    $dv   = substr($actives[0]['date_visite'], 0, 10);
    $diff = (int)(new DateTime($aujourdhui))->diff(new DateTime($dv))->format('%r%a');
    if     ($diff === 0) $prochaine = "Aujourd'hui";
    elseif ($diff === 1) $prochaine = 'Demain';
    else $prochaine = $JOURS_C[(int)date('N', strtotime($dv)) - 1] . ' ' . date('d/m', strtotime($dv));
}

/* ---------- Agenda : regroupement par jour ---------- */
$par_jour = [];
foreach ($semaine as $r) $par_jour[substr($r['date_visite'], 0, 10)][] = $r;

$jours = [];
for ($i = 0; $i < 7; $i++) {
    $dt  = (clone $lundi)->modify("+$i days");
    $k   = $dt->format('Y-m-d');
    $res = $par_jour[$k] ?? [];
    $jours[] = [
        'date' => $k,
        'nom'  => $JOURS_C[$i],
        'num'  => $dt->format('j'),
        'res'  => $res,
        'pers' => array_sum(array_map(fn($r) => est_annulee($r) ? 0 : (int)$r['nb_personnes'], $res)),
    ];
}
$pers_semaine = array_sum(array_column($jours, 'pers'));

$J = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$donnees = [
    'labels'  => array_map(fn($j) => $j['nom'] . ' ' . $j['num'], $jours),
    'valeurs' => array_column($jours, 'pers'),
];

/* ---------- URLs en conservant les filtres ---------- */
$base = ['vue' => $vue, 'id_jeu' => $id_jeu ?: '', 'statut' => $statut, 'sem' => $lundi->format('Y-m-d')];
$url  = fn(array $o = []) => 'planning.php?' . http_build_query(
    array_filter(array_merge($base, $o), fn($v) => $v !== '' && $v !== null)
);
$nb_filtres = ($id_jeu ? 1 : 0) + ($statut !== '' ? 1 : 0);

entete("Planning", false);
?>

<!-- ===== En-tête : titre + vue + filtres ===== -->
<div class="row align-center justify-between">
    <div class="col-12 col-lg">
        <h1 class="page-title">Planning de vos attractions</h1>
        <p class="subtitle">Réservations associées à vos jeux, en lecture seule.</p>
    </div>
    <div class="col-12 col-lg-auto">
        <div class="page-actions">
            <div class="segmented">
                <a class="<?= $vue === 'agenda' ? 'is-active' : '' ?>" href="<?= h($url(['vue' => 'agenda'])) ?>">Agenda</a>
                <a class="<?= $vue === 'liste'  ? 'is-active' : '' ?>" href="<?= h($url(['vue' => 'liste'])) ?>">Liste</a>
            </div>
            <?php if ($nb_filtres): ?>
                <a class="link-muted" href="<?= h($url(['id_jeu' => '', 'statut' => ''])) ?>">✕ Réinitialiser</a>
            <?php endif; ?>
            <button type="button" id="btnFiltre" class="btn btn-pill <?= $nb_filtres ? 'is-open' : '' ?>">
                ⚙ Filtrer<?= $nb_filtres ? ' (' . $nb_filtres . ')' : '' ?>
            </button>
        </div>
    </div>
</div>

<!-- ===== Panneau de filtres ===== -->
<form method="get" id="panneauFiltre" class="row" <?= $nb_filtres ? '' : 'hidden' ?>>
    <input type="hidden" name="vue" value="<?= h($vue) ?>">
    <input type="hidden" name="sem" value="<?= h($lundi->format('Y-m-d')) ?>">
    <div class="col-12">
        <div class="filter-panel">
            <div class="row">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-jeu">Jeu</label>
                    <select name="id_jeu" id="f-jeu" class="form-control">
                        <option value="0">Tous mes jeux</option>
                        <?php foreach ($jeux as $j): ?>
                            <option value="<?= (int)$j['id_jeu'] ?>" <?= (int)$j['id_jeu'] === $id_jeu ? 'selected' : '' ?>><?= h($j['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-statut">Statut</label>
                    <select name="statut" id="f-statut" class="form-control">
                        <option value="">Tous</option>
                        <?php foreach ($STATUTS as $s): ?>
                            <option value="<?= h($s) ?>" <?= $s === $statut ? 'selected' : '' ?>><?= h(ucfirst_fr($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <span class="kpi-label">Réservations à venir</span>
            <div class="kpi-value"><?= $nb_avenir ?></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--cyan">
            <span class="kpi-label">Personnes attendues</span>
            <div class="kpi-value"><?= (int)$pers_total ?> <small>pers.</small></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--dark">
            <span class="kpi-label">En attente</span>
            <div class="kpi-value"><?= $nb_attente ?></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="kpi kpi--dark">
            <span class="kpi-label">Prochaine visite</span>
            <div class="kpi-value"><?= h($prochaine) ?></div>
        </div>
    </div>
</div>

<?php if ($vue === 'agenda'): ?>

    <!-- ===== Navigation de semaine ===== -->
    <div class="row align-center justify-between">
        <div class="col-12 col-sm">
            <span class="week-title">Semaine du <?= h($label_sem) ?></span>
        </div>
        <div class="col-12 col-sm-auto">
            <div class="page-actions">
                <a class="btn btn-pill btn-sm" href="<?= h($url(['sem' => $prec])) ?>" aria-label="Semaine précédente">◀</a>
                <?php if (!$semaine_courante): ?>
                    <a class="btn btn-pill btn-sm" href="<?= h($url(['sem' => $aujourdhui])) ?>">Aujourd'hui</a>
                <?php endif; ?>
                <a class="btn btn-pill btn-sm" href="<?= h($url(['sem' => $suiv])) ?>" aria-label="Semaine suivante">▶</a>
            </div>
        </div>
    </div>

    <!-- ===== 7 jours : 1 / 2 / 7 colonnes selon l'écran ===== -->
    <div class="row">
        <?php foreach ($jours as $jr): ?>
        <div class="col-day">
            <div class="day <?= $jr['date'] === $aujourdhui ? 'is-today' : '' ?>">

                <div class="day-head">
                    <span>
                        <span class="day-name"><?= h($jr['nom']) ?></span>
                        <span class="day-num"><?= h($jr['num']) ?></span>
                    </span>
                    <?php if ($jr['pers']): ?>
                        <span class="day-count">👥 <?= (int)$jr['pers'] ?></span>
                    <?php endif; ?>
                </div>

                <?php foreach ($jr['res'] as $r):
                    $heure = heure_res($r);
                    $cls   = classe_statut_reservation($r['statut_reservation']) . (est_annulee($r) ? ' is-cancelled' : '');
                ?>
                    <div class="res <?= $cls ?>" role="button" tabindex="0"
                         data-num="<?= (int)$r['num_reservation'] ?>"
                         data-client="<?= h($r['prenom'] . ' ' . $r['nom']) ?>"
                         data-jeux="<?= h($r['jeux']) ?>"
                         data-date="<?= h(date('d/m/Y', strtotime($r['date_visite'])) . ($heure ? ' à ' . $heure : '')) ?>"
                         data-pers="<?= (int)$r['nb_personnes'] ?>"
                         data-montant="<?= h(number_format($r['montant'], 2)) ?> DH"
                         data-statut="<?= h(ucfirst_fr($r['statut_reservation'])) ?>">
                        <span class="res-jeux"><?= h($r['jeux']) ?></span>
                        <span class="res-meta">👥 <?= (int)$r['nb_personnes'] ?> pers.<?= $heure ? ' · ' . h($heure) : '' ?></span>
                        <span class="res-meta"><?= h($r['prenom'] . ' ' . $r['nom']) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if (!$jr['res']): ?><div class="day-empty">—</div><?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pers_semaine > 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <h3 class="chart-title">Charge de la semaine (personnes attendues par jour)</h3>
                <div class="chart-box chart-box--sm"><canvas id="chCharge"></canvas></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>

    <!-- ===== Vue liste ===== -->
    <div class="row">
        <div class="col-12">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date de visite</th>
                            <th>Jeu(x)</th>
                            <th>Client</th>
                            <th>Personnes</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avenir as $r): $heure = heure_res($r); ?>
                        <tr>
                            <td><?= (int)$r['num_reservation'] ?></td>
                            <td><?= h(date('d/m/Y', strtotime($r['date_visite']))) ?><?= $heure ? ' ' . h($heure) : '' ?></td>
                            <td><?= h($r['jeux']) ?></td>
                            <td><?= h($r['prenom'] . ' ' . $r['nom']) ?></td>
                            <td><?= (int)$r['nb_personnes'] ?></td>
                            <td class="num"><?= number_format($r['montant'], 2) ?> DH</td>
                            <td><span class="badge <?= classe_statut_reservation($r['statut_reservation']) ?>"><?= h(ucfirst_fr($r['statut_reservation'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (!$avenir): ?>
                        <tr><td colspan="7" class="empty">Aucune réservation à venir pour ces critères.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- ===== Fenêtre de détail d'une réservation ===== -->
<dialog id="detail">
    <div class="sep-bottom">
        <h3>Réservation n° <span id="d-num"></span></h3>
        <span id="d-statut" class="badge"></span>
    </div>

    <div class="row dl-rows">
        <div class="col-4 dl-label">Client</div>         <div class="col-8" id="d-client"></div>
        <div class="col-4 dl-label">Jeu(x)</div>         <div class="col-8" id="d-jeux"></div>
        <div class="col-4 dl-label">Date de visite</div> <div class="col-8" id="d-date"></div>
        <div class="col-4 dl-label">Personnes</div>      <div class="col-8" id="d-pers"></div>
        <div class="col-4 dl-label">Montant</div>        <div class="col-8" id="d-montant"></div>
    </div>

    <div class="sep-top text-end">
        <form method="dialog"><button type="submit" class="btn btn-primary">Fermer</button></form>
    </div>
</dialog>

<script>
// ----- Bouton Filtrer -----
const btn = document.getElementById('btnFiltre');
const panneau = document.getElementById('panneauFiltre');
btn.addEventListener('click', () => {
  panneau.hidden = !panneau.hidden;
  btn.classList.toggle('is-open', !panneau.hidden);
});

// ----- Détail d'une réservation -----
const dlg = document.getElementById('detail');
function ouvrir(el) {
  const d = el.dataset;
  document.getElementById('d-num').textContent     = d.num;
  document.getElementById('d-client').textContent  = d.client;
  document.getElementById('d-jeux').textContent    = d.jeux;
  document.getElementById('d-date').textContent    = d.date;
  document.getElementById('d-pers').textContent    = d.pers;
  document.getElementById('d-montant').textContent = d.montant;
  const s = document.getElementById('d-statut');
  s.textContent = d.statut;
  s.className = 'badge ' + ([...el.classList].find(c => /^is-(ok|warn|danger|info|muted)$/.test(c)) || '');
  dlg.showModal();
}
document.querySelectorAll('.res').forEach(el => {
  el.addEventListener('click', () => ouvrir(el));
  el.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ouvrir(el); }
  });
});
dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
</script>

<?php if ($vue === 'agenda' && $pers_semaine > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const D = <?= $J($donnees) ?>;
if (typeof Chart === 'undefined') {
  document.querySelector('.chart-box').innerHTML =
    '<p class="empty">Graphique indisponible (connexion Internet requise).</p>';
} else {
  Chart.defaults.color = '#94a3b8';
  const ctx = document.getElementById('chCharge').getContext('2d');
  const g = ctx.createLinearGradient(0, 0, 0, 240);
  g.addColorStop(0, '#818cf8');
  g.addColorStop(1, '#4f46e5');
  new Chart(ctx, {
    type: 'bar',
    data: { labels: D.labels, datasets: [{ label: 'Personnes', data: D.valeurs, backgroundColor: g, borderRadius: 8, maxBarThickness: 48 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }
      }
    }
  });
}
</script>
<?php endif; ?>

<?php pied(); ?>
