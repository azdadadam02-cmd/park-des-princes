<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$error = "";

$email =
    $_GET['email'] ?? '';

if (empty($email)) {
    die("Agent invalide.");
}


$stmt = $pdo->prepare("
    SELECT
        u.email,
        u.nom,
        u.prenom,
        u.telephone

    FROM agent_de_vente a

    INNER JOIN utilisateur u
        ON a.email = u.email

    WHERE a.email = :email
");

$stmt->execute([
    ':email' => $email
]);

$agent =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$agent) {
    die("Agent introuvable.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom =
        trim($_POST['nom']);

    $prenom =
        trim($_POST['prenom']);

    $telephone =
        trim($_POST['telephone']);


    if (
    empty($nom) ||
    empty($prenom) ||
    empty($telephone)
) {

    $error = "Veuillez remplir les champs obligatoires.";

    } elseif (
        strlen($nom) < 3 ||
        strlen($prenom) < 3
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

        $stmt = $pdo->prepare("
            UPDATE utilisateur

            SET
                nom = :nom,
                prenom = :prenom,
                telephone = :telephone

            WHERE email = :email
        ");

        $stmt->execute([

            ':nom' =>
                $nom,

            ':prenom' =>
                $prenom,

            ':telephone' =>
                $telephone,

            ':email' =>
                $email

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

    <title>Modifier agent</title>

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

            <h1>Modifier agent</h1>

            <p>
                Modifier les informations de l'agent
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
    value="<?= htmlspecialchars($_POST['nom'] ?? $agent['nom']) ?>"
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
    value="<?= htmlspecialchars($_POST['prenom'] ?? $agent['prenom']) ?>"
    minlength="3"
    pattern="[A-Za-zÀ-ÿ '-]{3,}"
    required
>

                </div>


                <div class="form-group">

                    <label>Email</label>

                    <input
                        type="email"

                        value="<?= htmlspecialchars(
                            $agent['email']
                        ) ?>"

                        disabled
                    >

                </div>


                <div class="form-group">

                    <label>Téléphone *</label>

                    <input
    type="text"
    name="telephone"
    value="<?= htmlspecialchars($_POST['telephone'] ?? $agent['telephone']) ?>"
    minlength="10"
    maxlength="10"
    pattern="[0-9]{10}"
    placeholder="0600000000"
    required
>

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