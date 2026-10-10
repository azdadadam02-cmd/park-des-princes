<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$id) {
    die("Maintenance invalide.");
}


$sql = "
    SELECT
        m.*,

        j.nom AS jeu_nom,

        u.nom AS responsable_nom,
        u.prenom AS responsable_prenom,
        u.telephone AS responsable_telephone

    FROM maintenance m

    INNER JOIN jeu j
        ON m.id_jeu = j.id_jeu

    LEFT JOIN utilisateur u
        ON m.email_responsable = u.email

    WHERE m.id_maintenance = :id
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);


$maintenance =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$maintenance) {

    die(
        "Opération de maintenance introuvable."
    );
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Détails maintenance</title>

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
                Maintenance #<?= $maintenance['id_maintenance'] ?>
            </h1>

            <p>
                Détails de l'intervention
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

            <span>Jeu</span>

            <strong>

                <?= htmlspecialchars(
                    $maintenance['jeu_nom']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Type d'intervention</span>

            <strong>

                <?= htmlspecialchars(
                    $maintenance['type_intervention']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Date intervention</span>

            <strong>

                <?= htmlspecialchars(
                    $maintenance['date_intervention']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Coût</span>

            <strong>

                <?= number_format(
                    $maintenance['cout'],
                    2,
                    ',',
                    ' '
                ) ?>

                DH

            </strong>

        </div>


        <div class="detail-item">

            <span>État</span>

            <strong>

                <?= htmlspecialchars(
                    $maintenance['etat_intervention']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Prochaine maintenance</span>

            <strong>

                <?= htmlspecialchars(
                    $maintenance['date_prochaine_maintenance']
                    ?? 'Non définie'
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Responsable</span>

            <strong>

                <?php if ($maintenance['responsable_nom']): ?>

                    <?= htmlspecialchars(
                        $maintenance['responsable_prenom']
                        . ' '
                        . $maintenance['responsable_nom']
                    ) ?>

                <?php else: ?>

                    Non affecté

                <?php endif; ?>

            </strong>

        </div>


        <div class="detail-description">

            <span>Description</span>

            <p>

                <?= nl2br(
                    htmlspecialchars(
                        $maintenance['description']
                    )
                ) ?>

            </p>

        </div>


    </div>

</main>

</body>

</html>