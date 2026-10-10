<?php
// ============================================================
// COMMANDES : réservations validées converties + ventes directes au guichet
// Encaissement (espèces / carte) => commande « payée » => redirection vers Billet.php
// Les commandes dont l'échéance est dépassée sont annulées automatiquement (_garde.php).
// ============================================================
require_once __DIR__ . '/_garde.php';

const STATUTS_COMMANDE = ['en attente' => 'En attente', 'payée' => 'Payée', 'annulée' => 'Annulée'];

$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num  = filter_input(INPUT_POST, 'num_commande', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!csrf_ok('paiement')) {
        $erreur = "Le formulaire a expiré. Rechargez la page avant de réessayer.";
    } elseif (!$num) {
        $erreur = "Numéro de commande invalide.";
    } else {
        [$ok, $texte] = encaisser_commande($pdo, $num, $email_agent);
        if ($ok) {
            header('Location: Billet.php?id=' . $num . '&ok=1');
            exit;
        }
        $erreur = $texte;
        maj_automatique($pdo);
    }
}

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$statut = (string)($_GET['statut'] ?? '');
if (!isset(STATUTS_COMMANDE[$statut])) $statut = '';

$sql = "SELECT c.num_commande, c.date_commande, c.montant_total, c.statut_commande, c.mode_paiement,
               c.date_paiment, c.date_limite_paiement, c.num_reservation,
               u.nom, u.prenom, u.email, r.origine, r.date_visite, r.nb_personnes
        FROM commande c
        JOIN utilisateur u ON u.email = c.email_client
        LEFT JOIN reservation r ON r.num_reservation = c.num_reservation
        WHERE 1 = 1";
$p = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql .= " AND (c.num_commande = ? OR c.num_reservation = ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    $n = ctype_digit($q) ? (int)$q : -1;
    array_push($p, $n, $n, $like, $like, $like, $like);
}
if ($statut !== '') { $sql .= " AND c.statut_commande = ?"; $p[] = $statut; }
$sql .= " ORDER BY (c.statut_commande = 'en attente') DESC, c.date_commande DESC, c.num_commande DESC LIMIT 100";
$st = $pdo->prepare($sql);
$st->execute($p);
$commandes = $st->fetchAll();

entete("Commandes", false);
?>

<div class="row align-center justify-between">
    <div class="col-12 col-md">
        <h1 class="page-title">Commandes</h1>
        <p class="subtitle">Réservations en ligne validées et ventes directes au guichet. Le billet s'ouvre automatiquement après l'encaissement.</p>
    </div>
    <div class="col-12 col-md-auto"><a href="vente.php" class="btn btn-primary">➕ Nouvelle vente directe</a></div>
</div>

<?php if ($erreur): ?><div class="row"><div class="col-12"><div class="alert alert-danger"><?= h($erreur) ?></div></div></div><?php endif; ?>

<form method="get" class="row">
    <div class="col-12"><div class="filter-panel"><div class="row">
        <div class="col-12 col-lg-5"><label class="form-label" for="q">Recherche</label>
            <input type="search" name="q" id="q" class="form-control" value="<?= h($q) ?>" placeholder="N° commande / réservation, nom, e-mail, téléphone…"></div>
        <div class="col-12 col-lg-3"><label class="form-label" for="statut">Statut</label>
            <select name="statut" id="statut" class="form-control"><option value="">Tous</option>
                <?php foreach (STATUTS_COMMANDE as $k => $lib): ?><option value="<?= h($k) ?>" <?= $k === $statut ? 'selected' : '' ?>><?= h($lib) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-12 col-lg-4"><label class="form-label">&nbsp;</label>
            <div class="page-actions"><button class="btn btn-primary" type="submit">Rechercher</button><a href="commandes.php" class="btn btn-ghost">Réinitialiser</a></div></div>
    </div></div></div>
</form>

<div class="row"><div class="col-12"><div class="table-wrap">
<table class="table">
    <thead><tr>
        <th>N°</th><th>Source</th><th>Client</th><th>Visite</th><th>Échéance</th>
        <th>Payée le</th><th class="num">Montant</th><th>Statut</th><th>Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($commandes as $c):
        $s = $c['statut_commande'];
        $limite = $c['date_limite_paiement'] ?: ($c['date_visite'] ? fin_de_journee($c['date_visite']) : null);
        $classe = $s === 'payée' ? 'is-ok' : ($s === 'annulée' ? 'is-danger' : 'is-warn');
    ?>
        <tr>
            <td>#<?= (int)$c['num_commande'] ?></td>
            <td><?= $c['origine'] === 'en ligne' ? 'Réservation en ligne #' . (int)$c['num_reservation'] : 'Guichet' ?></td>
            <td><?= h(trim($c['prenom'] . ' ' . $c['nom'])) ?><br><small class="text-muted"><?= h($c['email']) ?></small></td>
            <td><?= h(date_fr($c['date_visite'] ?? $c['date_commande'])) ?></td>
            <td><?= $s === 'en attente' ? h(date_fr($limite, true)) : '—' ?></td>
            <td><?= $c['date_paiment'] ? h(date_fr($c['date_paiment'], true)) : '—' ?></td>
            <td class="num"><?= dh($c['montant_total']) ?></td>
            <td><span class="status <?= $classe ?>"><?= h(STATUTS_COMMANDE[$s] ?? ucfirst_fr($s)) ?></span></td>
            <td>
            <?php if ($s === 'en attente'): ?>
                <form method="post" class="page-actions">
                    <?= csrf_champ('paiement') ?>
                    <input type="hidden" name="num_commande" value="<?= (int)$c['num_commande'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Valider &amp; payer</button>
                </form>
            <?php elseif ($s === 'payée'): ?>
                <a class="btn btn-outline btn-sm" href="Billet.php?id=<?= (int)$c['num_commande'] ?>">🎟 Billet</a>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$commandes): ?><tr><td colspan="9" class="empty">Aucune commande.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div></div>

<?php pied(); ?>
