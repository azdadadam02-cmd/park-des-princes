<?php
// ============================================================
// LISTE DES BILLETS (lecture seule) : recherche et réimpression
// ============================================================
require_once __DIR__ . '/_garde.php';

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$statut = (string)($_GET['statut'] ?? '');
if (!in_array($statut, ['valide', 'en attente', 'utilisé', 'annulé'], true)) $statut = '';

$sql = "SELECT b.id_billet, b.type, b.prix_unitaire, b.statut_billet, b.date_creation, b.date_fin,
               c.num_commande, c.statut_commande, u.nom, u.prenom, u.email
        FROM billet b
        JOIN commande c ON c.num_commande = b.num_commande
        LEFT JOIN utilisateur u ON u.email = c.email_client
        WHERE 1 = 1";
$p = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $id_code = id_depuis_code($q);
    $sql .= " AND (b.id_billet = ? OR c.num_commande = ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?
                   OR CONCAT(u.prenom, ' ', u.nom) LIKE ?)";
    array_push($p, $id_code ?? (ctype_digit($q) ? (int)$q : -1), ctype_digit($q) ? (int)$q : -1, $like, $like, $like, $like);
}
if ($statut !== '') { $sql .= " AND b.statut_billet = ?"; $p[] = $statut; }
$sql .= " ORDER BY b.id_billet DESC LIMIT 100";

$st = $pdo->prepare($sql);
$st->execute($p);
$billets = $st->fetchAll();

entete("Billets", false);
?>

<div class="row">
    <div class="col-12">
        <h1 class="page-title">Billets</h1>
        <p class="subtitle">Tous les billets générés. Recherche par code (PP-000042), n° de commande, nom ou e-mail du client.</p>
    </div>
</div>

<form method="get" class="row">
    <div class="col-12"><div class="filter-panel"><div class="row">
        <div class="col-12 col-lg-5"><label class="form-label" for="q">Recherche</label>
            <input type="search" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Code, n° commande, nom, e-mail…"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="statut">Statut</label>
            <select id="statut" name="statut" class="form-control">
                <option value="">Tous</option>
                <?php foreach (['valide', 'en attente', 'utilisé', 'annulé'] as $s): ?>
                    <option value="<?= h($s) ?>" <?= $s === $statut ? 'selected' : '' ?>><?= h(ucfirst_fr($s)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-12 col-sm-6 col-lg-4"><label class="form-label">&nbsp;</label>
            <div class="page-actions">
                <button class="btn btn-primary" type="submit">Rechercher</button>
                <a href="tous_billets.php" class="btn btn-ghost">Réinitialiser</a>
                <span class="text-muted"><?= count($billets) ?> résultat<?= count($billets) > 1 ? 's' : '' ?><?= count($billets) === 100 ? ' (limité à 100)' : '' ?></span>
            </div></div>
    </div></div></div>
</form>

<div class="row">
    <div class="col-12">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Type</th><th>Client</th><th>Commande</th><th>Valable</th><th>Statut</th><th class="num">Prix</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($billets as $b): ?>
                    <?php $payee = mb_strtolower((string)$b['statut_commande']) === 'payée'; ?>
                    <tr>
                        <td><?= h(code_billet((int)$b['id_billet'])) ?></td>
                        <td><?= h($b['type']) ?></td>
                        <td><?= h(trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? '')) ?: '—') ?></td>
                        <td>#<?= (int)$b['num_commande'] ?> <span class="text-muted">(<?= h($b['statut_commande']) ?>)</span></td>
                        <td><?= h(date_fr($b['date_creation'])) ?> → <?= h(date_fr($b['date_fin'])) ?></td>
                        <td><span class="status <?= classe_statut_reservation($b['statut_billet'] === 'valide' ? 'confirmée' : ($b['statut_billet'] === 'utilisé' ? 'utilisée' : ($b['statut_billet'] === 'annulé' ? 'annulée' : 'en attente'))) ?>"><?= h(ucfirst_fr((string)$b['statut_billet'])) ?></span></td>
                        <td class="num"><?= dh($b['prix_unitaire']) ?></td>
                        <td><?php if ($payee): ?><a class="btn btn-ghost" href="Billet.php?id=<?= (int)$b['num_commande'] ?>">Réimprimer</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$billets): ?><tr><td colspan="8" class="empty">Aucun billet pour ces critères.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php pied(); ?>
