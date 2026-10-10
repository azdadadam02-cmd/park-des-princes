<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? '';

if ($id === '') {
    die("Billet invalide.");
}


$sql = "
    SELECT
        b.*,

        u.nom AS agent_nom,
        u.prenom AS agent_prenom,

        c.email_client,
        c.montant_total,
        c.mode_paiement,
        c.statut_commande

    FROM billet b

    LEFT JOIN utilisateur u
        ON b.email_agent = u.email

    INNER JOIN commande c
        ON b.num_commande = c.num_commande

    WHERE b.id_billet = :id
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$billet =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$billet) {
    die("Billet introuvable.");
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Détails billet</title>

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
                Billet #<?= htmlspecialchars($billet['id_billet']) ?>
            </h1>

            <p>
                Informations du billet
            </p>

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

            <span>Type</span>

            <strong>
                <?= htmlspecialchars($billet['type']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Quantité</span>

            <strong>
                <?= htmlspecialchars($billet['quantite']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Prix unitaire</span>

            <strong>

                <?= number_format(
                    $billet['prix_unitaire'],
                    2,
                    ',',
                    ' '
                ) ?>

                DH

            </strong>

        </div>


        <div class="detail-item">

            <span>Statut billet</span>

            <strong>
                <?= htmlspecialchars($billet['statut_billet']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Date création</span>

            <strong>
                <?= htmlspecialchars($billet['date_creation']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Date fin</span>

            <strong>
                <?= htmlspecialchars($billet['date_fin']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Commande</span>

            <strong>
                #<?= htmlspecialchars($billet['num_commande']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Client</span>

            <strong>
                <?= htmlspecialchars($billet['email_client']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Paiement</span>

            <strong>
                <?= htmlspecialchars($billet['mode_paiement']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Agent</span>

            <strong>

                <?php if ($billet['email_agent']): ?>

                    <?= htmlspecialchars(
                        $billet['agent_prenom']
                        . ' '
                        . $billet['agent_nom']
                    ) ?>

                <?php else: ?>

                    Non validé

                <?php endif; ?>

            </strong>

        </div>


    </div>

</main>

</body>
</html>