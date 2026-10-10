<?php
// ============================================================
// BILLET : document imprimable d'une commande payée
// (infos client, date de visite, jeux, un billet par personne avec son code)
// ============================================================
require_once __DIR__ . '/_garde.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$cmd = null;
if ($id) {
    $st = $pdo->prepare(
        "SELECT c.*, u.nom, u.prenom, u.telephone, u.email, r.date_visite, r.nb_personnes, r.origine
         FROM commande c
         JOIN utilisateur u ON u.email = c.email_client
         LEFT JOIN reservation r ON r.num_reservation = c.num_reservation
         WHERE c.num_commande = ? AND c.statut_commande = 'payée'"
    );
    $st->execute([$id]);
    $cmd = $st->fetch() ?: null;
}

if (!$cmd) {
    http_response_code(404);
    entete("Billet introuvable", false);
    echo '<div class="row"><div class="col-12"><div class="empty-state"><span class="empty-icon">🎟</span>'
       . '<h3>Billet indisponible</h3><p>Cette commande n\'existe pas ou n\'est pas encore payée.</p>'
       . '<a class="btn btn-primary" href="commandes.php">Retour aux commandes</a></div></div></div>';
    pied();
    exit;
}

$st = $pdo->prepare(
    "SELECT b.id_billet, b.type, b.prix_unitaire, b.date_creation, b.date_fin, b.statut_billet,
            (SELECT GROUP_CONCAT(j.nom ORDER BY j.nom SEPARATOR ', ')
             FROM contient ct JOIN jeu j ON j.id_jeu = ct.id_jeu
             WHERE ct.id_billet = b.id_billet) AS jeux
     FROM billet b WHERE b.num_commande = ? ORDER BY b.id_billet"
);
$st->execute([$cmd['num_commande']]);
$billets = $st->fetchAll();

$est_passage = ($cmd['email_client'] === EMAIL_VISITEUR);
$nom_client  = $est_passage ? 'Visiteur de passage' : trim($cmd['prenom'] . ' ' . $cmd['nom']);
$date_visite = $cmd['date_visite'] ?: ($billets[0]['date_creation'] ?? $cmd['date_commande']);

entete("Billet — commande n° " . $cmd['num_commande'], false);
?>

<?php if (!empty($_GET['ok'])): ?>
<div class="row no-print"><div class="col-12">
    <div class="alert alert-success">✅ Paiement enregistré. Imprimez le billet et remettez-le au client.</div>
</div></div>
<?php endif; ?>

<div class="row justify-center">
    <div class="col-12 col-lg-9">
        <div class="card card-lg">

            <div class="row align-center justify-between">
                <div class="col-12 col-md">
                    <h1 class="page-title">Billet — commande n° <?= (int)$cmd['num_commande'] ?></h1>
                    <p class="subtitle">Parc des Princes — payé le <?= h(date_fr($cmd['date_paiment'] ?: $cmd['date_commande'], true)) ?></p>
                </div>
                <div class="col-12 col-md-auto"><span class="status is-ok">Payée</span></div>
            </div>

            <div class="row">
                <div class="col-12 col-md-3"><div class="box"><span class="box-label">Client</span><span class="box-value"><?= h($nom_client) ?></span></div></div>
                <div class="col-12 col-md-3"><div class="box"><span class="box-label">Téléphone</span><span class="box-value"><?= h($cmd['telephone'] ?: '—') ?></span></div></div>
                <div class="col-12 col-md-3"><div class="box"><span class="box-label">Date de visite</span><span class="box-value"><?= h(date_fr($date_visite)) ?></span></div></div>
                <div class="col-12 col-md-3"><div class="box"><span class="box-label">Payée le</span><span class="box-value"><?= h(date_fr($cmd['date_paiment'] ?? null, true)) ?></span></div></div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Code billet</th><th>Type</th><th>Valable jusqu'au</th><th>Jeux</th><th class="num">Prix</th></tr></thead>
                            <tbody>
                            <?php foreach ($billets as $b): ?>
                                <tr>
                                    <td class="code-billet"><?= h(code_billet((int)$b['id_billet'])) ?></td>
                                    <td><?= h($b['type']) ?></td>
                                    <td><?= h(date_fr($b['date_fin'], true)) ?></td>
                                    <td><?= h($b['jeux'] ?: 'Tous les jeux') ?></td>
                                    <td class="num"><?= dh($b['prix_unitaire']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr>
                                <td colspan="4">Total payé (<?= count($billets) ?> billet<?= count($billets) > 1 ? 's' : '' ?>)</td>
                                <td class="num"><?= dh($cmd['montant_total']) ?></td>
                            </tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row no-print"><div class="col-12"><div class="page-actions">
                <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Imprimer</button>
                <a href="vente.php" class="btn btn-primary">Nouvelle vente</a>
                <a href="commandes.php" class="btn btn-ghost">Commandes</a>
            </div></div></div>

        </div>
    </div>
</div>

<?php pied(); ?>
