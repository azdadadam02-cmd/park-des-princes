<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    die("Jeu invalide.");
}


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

    WHERE j.id_jeu = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$jeu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$jeu) {
    die("Jeu introuvable.");
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Détails jeu</title>

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
                <?= htmlspecialchars($jeu['nom']) ?>
            </h1>

            <p>Détails du jeu</p>

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

            <span>Catégorie</span>

            <strong>
                <?= htmlspecialchars(
                    $jeu['nom_categorie']
                    ?? 'Non définie'
                ) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Âge minimum</span>

            <strong>
                <?= $jeu['age_min'] ?> ans
            </strong>

        </div>


        <div class="detail-item">

            <span>Capacité</span>

            <strong>
                <?= $jeu['capacite'] ?> personnes
            </strong>

        </div>


        <div class="detail-item">

            <span>Durée session</span>

            <strong>
                <?= $jeu['duree_session'] ?> minutes
            </strong>

        </div>


        <div class="detail-item">

            <span>Tarif</span>

            <strong>
                <?= $jeu['tarif'] ?> DH
            </strong>

        </div>


        <div class="detail-item">

            <span>État</span>

            <strong>
                <?= htmlspecialchars($jeu['etat']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Date mise en service</span>

            <strong>
                <?= htmlspecialchars($jeu['date_mise_service']) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span>Responsable</span>

            <strong>

                <?= htmlspecialchars(
                    $jeu['responsable_nom']
                    ?? 'Non affecté'
                ) ?>

            </strong>

        </div>


        <div class="detail-description">

            <span>Description</span>

            <p>
                <?= nl2br(
                    htmlspecialchars($jeu['description'])
                ) ?>
            </p>

        </div>

        <div class="detail-image">

    <label>Image du jeu</label>

    <?php if (!empty($jeu['img_jeu'])): ?>

        <img
            src="/park-des-princes/uploads/jeux/<?= htmlspecialchars($jeu['img_jeu']) ?>"
            alt="<?= htmlspecialchars($jeu['nom']) ?>"
            class="jeu-detail-image"
        >

    <?php else: ?>

        <p>Aucune image disponible.</p>

    <?php endif; ?>

</div>

    </div>

</main>

</body>
</html>