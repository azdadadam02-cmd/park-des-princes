<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


$recherche = trim($_GET['q'] ?? '');
$etat = trim($_GET['etat'] ?? '');

$id_jeu = filter_input(
    INPUT_GET,
    'jeu',
    FILTER_VALIDATE_INT
);


// ==========================
// Liste des jeux
// ==========================

$stmtJeux = $pdo->query("
    SELECT
        id_jeu,
        nom
    FROM jeu
    ORDER BY nom
");

$jeux =
    $stmtJeux->fetchAll(PDO::FETCH_ASSOC);


// ==========================
// Maintenances
// ==========================

$sql = "
    SELECT
        m.id_maintenance,
        m.type_intervention,
        m.description,
        m.date_intervention,
        m.cout,
        m.etat_intervention,
        m.date_prochaine_maintenance,
        m.id_jeu,
        m.email_responsable,

        j.nom AS jeu_nom,

        u.nom AS responsable_nom,
        u.prenom AS responsable_prenom

    FROM maintenance m

    INNER JOIN jeu j
        ON m.id_jeu = j.id_jeu

    LEFT JOIN utilisateur u
        ON m.email_responsable = u.email

    WHERE 1 = 1
";

$params = [];


// Recherche
if ($recherche !== '') {

    $sql .= "
        AND (
            m.type_intervention LIKE :recherche
            OR m.description LIKE :recherche
            OR j.nom LIKE :recherche
        )
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


// Filtre état
if ($etat !== '') {

    $sql .= "
        AND m.etat_intervention = :etat
    ";

    $params[':etat'] = $etat;
}


// Filtre jeu
if ($id_jeu) {

    $sql .= "
        AND m.id_jeu = :jeu
    ";

    $params[':jeu'] = $id_jeu;
}


$sql .= "
    ORDER BY
        m.date_intervention DESC,
        m.id_maintenance DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$maintenances =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Maintenance</title>

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

            <h1>Maintenance</h1>

            <p>
                Suivi des opérations de maintenance
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
            placeholder="Jeu, type, description..."
            value="<?= htmlspecialchars($recherche) ?>"
        >


        <select name="etat">

            <option value="">
                Tous les états
            </option>

            <option
                value="planifiée"
                <?= $etat === 'planifiée'
                    ? 'selected'
                    : '' ?>
            >
                Planifiée
            </option>

            <option
                value="en cours"
                <?= $etat === 'en cours'
                    ? 'selected'
                    : '' ?>
            >
                En cours
            </option>

            <option
                value="terminée"
                <?= $etat === 'terminée'
                    ? 'selected'
                    : '' ?>
            >
                Terminée
            </option>

            <option
                value="annulée"
                <?= $etat === 'annulée'
                    ? 'selected'
                    : '' ?>
            >
                Annulée
            </option>

        </select>


        <select name="jeu">

            <option value="">
                Tous les jeux
            </option>

            <?php foreach ($jeux as $jeu): ?>

                <option
                    value="<?= $jeu['id_jeu'] ?>"

                    <?= $id_jeu == $jeu['id_jeu']
                        ? 'selected'
                        : '' ?>
                >

                    <?= htmlspecialchars(
                        $jeu['nom']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


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

                    <th>ID</th>

                    <th>Jeu</th>

                    <th>Intervention</th>

                    <th>Date</th>

                    <th>Coût</th>

                    <th>Responsable</th>

                    <th>État</th>

                    <th>Prochaine</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($maintenances) > 0): ?>


                <?php foreach ($maintenances as $maintenance): ?>

                    <tr>

                        <td>
                            <?= $maintenance['id_maintenance'] ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $maintenance['jeu_nom']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $maintenance['type_intervention']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $maintenance['date_intervention']
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $maintenance['cout'],
                                2,
                                ',',
                                ' '
                            ) ?>

                            DH

                        </td>


                        <td>

                            <?php
                            if ($maintenance['responsable_nom']):
                            ?>

                                <?= htmlspecialchars(
                                    $maintenance['responsable_prenom']
                                    . ' '
                                    . $maintenance['responsable_nom']
                                ) ?>

                            <?php else: ?>

                                Non affecté

                            <?php endif; ?>

                        </td>


                        <td>

                            <span class="status-badge">

                                <?= htmlspecialchars(
                                    $maintenance['etat_intervention']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $maintenance['date_prochaine_maintenance']
                                ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <a
                                href="details.php?id=<?= $maintenance['id_maintenance'] ?>"
                                class="btn-view"
                            >
                                Voir
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td colspan="9">

                        Aucune opération de maintenance.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>

</html>