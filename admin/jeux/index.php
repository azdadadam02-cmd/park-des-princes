<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


$recherche =
    trim($_GET['q'] ?? '');

$etat =
    trim($_GET['etat'] ?? '');

$categorie =
    filter_input(
        INPUT_GET,
        'categorie',
        FILTER_VALIDATE_INT
    );


$sql = "
    SELECT
        j.*,
        c.nom_categorie,
        CONCAT(u.prenom, ' ', u.nom) AS responsable_nom

    FROM jeu j

    LEFT JOIN categorie c
        ON j.id_categorie = c.id_categorie

    LEFT JOIN utilisateur u
        ON j.email_responsable = u.email

    WHERE j.etat <> 'hors service'
";

$params = [];


if ($recherche !== '') {

    $sql .= "
        AND j.nom LIKE :recherche
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


if ($etat !== '') {

    $sql .= "
        AND j.etat = :etat
    ";

    $params[':etat'] =
        $etat;
}


if ($categorie) {

    $sql .= "
        AND j.id_categorie = :categorie
    ";

    $params[':categorie'] =
        $categorie;
}


$sql .= "
    ORDER BY j.id_jeu DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$jeux =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Gestion des jeux</title>

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


<main class="jeux-page">

    <div class="page-header">

        <div>
            <h1>Gestion des jeux</h1>

            <p>
                Liste des jeux du parc
            </p>
        </div>


        <a href="ajouter.php" class="btn-add">
            <span>＋</span>
            Ajouter un jeu
        </a>

    </div>


    <form
    method="GET"
    class="filters-box"
>

    <input
        type="text"
        name="q"
        placeholder="Rechercher un jeu..."
        value="<?= htmlspecialchars($recherche) ?>"
    >


    <select name="etat">

        <option value="">
            Tous les états
        </option>

        <option
            value="disponible"
            <?= $etat === 'disponible' ? 'selected' : '' ?>
        >
            Disponible
        </option>

        <option
            value="en fonctionnement"
            <?= $etat === 'en fonctionnement' ? 'selected' : '' ?>
        >
            En fonctionnement
        </option>

        <option
            value="en maintenance"
            <?= $etat === 'en maintenance' ? 'selected' : '' ?>
        >
            En maintenance
        </option>

        <option
            value="hors service"
            <?= $etat === 'hors service' ? 'selected' : '' ?>
        >
            Hors service
        </option>

        <option
            value="fermé temporairement"
            <?= $etat === 'fermé temporairement' ? 'selected' : '' ?>
        >
            Fermé temporairement
        </option>

    </select>


    <select name="categorie">

        <option value="">
            Toutes les catégories
        </option>

        <?php foreach ($categories as $cat): ?>

            <option
                value="<?= $cat['id_categorie'] ?>"
                <?= $categorie == $cat['id_categorie']
                    ? 'selected'
                    : '' ?>
            >

                <?= htmlspecialchars(
                    $cat['nom_categorie']
                ) ?>

            </option>

        <?php endforeach; ?>

    </select>


    <button
        type="submit"
        class="btn-search"
    >
        Rechercher
    </button>


    <a
        href="index.php"
        class="btn-reset"
    >
        Réinitialiser
    </a>

</form>


    <div class="table-box">

        <table>

            <thead>

    <tr>

        <th>ID</th>
        <th>Image</th>
        <th>Nom</th>
        <th>Catégorie</th>

        <th>Âge min</th>

        <th>Capacité</th>

        <th>Durée</th>

        <th>Tarif</th>
        <th>État</th>

        <th>Date mise en service</th>

        <th>Responsable</th>

        <th>Description</th>

        <th>Actions</th>

    </tr>

</thead>


            <tbody>

            <?php if (count($jeux) > 0): ?>


                <?php foreach ($jeux as $jeu): ?>

             <tr>

    <!-- ID -->
    <td>
        <?= $jeu['id_jeu'] ?>
    </td>


    <!-- IMAGE -->
    <td>

        <?php if (!empty($jeu['img_jeu'])): ?>

            <img
                src="/park-des-princes/uploads/jeux/<?= htmlspecialchars($jeu['img_jeu']) ?>"
                alt="<?= htmlspecialchars($jeu['nom']) ?>"
                class="jeu-thumb"
            >

        <?php else: ?>

            <span>Aucune image</span>

        <?php endif; ?>

    </td>


    <!-- NOM -->
    <td>
        <?= htmlspecialchars($jeu['nom']) ?>
    </td>


    <!-- CATEGORIE -->
    <td>

        <?= htmlspecialchars(
            $jeu['nom_categorie']
            ?? 'Non définie'
        ) ?>

    </td>


    <!-- AGE MINIMUM -->
    <td>

        <?= $jeu['age_min'] !== null && $jeu['age_min'] !== ''
            ? htmlspecialchars($jeu['age_min']) . ' ans'
            : '-' ?>

    </td>


    <!-- CAPACITE -->
    <td>
        <?= htmlspecialchars($jeu['capacite']) ?>
    </td>


    <!-- DUREE SESSION -->
    <td>

        <?= $jeu['duree_session'] !== null && $jeu['duree_session'] !== ''
            ? htmlspecialchars($jeu['duree_session']) . ' min'
            : '-' ?>

    </td>


    <!-- TARIF -->
    <td>
        <?= htmlspecialchars($jeu['tarif']) ?> DH
    </td>


    <!-- ETAT -->
    <td>

        <span class="badge-etat">

            <?= htmlspecialchars($jeu['etat']) ?>

        </span>

    </td>


    <!-- DATE MISE EN SERVICE -->
    <td>

        <?= !empty($jeu['date_mise_service'])
            ? htmlspecialchars($jeu['date_mise_service'])
            : '-' ?>

    </td>


    <!-- RESPONSABLE -->
    <td>

        <?= !empty($jeu['responsable_nom'])
            ? htmlspecialchars($jeu['responsable_nom'])
            : 'Non affecté' ?>

    </td>


    <!-- DESCRIPTION -->
    <td class="description-cell">

        <?= !empty($jeu['description'])
            ? htmlspecialchars($jeu['description'])
            : '-' ?>

    </td>


    <!-- ACTIONS -->
    <td>

        <div class="actions-box">

            <a
                href="details.php?id=<?= $jeu['id_jeu'] ?>"
                class="btn-view"
            >
                Voir
            </a>

            <a
                href="modifier.php?id=<?= $jeu['id_jeu'] ?>"
                class="btn-edit"
            >
                Modifier
            </a>

            <a
                href="desactiver.php?id=<?= $jeu['id_jeu'] ?>"
                class="btn-delete"
                onclick="return confirm('Voulez-vous désactiver ce jeu ?')"
            >
                Désactiver
            </a>

        </div>

    </td>

</tr>

                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td colspan="13">

                        Aucun jeu enregistré.

                    </td>

                </tr>


            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>

</html>