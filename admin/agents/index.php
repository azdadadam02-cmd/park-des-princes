<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$sql = "
    SELECT
        a.email,
        u.nom,
        u.prenom,
        u.telephone

    FROM agent_de_vente a

    INNER JOIN utilisateur u
        ON a.email = u.email

    ORDER BY u.nom
";

$stmt = $pdo->query($sql);

$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Agents de vente</title>

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

            <h1>Agents de vente</h1>

            <p>
                Gestion des agents de vente et d'accueil
            </p>

        </div>

        <a
            href="ajouter.php"
            class="btn-primary"
        >
            + Ajouter un agent
        </a>

    </div>


    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>Nom</th>
    <th>Prénom</th>
    <th>Email</th>
    <th>Téléphone</th>
    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($agents) > 0): ?>

                <?php foreach ($agents as $agent): ?>

                    <tr>

    <td>
        <?= htmlspecialchars($agent['nom']) ?>
    </td>

    <td>
        <?= htmlspecialchars($agent['prenom']) ?>
    </td>

    <td>
        <?= htmlspecialchars($agent['email']) ?>
    </td>

    <td>
        <?= htmlspecialchars($agent['telephone']) ?>
    </td>

    <td class="actions">

        <a
            href="modifier.php?email=<?= urlencode($agent['email']) ?>"
            class="btn-edit"
        >
            Modifier
        </a>

        <a
            href="desactiver.php?email=<?= urlencode($agent['email']) ?>"
            class="btn-delete"
            onclick="return confirm('Désactiver cet agent ?')"
        >
            Désactiver
        </a>

    </td>

</tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="5">
                        Aucun agent enregistré.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>