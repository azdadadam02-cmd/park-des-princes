<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    die("Dépense invalide.");
}


$sql = "
    SELECT
        d.*,

        j.nom AS jeu_nom,

        u.nom AS utilisateur_nom,
        u.prenom AS utilisateur_prenom

    FROM depense d

    LEFT JOIN jeu j
        ON d.id_jeu = j.id_jeu

    LEFT JOIN utilisateur u
        ON d.email_utilisateur = u.email

    WHERE d.id_depense = :id
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$depense =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$depense) {
    die("Dépense introuvable.");
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Détails dépense</title>

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
                Dépense #<?= $depense['id_depense'] ?>
            </h1>

            <p>
                Informations de la dépense
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

            <span>Catégorie</span>

            <strong>

                <?= htmlspecialchars(
                    $depense['categorie_depense']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Type</span>

            <strong>

                <?= htmlspecialchars(
                    $depense['type_depense']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Montant</span>

            <strong>

                <?= number_format(
                    $depense['montant_depense'],
                    2,
                    ',',
                    ' '
                ) ?>

                DH

            </strong>

        </div>


        <div class="detail-item">

            <span>Date</span>

            <strong>

                <?= htmlspecialchars(
                    $depense['date_depense']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Jeu concerné</span>

            <strong>

                <?= htmlspecialchars(
                    $depense['jeu_nom']
                    ?? 'Aucun - Frais général'
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Saisi par</span>

            <strong>

                <?php if ($depense['utilisateur_nom']): ?>

                    <?= htmlspecialchars(
                        $depense['utilisateur_prenom']
                        . ' '
                        . $depense['utilisateur_nom']
                    ) ?>

                <?php else: ?>

                    <?= htmlspecialchars(
                        $depense['email_utilisateur']
                    ) ?>

                <?php endif; ?>

            </strong>

        </div>


        <div class="detail-description">

            <span>Description</span>

            <p>

                <?= nl2br(
                    htmlspecialchars(
                        $depense['description_depense']
                    )
                ) ?>

            </p>

        </div>


        <div class="detail-description">

            <span>Justificatif</span>

            <p>

                <?php
                if (!empty($depense['fichier_justificatif'])):
                ?>

                    <?= htmlspecialchars(
                        $depense['fichier_justificatif']
                    ) ?>

                <?php else: ?>

                    Aucun justificatif

                <?php endif; ?>

            </p>

        </div>


    </div>

</main>

</body>
</html>