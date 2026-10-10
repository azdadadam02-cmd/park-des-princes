<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$email = $_GET['email'] ?? '';

if (empty($email)) {
    die("Responsable invalide.");
}


// récupérer responsable
$stmt = $pdo->prepare("
    SELECT
        u.nom,
        u.prenom,
        r.email

    FROM responsable_de_jeu r

    INNER JOIN utilisateur u
        ON r.email = u.email

    WHERE r.email = :email
");

$stmt->execute([
    ':email' => $email
]);

$responsable = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$responsable) {
    die("Responsable introuvable.");
}


// récupérer jeux
$stmt = $pdo->query("
    SELECT
        id_jeu,
        nom,
        email_responsable

    FROM jeu

    ORDER BY nom
");

$jeux = $stmt->fetchAll(PDO::FETCH_ASSOC);


// affectation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_jeu = filter_input(
        INPUT_POST,
        'id_jeu',
        FILTER_VALIDATE_INT
    );

    if ($id_jeu) {

        $stmt = $pdo->prepare("
            UPDATE jeu

            SET email_responsable = :email

            WHERE id_jeu = :id
        ");

        $stmt->execute([

            ':email' => $email,
            ':id' => $id_jeu

        ]);

        header("Location: index.php");
        exit;
    }
}

?>


<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Affecter responsable</title>

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

            <h1>Affecter un jeu</h1>

            <p>
                Responsable :
                <?= htmlspecialchars(
                    $responsable['prenom']
                    . ' '
                    . $responsable['nom']
                ) ?>
            </p>

        </div>

        <a
            href="index.php"
            class="btn-secondary"
        >
            Retour
        </a>

    </div>


    <div class="form-box">

        <form method="POST">

            <div class="form-group">

                <label>
                    Choisir un jeu
                </label>

                <select
                    name="id_jeu"
                    required
                >

                    <option value="">
                        Choisir...
                    </option>

                    <?php foreach ($jeux as $jeu): ?>

                        <option
                            value="<?= $jeu['id_jeu'] ?>"
                        >

                            <?= htmlspecialchars($jeu['nom']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <br>

            <button
                type="submit"
                class="btn-primary"
            >
                Affecter le responsable
            </button>

        </form>

    </div>

</main>

</body>
</html>