<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$recherche = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$date = trim($_GET['date'] ?? '');

$id_jeu = filter_input(
    INPUT_GET,
    'jeu',
    FILTER_VALIDATE_INT
);


// ===============================
// Jeux pour filtre
// ===============================

$stmtJeux = $pdo->query("
    SELECT id_jeu, nom
    FROM jeu
    ORDER BY nom
");

$jeux = $stmtJeux->fetchAll(PDO::FETCH_ASSOC);


// ===============================
// Dépenses
// ===============================

$sql = "
    SELECT
        d.id_depense,
        d.categorie_depense,
        d.description_depense,
        d.montant_depense,
        d.date_depense,
        d.type_depense,
        d.fichier_justificatif,
        d.id_jeu,
        d.email_utilisateur,

        j.nom AS jeu_nom,

        u.nom AS utilisateur_nom,
        u.prenom AS utilisateur_prenom

    FROM depense d

    LEFT JOIN jeu j
        ON d.id_jeu = j.id_jeu

    LEFT JOIN utilisateur u
        ON d.email_utilisateur = u.email

    WHERE 1 = 1
";

$params = [];


// Recherche
if ($recherche !== '') {

    $sql .= "
        AND (
            d.categorie_depense LIKE :recherche
            OR d.description_depense LIKE :recherche
            OR d.email_utilisateur LIKE :recherche
        )
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


// Type
if ($type !== '') {

    $sql .= "
        AND d.type_depense = :type
    ";

    $params[':type'] = $type;
}


// Date
if ($date !== '') {

    $sql .= "
        AND d.date_depense = :date
    ";

    $params[':date'] = $date;
}


// Jeu
if ($id_jeu) {

    $sql .= "
        AND d.id_jeu = :jeu
    ";

    $params[':jeu'] = $id_jeu;
}


$sql .= "
    ORDER BY d.date_depense DESC,
             d.id_depense DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$depenses =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Dépenses</title>

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

            <h1>Gestion des dépenses</h1>

            <p>
                Consulter les dépenses du parc
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
            placeholder="Catégorie, description..."
            value="<?= htmlspecialchars($recherche) ?>"
        >


        <select name="type">

            <option value="">
                Tous les types
            </option>

            <option
                value="jeu"
                <?= $type === 'jeu' ? 'selected' : '' ?>
            >
                Liée à un jeu
            </option>

            <option
                value="general"
                <?= $type === 'general' ? 'selected' : '' ?>
            >
                Frais généraux
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

                    <?= htmlspecialchars($jeu['nom']) ?>

                </option>

            <?php endforeach; ?>

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

                    <th>ID</th>
                    <th>Catégorie</th>
                    <th>Type</th>
                    <th>Jeu</th>
                    <th>Montant</th>
                    <th>Date</th>
                    <th>Saisi par</th>
                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($depenses) > 0): ?>

                <?php foreach ($depenses as $depense): ?>

                    <tr>

                        <td>
                            <?= $depense['id_depense'] ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $depense['categorie_depense']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $depense['type_depense']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $depense['jeu_nom']
                                ?? 'Frais général'
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $depense['montant_depense'],
                                2,
                                ',',
                                ' '
                            ) ?>

                            DH

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $depense['date_depense']
                            ) ?>

                        </td>


                        <td>

                            <?php
                            if ($depense['utilisateur_nom']):
                            ?>

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

                        </td>


                        <td>

                            <a
                                href="details.php?id=<?= $depense['id_depense'] ?>"
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
                        Aucune dépense trouvée.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>