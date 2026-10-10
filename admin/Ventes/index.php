<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$recherche = trim($_GET['q'] ?? '');
$statut = trim($_GET['statut'] ?? '');
$date = trim($_GET['date'] ?? '');


/* récupérer les statuts existants */
$stmtStatuts = $pdo->query("
    SELECT DISTINCT statut_commande
    FROM commande
    WHERE statut_commande IS NOT NULL
      AND statut_commande <> ''
    ORDER BY statut_commande
");

$statuts = $stmtStatuts->fetchAll(PDO::FETCH_COLUMN);


/* commandes */

$sql = "
    SELECT
        c.num_commande,
        c.date_commande,
        c.montant_total,
        c.statut_commande,
        c.mode_paiement,
        c.email_client,
        c.email_agent,

        u.nom,
        u.prenom,

        ua.nom AS agent_nom,
        ua.prenom AS agent_prenom

    FROM commande c

    INNER JOIN utilisateur u
        ON c.email_client = u.email

    LEFT JOIN utilisateur ua
        ON c.email_agent = ua.email

    WHERE 1 = 1
";

$params = [];


/* recherche */

if ($recherche !== '') {

    $sql .= "
        AND (
            u.nom LIKE :recherche
            OR u.prenom LIKE :recherche
            OR u.email LIKE :recherche
            OR c.num_commande LIKE :recherche
        )
    ";

    $params[':recherche'] =
        '%' . $recherche . '%';
}


/* statut */

if ($statut !== '') {

    $sql .= "
        AND c.statut_commande = :statut
    ";

    $params[':statut'] = $statut;
}


/* date */

if ($date !== '') {

    $sql .= "
        AND DATE(c.date_commande) = :date
    ";

    $params[':date'] = $date;
}


$sql .= "
    ORDER BY c.date_commande DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$commandes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Commandes</title>

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

            <h1>Ventes</h1>

            <p>
                Consulter les ventes du parc
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
            placeholder="Client, email ou numéro..."
            value="<?= htmlspecialchars($recherche) ?>"
        >


        <select name="statut">

            <option value="">
                Tous les statuts
            </option>

            <?php foreach ($statuts as $s): ?>

                <option
                    value="<?= htmlspecialchars($s) ?>"
                    <?= $statut === $s ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($s) ?>
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


    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>N°</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Paiement</th>
                    <th>Statut</th>
                    <th>Agent</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($commandes) > 0): ?>

                <?php foreach ($commandes as $commande): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $commande['num_commande']
                            ) ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $commande['prenom']
                                . ' '
                                . $commande['nom']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $commande['date_commande']
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $commande['montant_total'],
                                2,
                                ',',
                                ' '
                            ) ?>

                            DH

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $commande['mode_paiement']
                            ) ?>

                        </td>


                        <td>

                            <span class="status-badge">

                                <?= htmlspecialchars(
                                    $commande['statut_commande']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if ($commande['email_agent']): ?>

                                <?= htmlspecialchars(
                                    $commande['agent_prenom']
                                    . ' '
                                    . $commande['agent_nom']
                                ) ?>

                            <?php else: ?>

                                Non affecté

                            <?php endif; ?>

                        </td>


                        <td>

                            <a
                                href="details.php?id=<?= urlencode($commande['num_commande']) ?>"
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
                        Aucune commande trouvée.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>