<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$sql = "
    SELECT
        r.email,
        r.fonction,
        r.date_embauche,
        r.statut_responsable,
        u.nom,
        u.prenom,
        u.telephone

    FROM responsable_de_jeu r

    INNER JOIN utilisateur u
        ON r.email = u.email
    
    WHERE r.statut_responsable <> 'inactif'

    ORDER BY u.nom
";

$stmt = $pdo->query($sql);

$responsables = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Responsables</title>

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
            <h1>Gestion des responsables</h1>
            <p>Liste des responsables du parc</p>
        </div>

        <a
            href="ajouter.php"
            class="btn-primary"
        >
            + Ajouter responsable
        </a>

    </div>


    <div class="table-box">

        <table>

            <thead>

                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Fonction</th>
                    <th>Date embauche</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

            <?php if (count($responsables) > 0): ?>

                <?php foreach ($responsables as $responsable): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $responsable['prenom']
                                . ' '
                                . $responsable['nom']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($responsable['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($responsable['telephone']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($responsable['fonction']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($responsable['date_embauche']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($responsable['statut_responsable']) ?>
                        </td>

                        <td class="actions">

    <a
        href="affecter.php?email=<?= urlencode($responsable['email']) ?>"
        class="btn-view"
    >
        Affecter
    </a>

    <a
        href="modifier.php?email=<?= urlencode($responsable['email']) ?>"
        class="btn-edit"
    >
        Modifier
    </a>

    <a
        href="desactiver.php?email=<?= urlencode($responsable['email']) ?>"
        class="btn-delete"
        onclick="return confirm('Voulez-vous désactiver ce responsable ?')"
    >
        Désactiver
    </a>

</td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7">
                        Aucun responsable enregistré.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>