<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$sql = "
    SELECT
        c.email,
        c.date_inscription,
        u.nom,
        u.prenom,
        u.telephone

    FROM client c

    INNER JOIN utilisateur u
        ON c.email = u.email

    ORDER BY c.date_inscription DESC
";

$stmt = $pdo->query($sql);

$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Gestion des clients</title>

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

            <h1>Gestion des clients</h1>

            <p>
                Liste des clients du parc
            </p>

        </div>

        <a
            href="ajouter.php"
            class="btn-primary"
        >
            + Ajouter un client
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
    <th>Date inscription</th>
    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

            <?php if (count($clients) > 0): ?>

                <?php foreach ($clients as $client): ?>

                    <tr>

                        <td>
    <?= htmlspecialchars($client['nom']) ?>
</td>

<td>
    <?= htmlspecialchars($client['prenom']) ?>
</td>


                        <td>
                            <?= htmlspecialchars($client['email']) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars($client['telephone']) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars($client['date_inscription']) ?>
                        </td>


                        <td class="actions">

                        <a
                                     href="modifier.php?email=<?= urlencode($client['email']) ?>"
                                    class="btn-edit"
                                    >
                                     Modifier
                        </a>

                            <a
                                        href="supprimer.php?email=<?= urlencode($client['email']) ?>"
                                        class="btn-delete"
                                        onclick="return confirm('Voulez-vous vraiment supprimer ce client ?')"
                                            >
                                            Supprimer
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td colspan="6">
                        Aucun client enregistré.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>