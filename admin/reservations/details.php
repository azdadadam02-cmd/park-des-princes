<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? '';

if ($id === '') {
    die("Réservation invalide.");
}


$sql = "
    SELECT
        r.*,

        u.nom,
        u.prenom,
        u.email,
        u.telephone,

        GROUP_CONCAT(
            DISTINCT j.nom
            SEPARATOR ', '
        ) AS jeux

    FROM reservation r

    INNER JOIN client c
        ON r.email_client = c.email

    INNER JOIN utilisateur u
        ON c.email = u.email

    LEFT JOIN choisir ch
        ON r.num_reservation = ch.num_reservation

    LEFT JOIN jeu j
        ON ch.id_jeu = j.id_jeu

    WHERE r.num_reservation = :id

    GROUP BY
        r.num_reservation,
        r.date_creation,
        r.date_visite,
        r.nb_personnes,
        r.montant,
        r.statut_reservation,
        r.email_client,
        r.email_agent,
        u.nom,
        u.prenom,
        u.email,
        u.telephone
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$reservation =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$reservation) {

    die(
        "Réservation introuvable."
    );
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>
        Détails réservation
    </title>

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
                Réservation
                #<?= htmlspecialchars(
                    $reservation['num_reservation']
                ) ?>
            </h1>

            <p>
                Informations complètes
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

            <span>Client</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['prenom']
                    . ' '
                    . $reservation['nom']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Email</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['email']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Téléphone</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['telephone']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Date création</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['date_creation']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Date visite</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['date_visite']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Nombre de personnes</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['nb_personnes']
                ) ?>

            </strong>

        </div>


        <div class="detail-item">

            <span>Montant</span>

            <strong>

                <?= number_format(
                    $reservation['montant'],
                    2,
                    ',',
                    ' '
                ) ?>

                DH

            </strong>

        </div>


        <div class="detail-item">

            <span>Statut</span>

            <strong>

                <?= htmlspecialchars(
                    $reservation['statut_reservation']
                ) ?>

            </strong>

        </div>


        <div class="detail-description">

            <span>Jeux réservés</span>

            <p>

                <?= htmlspecialchars(
                    $reservation['jeux']
                    ?? 'Aucun jeu'
                ) ?>

            </p>

        </div>


    </div>


</main>

</body>

</html>