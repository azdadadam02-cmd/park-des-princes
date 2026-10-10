<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom =
        trim($_POST['nom']);

    $prenom =
        trim($_POST['prenom']);

    $email =
        trim($_POST['email']);

    $telephone =
        trim($_POST['telephone']);

    $mot_passe =
        $_POST['mot_passe'];


    if (
    empty($nom) ||
    empty($prenom) ||
    empty($email) ||
    empty($telephone) ||
    empty($mot_passe)
) {

    $error = "Veuillez remplir les champs obligatoires.";

    } elseif (
        mb_strlen($nom) < 3 ||
        mb_strlen($prenom) < 3
    ) {

        $error = "Le nom et le prénom doivent contenir au moins 3 lettres.";

    } elseif (
        !preg_match('/^[A-Za-zÀ-ÿ\s\'-]+$/u', $nom) ||
        !preg_match('/^[A-Za-zÀ-ÿ\s\'-]+$/u', $prenom)
    ) {

    $error = "Le nom et le prénom ne doivent pas contenir de chiffres.";

    } elseif (
        !preg_match('/^[0-9]{10}$/', $telephone)
    ) {

        $error = "Le téléphone doit contenir exactement 10 chiffres. Exemple : 0600000000.";
    
    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Adresse email invalide.";

    } else {

        try {

            $pdo->beginTransaction();


            // UTILISATEUR

            $stmt = $pdo->prepare("
                INSERT INTO utilisateur
                (
                    email,
                    mot_passe,
                    nom,
                    prenom,
                    telephone,
                    role,
                    email_admin
                )

                VALUES
                (
                    :email,
                    :mot_passe,
                    :nom,
                    :prenom,
                    :telephone,
                    'client',
                    :email_admin
                )
            ");

            $stmt->execute([

                ':email' =>
                    $email,

                ':mot_passe' =>
                    password_hash(
                        $mot_passe,
                        PASSWORD_DEFAULT
                    ),

                ':nom' =>
                    $nom,

                ':prenom' =>
                    $prenom,

                ':telephone' =>
                    $telephone,

                ':email_admin' =>
                    $_SESSION['admin_email']

            ]);


            // CLIENT

            $stmt = $pdo->prepare("
                INSERT INTO client
                (
                    email,
                    date_inscription
                )

                VALUES
                (
                    :email,
                    CURDATE()
                )
            ");

            $stmt->execute([
                ':email' => $email
            ]);


            $pdo->commit();


            header("Location: index.php");
            exit;


        } catch (PDOException $e) {

            $pdo->rollBack();

            $error =
                "Erreur : "
                . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <title>Ajouter client</title>

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

            <h1>Ajouter un client</h1>

            <p>
                Enregistrer un nouveau client
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
                    minlength="3"
                    pattern="[A-Za-zÀ-ÿ '-]{3,}"
                    required
                    >

                </div>


                <div class="form-group">

                    <label>Prénom *</label>

                    <input
                    type="text"
                    name="prenom"
                    minlength="3"
                    pattern="[A-Za-zÀ-ÿ '-]{3,}"
                    required
                    >

                </div>


                <div class="form-group">

                    <label>Email *</label>

                    <input
                        type="email"
                        name="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Téléphone *</label>

                    <input
                        type="text"
                        name="telephone"
                        minlength="10"
                        maxlength="10"
                        pattern="[0-9]{10}"
                        placeholder="0600000000"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Mot de passe *</label>

                    <input
                        type="password"
                        name="mot_passe"
                        required
                    >

                </div>


            </div>


            <button
                type="submit"
                class="btn-primary"
            >
                Enregistrer le client
            </button>

        </form>

    </div>

</main>

</body>
</html>




