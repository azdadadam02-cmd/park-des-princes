<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$error = "";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    die("Jeu invalide.");
}


// ==========================
// Récupérer le jeu
// ==========================

$stmt = $pdo->prepare("
    SELECT *
    FROM jeu
    WHERE id_jeu = :id
");

$stmt->execute([
    ':id' => $id
]);

$jeu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$jeu) {
    die("Jeu introuvable.");
}


// ==========================
// Catégories
// ==========================

$stmt = $pdo->query("
    SELECT *
    FROM categorie
    ORDER BY nom_categorie
");

$categories =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================
// Responsables
// ==========================

$sqlResponsables = "
    SELECT
        r.email,
        u.nom,
        u.prenom
    FROM responsable_de_jeu r

    INNER JOIN utilisateur u
        ON r.email = u.email

    ORDER BY u.nom
";

$stmt =
    $pdo->query($sqlResponsables);

$responsables =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================
// Modification
// ==========================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom =
        trim($_POST['nom']);

    $description =
        trim($_POST['description']);

    $age_min =
        $_POST['age_min'];

    $capacite =
        $_POST['capacite'];

    $duree_session =
        $_POST['duree_session'];

    $tarif =
        $_POST['tarif'];

    $etat =
        $_POST['etat'];

    $date_mise_service =
        $_POST['date_mise_service'];

    $id_categorie =
        filter_input(
            INPUT_POST,
            'id_categorie',
            FILTER_VALIDATE_INT
        );

    $email_responsable =
        !empty($_POST['email_responsable'])
        ? $_POST['email_responsable']
        : null;


    if (
        empty($nom) ||
        empty($capacite) ||
        empty($tarif) ||
        !$id_categorie
    ) {

        $error =
            "Veuillez remplir les champs obligatoires.";

    } else {

        $sql = "
            UPDATE jeu

            SET
                nom = :nom,
                description = :description,
                age_min = :age_min,
                capacite = :capacite,
                duree_session = :duree_session,
                tarif = :tarif,
                etat = :etat,
                date_mise_service = :date_mise_service,
                id_categorie = :id_categorie,
                email_responsable = :email_responsable

            WHERE id_jeu = :id
        ";

        $stmt =
            $pdo->prepare($sql);

        $stmt->execute([

            ':nom' => $nom,

            ':description' => $description,

            ':age_min' => $age_min,

            ':capacite' => $capacite,

            ':duree_session' => $duree_session,

            ':tarif' => $tarif,

            ':etat' => $etat,

            ':date_mise_service' => $date_mise_service,

            ':id_categorie' => $id_categorie,

            ':email_responsable' =>
                $email_responsable,

            ':id' => $id

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

    <title>Modifier jeu</title>

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

            <h1>Modifier le jeu</h1>

            <p>
                Modifier les informations du jeu
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


        <?php if ($error): ?>

            <div class="alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-grid">


                <div class="form-group">

                    <label>Nom *</label>

                    <input
                        type="text"
                        name="nom"
                        value="<?= htmlspecialchars($jeu['nom']) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Catégorie *</label>

                    <select
                        name="id_categorie"
                        required
                    >

                        <?php foreach ($categories as $categorie): ?>

                            <option
                                value="<?= $categorie['id_categorie'] ?>"

                                <?= $categorie['id_categorie'] == $jeu['id_categorie']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $categorie['nom_categorie']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Âge minimum</label>

                    <input
                        type="number"
                        name="age_min"
                        min="0"
                        value="<?= htmlspecialchars($jeu['age_min']) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Capacité *</label>

                    <input
                        type="number"
                        name="capacite"
                        min="1"
                        value="<?= htmlspecialchars($jeu['capacite']) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Durée session</label>

                    <input
                        type="number"
                        name="duree_session"
                        min="1"
                        value="<?= htmlspecialchars($jeu['duree_session']) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Tarif *</label>

                    <input
                        type="number"
                        name="tarif"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars($jeu['tarif']) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>État</label>

                    <select name="etat">

                        <?php

                        $etats = [
                            'disponible',
                            'en fonctionnement',
                            'en maintenance',
                            'hors service',
                            'fermé temporairement'
                        ];

                        foreach ($etats as $etat):

                        ?>

                            <option
                                value="<?= $etat ?>"

                                <?= $jeu['etat'] === $etat
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= ucfirst($etat) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Date mise en service
                    </label>

                    <input
                        type="date"
                        name="date_mise_service"
                        value="<?= htmlspecialchars($jeu['date_mise_service']) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Responsable</label>

                    <select name="email_responsable">

                        <option value="">
                            Aucun responsable
                        </option>

                        <?php foreach ($responsables as $responsable): ?>

                            <option
                                value="<?= htmlspecialchars($responsable['email']) ?>"

                                <?= $jeu['email_responsable'] === $responsable['email']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $responsable['prenom']
                                    . ' '
                                    . $responsable['nom']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group full-width">

                    <label>Description</label>

                    <textarea
                        name="description"
                        rows="5"
                    ><?= htmlspecialchars($jeu['description']) ?></textarea>

                </div>

            </div>


            <button
                type="submit"
                class="btn-primary"
            >
                Enregistrer les modifications
            </button>


        </form>

    </div>

</main>

</body>
</html>