<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$recherche = trim($_GET['q'] ?? '');
$statut = trim($_GET['statut'] ?? '');
$date = trim($_GET['date'] ?? '');


$sql = "
    SELECT
        r.num_reservation,
        r.date_creation,
        r.date_visite,
        r.nb_personnes,
        r.montant,
        r.statut_reservation,

        u.nom,
        u.prenom,
        u.email,

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

    WHERE 1 = 1
";

$params = [];


/* Recherche client */

if ($recherche !== '') {

    $sql .= "
        AND (
            u.nom LIKE :recherche
            OR u.prenom LIKE :recherche
            OR u.email LIKE :recherche
            OR r.num_reservation LIKE :recherche
        )
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


/* Filtre statut */

if ($statut !== '') {

    $sql .= "
        AND r.statut_reservation = :statut
    ";

    $params[':statut'] = $statut;
}


/* Filtre date visite */

if ($date !== '') {

    $sql .= "
        AND r.date_visite = :date
    ";

    $params[':date'] = $date;
}


$sql .= "
    GROUP BY
        r.num_reservation,
        r.date_creation,
        r.date_visite,
        r.nb_personnes,
        r.montant,
        r.statut_reservation,
        u.nom,
        u.prenom,
        u.email

    ORDER BY r.date_creation DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$reservations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Réservations</title>

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

            <h1>Gestion des réservations</h1>

            <p>
                Consulter les réservations du parc
            </p>

        </div>

    </div>


    <!-- FILTRES -->

    <form
        method="GET"
        class="filters-box"
    >

        <input
            type="text"
            name="q"
            placeholder="Client, email ou numéro..."
            value="<?= htmlspecialchars($recherche) ?>"
        >


        <select name="statut">

            <option value="">
                Tous les statuts
            </option>

            <option
                value="en attente"
                <?= $statut === 'en attente' ? 'selected' : '' ?>
            >
                En attente
            </option>

            <option
                value="confirmée"
                <?= $statut === 'confirmée' ? 'selected' : '' ?>
            >
                Confirmée
            </option>

            <option
                value="payée"
                <?= $statut === 'payée' ? 'selected' : '' ?>
            >
                Payée
            </option>

            <option
                value="utilisée"
                <?= $statut === 'utilisée' ? 'selected' : '' ?>
            >
                Utilisée
            </option>

            <option
                value="annulée"
                <?= $statut === 'annulée' ? 'selected' : '' ?>
            >
                Annulée
            </option>

        </select>


        <input
            type="date"
            name="date"
            value="<?= htmlspecialchars($date) ?>"
        >


        <button
            type="submit"
            class="btn-primary"
        >
            Rechercher
        </button>


        <a
            href="index.php"
            class="btn-secondary"
        >
            Réinitialiser
        </a>

    </form>


    <!-- TABLE -->

    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>N°</th>

                    <th>Client</th>

                    <th>Date visite</th>

                    <th>Personnes</th>

                    <th>Jeux</th>

                    <th>Montant</th>

                    <th>Statut</th>

                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($reservations) > 0): ?>


                <?php foreach ($reservations as $reservation): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                $reservation['num_reservation']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $reservation['prenom']
                                . ' '
                                . $reservation['nom']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $reservation['date_visite']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $reservation['nb_personnes']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $reservation['jeux']
                                ?? 'Aucun'
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $reservation['montant'],
                                2,
                                ',',
                                ' '
                            ) ?>

                            DH

                        </td>


                        <td>

                            <span class="status-badge">

                                <?= htmlspecialchars(
                                    $reservation['statut_reservation']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <a
                                href="details.php?id=<?= urlencode($reservation['num_reservation']) ?>"
                                class="btn-view"
                            >
                                Voir
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td colspan="8">

                        Aucune réservation trouvée.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>

</html>