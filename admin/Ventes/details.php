<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? '';

if ($id === '') {
    die("Commande invalide.");
}


/* commande */

$stmt = $pdo->prepare("
    SELECT
        c.*,

        u.nom,
        u.prenom,
        u.email,
        u.telephone,

        ua.nom AS agent_nom,
        ua.prenom AS agent_prenom

    FROM commande c

    INNER JOIN utilisateur u
        ON c.email_client = u.email

    LEFT JOIN utilisateur ua
        ON c.email_agent = ua.email

    WHERE c.num_commande = :id
");

$stmt->execute([
    ':id' => $id
]);

$commande =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$commande) {
    die("Commande introuvable.");
}


/* billets de cette commande */

$stmt = $pdo->prepare("
    SELECT *
    FROM billet
    WHERE num_commande = :id
    ORDER BY id_billet DESC
");

$stmt->execute([
    ':id' => $id
]);

$billets =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Détails commande</title>

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main>

    <div class="page-header">

        <div>

            <h1>
                Commande #<?= htmlspecialchars(
                    $commande['num_commande']
                ) ?>
            </h1>

            <p>Détails de la vente</p>

        </div>


        <a
            href="index.php"
            class="btn-secondary"
        >
            Retour
        </a>

    </div>


    <div class="details-box">


        <div class="detail-item">

            <span>Client</span>

            <strong>

                <?= htmlspecialchars(
                    $commande['prenom']
                    . ' '
                    . $commande['nom']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Email</span>

            <strong>
                <?= htmlspecialchars($commande['email']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Date commande</span>

            <strong>
                <?= htmlspecialchars($commande['date_commande']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Montant total</span>

            <strong>

                <?= number_format(
                    $commande['montant_total'],
                    2,
                    ',',
                    ' '
                ) ?>

                DH

            </strong>

        </div>


        <div class="detail-item">

            <span>Mode paiement</span>

            <strong>
                <?= htmlspecialchars($commande['mode_paiement']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Statut</span>

            <strong>
                <?= htmlspecialchars($commande['statut_commande']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Agent de vente</span>

            <strong>

                <?php if ($commande['email_agent']): ?>

                    <?= htmlspecialchars(
                        $commande['agent_prenom']
                        . ' '
                        . $commande['agent_nom']
                    ) ?>

                <?php else: ?>

                    Non affecté

                <?php endif; ?>

            </strong>

        </div>

    </div>


    <br>


    <div class="table-box">

        <h2>Billets de la commande</h2>

        <br>


        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Date fin</th>
                    <th>Statut</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($billets) > 0): ?>

                <?php foreach ($billets as $billet): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($billet['id_billet']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($billet['type']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($billet['quantite']) ?>
                        </td>

                        <td>

                            <?= number_format(
                                $billet['prix_unitaire'],
                                2,
                                ',',
                                ' '
                            ) ?>

                            DH

                        </td>

                        <td>
                            <?= htmlspecialchars($billet['date_fin']) ?>
                        </td>

                        <td>

                            <span class="status-badge">

                                <?= htmlspecialchars(
                                    $billet['statut_billet']
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="6">
                        Aucun billet dans cette commande.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>