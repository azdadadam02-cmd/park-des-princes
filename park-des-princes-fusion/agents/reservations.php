<?php
// ============================================================
// RÉSERVATIONS EN LIGNE
// Validation (jeux Indisponible + commande créée) ou annulation (jeux libérés).
// Le passage en « Terminée » est automatique (voir maj_automatique dans _garde.php).
// ============================================================
require_once __DIR__ . '/_garde.php';

const LIBELLES_STATUT = [
    'en attente' => 'En attente',
    'confirmée'  => 'Validée',
    'payée'      => 'Payée',
    'utilisée'   => 'Terminée',
    'annulée'    => 'Annulée',
];

$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num    = filter_input(INPUT_POST, 'num_reservation', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok('reservation')) {
        $erreur = "Le formulaire a expiré. Rechargez la page avant de réessayer.";
    } elseif (!$num || !in_array($action, ['valider', 'annuler'], true)) {
        $erreur = "Demande invalide.";
    } else {
        [$ok, $texte] = $action === 'valider'
            ? valider_reservation($pdo, $num, $email_agent)
            : annuler_reservation($pdo, $num);
        $ok ? $message = $texte : $erreur = $texte;
    }
}

/* ---------- Filtres ---------- */
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$statut = (string)($_GET['statut'] ?? '');
if (!isset(LIBELLES_STATUT[$statut])) $statut = '';

$sql = "SELECT r.num_reservation, r.date_creation, r.date_visite, r.nb_personnes, r.montant, r.statut_reservation,
               u.nom, u.prenom, u.email, u.telephone,
               GROUP_CONCAT(DISTINCT j.nom ORDER BY j.nom SEPARATOR ', ') AS jeux
        FROM reservation r
        JOIN utilisateur u ON u.email = r.email_client
        LEFT JOIN choisir ch ON ch.num_reservation = r.num_reservation
        LEFT JOIN jeu j ON j.id_jeu = ch.id_jeu
        WHERE r.origine = 'en ligne'";
$p = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql .= " AND (r.num_reservation = ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    array_push($p, ctype_digit($q) ? (int)$q : -1, $like, $like, $like, $like);
}
if ($statut !== '') { $sql .= " AND r.statut_reservation = ?"; $p[] = $statut; }
$sql .= " GROUP BY r.num_reservation, r.date_creation, r.date_visite, r.nb_personnes, r.montant,
                   r.statut_reservation, u.nom, u.prenom, u.email, u.telephone
          ORDER BY (r.statut_reservation = 'en attente') DESC, r.date_visite ASC, r.num_reservation DESC
          LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($p);
$reservations = $st->fetchAll();

entete("Réservations en ligne", false);
?>

<div class="row">
    <div class="col-12">
        <h1 class="page-title">Réservations en ligne</h1>
        <p class="subtitle">Vérifiez les informations du client puis validez ou annulez la demande. Une réservation validée bloque ses jeux jusqu'au jour de la visite inclus et crée une commande.</p>
    </div>
</div>

<?php if ($message): ?><div class="row"><div class="col-12"><div class="alert alert-success"><?= h($message) ?></div></div></div><?php endif; ?>
<?php if ($erreur):  ?><div class="row"><div class="col-12"><div class="alert alert-danger"><?= h($erreur) ?></div></div></div><?php endif; ?>

<form method="get" class="row">
    <div class="col-12">
        <div class="filter-panel">
            <div class="row">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="f-q">Recherche</label>
                    <input type="search" name="q" id="f-q" class="form-control" value="<?= h($q) ?>" placeholder="N°, nom, e-mail, téléphone…">
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="f-statut">Statut</label>
                    <select name="statut" id="f-statut" class="form-control">
                        <option value="">Tous</option>
                        <?php foreach (LIBELLES_STATUT as $k => $lib): ?>
                            <option value="<?= h($k) ?>" <?= $k === $statut ? 'selected' : '' ?>><?= h($lib) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                        <a href="reservations.php" class="btn btn-ghost">Réinitialiser</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-12">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Jeux</th>
                        <th>Pers.</th>
                        <th>Visite</th>
                        <th class="num">Montant</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reservations as $r):
                    $infos_err = erreurs_infos_client($r);
                    $s = $r['statut_reservation'];
                    $passee = strtotime($r['date_visite']) < strtotime('today');
                ?>
                    <tr>
                        <td>#<?= (int)$r['num_reservation'] ?></td>
                        <td><?= h(trim($r['prenom'] . ' ' . $r['nom']) ?: '—') ?></td>
                        <td>
                            <?= h($r['telephone'] ?: '—') ?><br><small class="text-muted"><?= h($r['email']) ?></small>
                            <?php if ($s === 'en attente'): ?>
                                <br><?php if ($infos_err): ?>
                                    <small class="status is-danger">Infos à corriger : <?= h(implode(', ', $infos_err)) ?></small>
                                <?php else: ?>
                                    <small class="status is-ok">Infos vérifiées</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= h($r['jeux'] ?: '—') ?></td>
                        <td><?= (int)$r['nb_personnes'] ?></td>
                        <td><?= h(date_fr($r['date_visite'], true)) ?></td>
                        <td class="num"><?= dh($r['montant']) ?></td>
                        <td><span class="status <?= classe_statut_reservation($s) ?>"><?= h(LIBELLES_STATUT[$s] ?? ucfirst_fr($s)) ?></span></td>
                        <td>
                        <?php if (in_array($s, ['en attente', 'confirmée'], true)): ?>
                            <form method="post" class="page-actions">
                                <?= csrf_champ('reservation') ?>
                                <input type="hidden" name="num_reservation" value="<?= (int)$r['num_reservation'] ?>">
                                <?php if ($s === 'en attente' && !$passee && !$infos_err): ?>
                                    <button type="submit" name="action" value="valider" class="btn btn-primary btn-sm">Valider</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="annuler" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Annuler la réservation #<?= (int)$r['num_reservation'] ?> ?')">Annuler</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$reservations): ?>
                    <tr><td colspan="9" class="empty">Aucune réservation en ligne pour ces critères.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php pied(); ?>
