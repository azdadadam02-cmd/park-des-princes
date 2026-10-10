<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$recherche = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$statut = trim($_GET['statut'] ?? '');


$sql = "
    SELECT
        b.id_billet,
        b.date_creation,
        b.date_fin,
        b.type,
        b.statut_billet,
        b.quantite,
        b.prix_unitaire,
        b.num_commande,
        b.email_agent,

        u.nom AS agent_nom,
        u.prenom AS agent_prenom

    FROM billet b

    LEFT JOIN utilisateur u
        ON b.email_agent = u.email

    WHERE 1 = 1
";

$params = [];


/* Recherche */

if ($recherche !== '') {

    $sql .= "
        AND (
            b.id_billet LIKE :recherche
            OR b.num_commande LIKE :recherche
        )
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


/* Type */

if ($type !== '') {

    $sql .= "
        AND b.type = :type
    ";

    $params[':type'] = $type;
}


/* Statut */

if ($statut !== '') {

    $sql .= "
        AND b.statut_billet = :statut
    ";

    $params[':statut'] = $statut;
}


$sql .= "
    ORDER BY b.id_billet DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$billets =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Gestion des billets</title>

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

            <h1>Gestion des billets</h1>

            <p>
                Consulter les billets vendus
            </p>

        </div>

    </div>


    <form
        method="GET"
        class="filters-box"
    >

        <input
            type="text"
            name="q"
            placeholder="ID billet ou commande..."
            value="<?= htmlspecialchars($recherche) ?>"
        >


        <select name="type">

            <option value="">
                Tous les types
            </option>

            <option
                value="enfant"
                <?= $type === 'enfant' ? 'selected' : '' ?>
            >
                Enfant
            </option>

            <option
                value="adulte"
                <?= $type === 'adulte' ? 'selected' : '' ?>
            >
                Adulte
            </option>

            <option
                value="famille"
                <?= $type === 'famille' ? 'selected' : '' ?>
            >
                Famille
            </option>

            <option
                value="pass journée"
                <?= $type === 'pass journée' ? 'selected' : '' ?>
            >
                Pass journée
            </option>

            <option
                value="abonnement"
                <?= $type === 'abonnement' ? 'selected' : '' ?>
            >
                Abonnement
            </option>

        </select>


        <select name="statut">

            <option value="">
                Tous les statuts
            </option>

            <option
                value="valide"
                <?= $statut === 'valide' ? 'selected' : '' ?>
            >
                Valide
            </option>

            <option
                value="utilisé"
                <?= $statut === 'utilisé' ? 'selected' : '' ?>
            >
                Utilisé
            </option>

            <option
                value="expiré"
                <?= $statut === 'expiré' ? 'selected' : '' ?>
            >
                Expiré
            </option>

            <option
                value="annulé"
                <?= $statut === 'annulé' ? 'selected' : '' ?>
            >
                Annulé
            </option>

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


    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Prix</th>
                    <th>Commande</th>
                    <th>Date création</th>
                    <th>Date fin</th>
                    <th>Statut</th>
                    <th>Action</th>

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
                            #<?= htmlspecialchars($billet['num_commande']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($billet['date_creation']) ?>
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

                        <td>

                            <a
                                href="details.php?id=<?= urlencode($billet['id_billet']) ?>"
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
                        Aucun billet trouvé.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>