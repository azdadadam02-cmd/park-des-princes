<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


$message = "";
$error = "";


// ===========================
// Charger catégories
// ===========================

$stmt = $pdo->query("
    SELECT *
    FROM categorie
    ORDER BY nom_categorie
");

$categories =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ===========================
// Charger responsables
// ===========================

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

$stmtResponsables =
    $pdo->query($sqlResponsables);

$responsables =
    $stmtResponsables->fetchAll(PDO::FETCH_ASSOC);


// ===========================
// Ajouter jeu
// ===========================

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

    $id_categorie = filter_input(
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
    $capacite === '' ||
    $tarif === '' ||
    !$id_categorie
    ) {

        $error = "Veuillez remplir les champs obligatoires et choisir une catégorie.";

    } elseif (mb_strlen($nom) < 2) {

        $error = "Le nom du jeu doit contenir au moins 2 caractères.";

    } elseif (
        $age_min !== '' &&
        (!is_numeric($age_min) || $age_min < 0)
    ) {

        $error = "L'âge minimum doit être supérieur ou égal à 0.";

    } elseif (
        !is_numeric($capacite) ||
        $capacite <= 0
    ) {

        $error = "La capacité doit être supérieure à 0.";

    } elseif (
        $duree_session !== '' &&
        (!is_numeric($duree_session) || $duree_session <= 0)
    ) {

        $error = "La durée de session doit être supérieure à 0.";

    } elseif (
        !is_numeric($tarif) ||
        $tarif < 0
    ) {

        $error = "Le tarif doit être supérieur ou égal à 0.";

    }  
      else {

        $img_jeu = null;


$img_jeu = null;

if (
    !isset($_FILES['img_jeu']) ||
    $_FILES['img_jeu']['error'] === UPLOAD_ERR_NO_FILE
) {

    $error = "Veuillez ajouter une image du jeu.";

} elseif (
    $_FILES['img_jeu']['error'] !== UPLOAD_ERR_OK
) {

    $error = "Erreur lors de l'envoi de l'image.";

} else {

    $extension = strtolower(
        pathinfo(
            $_FILES['img_jeu']['name'],
            PATHINFO_EXTENSION
        )
    );

    $extensionsAutorisees = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];

    if (!in_array($extension, $extensionsAutorisees)) {

        $error = "L'image doit être au format JPG, JPEG, PNG ou WEBP.";

    } else {

        $dossier = __DIR__ . '/../../uploads/jeux/';

        if (!is_dir($dossier)) {
            mkdir($dossier, 0777, true);
        }

        $img_jeu =
            'jeu_' .
            uniqid() .
            '.' .
            $extension;

        move_uploaded_file(
            $_FILES['img_jeu']['tmp_name'],
            $dossier . $img_jeu
        );
    }
}

if (empty($error)) {
        $sql = "

            INSERT INTO jeu
            (
                nom,
                description,
                age_min,
                capacite,
                duree_session,
                tarif,
                etat,
                date_mise_service,
                id_categorie,
                email_responsable,
                email_admin,
                img_jeu
            )

            VALUES
            (
                :nom,
                :description,
                :age_min,
                :capacite,
                :duree_session,
                :tarif,
                :etat,
                :date_mise_service,
                :id_categorie,
                :email_responsable,
                :email_admin,
                :img_jeu
            )

        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute([

            ':nom' =>
                $nom,

            ':description' =>
                $description,

            ':age_min' =>
                $age_min,

            ':capacite' =>
                $capacite,

            ':duree_session' =>
                $duree_session,

            ':tarif' =>
                $tarif,

            ':etat' =>
                $etat,

            ':date_mise_service' =>
                $date_mise_service,

            ':id_categorie' =>
                $id_categorie,

            ':email_responsable' =>
                $email_responsable,

            ':email_admin' =>
                $_SESSION['admin_email'],

            ':img_jeu' => $img_jeu

        ]);


        header(
            "Location: index.php"
        );

        exit;
        }
    }
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Ajouter un jeu</title>

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

            <h1>Ajouter un jeu</h1>

            <p>
                Ajouter un nouveau jeu au parc
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


        <form method="POST" enctype="multipart/form-data">


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Nom du jeu *
                    </label>

                    <input
    type="text"
    name="nom"
    minlength="2"
    maxlength="100"
    required
>

                </div>


                <div class="form-group">

                    <label>
                        Catégorie
                    </label>

                    <select name="id_categorie" required>

                        <option value="">
                            Choisir
                        </option>


                        <?php foreach ($categories as $categorie): ?>

                            <option
                                value="<?= $categorie['id_categorie'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $categorie['nom_categorie']
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Âge minimum
                    </label>

                    <input
                        type="number"
                        name="age_min"
                        min="0"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Capacité *
                    </label>

                    <input
                        type="number"
                        name="capacite"
                        min="1"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Durée session
                    </label>

                    <input
                        type="number"
                        name="duree_session"
                        min="1"
                        placeholder="Minutes"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Tarif *
                    </label>

                    <input
                        type="number"
                        name="tarif"
                        step="0.01"
                        min="0"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        État
                    </label>

                    <select name="etat">

                        <option value="disponible">
                            Disponible
                        </option>

                        <option value="en fonctionnement">
                            En fonctionnement
                        </option>

                        <option value="en maintenance">
                            En maintenance
                        </option>

                        <option value="hors service">
                            Hors service
                        </option>

                        <option value="fermé temporairement">
                            Fermé temporairement
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Date mise en service
                    </label>

                    <input
                        type="date"
                        name="date_mise_service"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Responsable
                    </label>

                    <select name="email_responsable">

                        <option value="">
                            Aucun responsable
                        </option>


                        <?php foreach ($responsables as $responsable): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $responsable['email']
                                ) ?>"
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

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="5"
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>Image du jeu</label>

                    <input
    type="file"
    name="img_jeu"
    accept=".jpg,.jpeg,.png,.webp"
    required
>

                </div>


            </div>


            <button
                type="submit"
                class="btn-primary"
            >
                Enregistrer le jeu
            </button>


        </form>

    </div>


</main>

</body>

</html>