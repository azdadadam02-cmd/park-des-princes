<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$error = "";

$email = $_GET['email'] ?? '';

if (empty($email)) {
    die("Responsable invalide.");
}


// ===============================
// Récupérer le responsable
// ===============================

$sql = "
    SELECT
        u.email,
        u.nom,
        u.prenom,
        u.telephone,
        r.fonction,
        r.date_embauche,
        r.statut_responsable

    FROM responsable_de_jeu r

    INNER JOIN utilisateur u
        ON r.email = u.email

    WHERE r.email = :email
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':email' => $email
]);

$responsable = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$responsable) {
    die("Responsable introuvable.");
}


// ===============================
// Modifier
// ===============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $telephone = trim($_POST['telephone']);

    $fonction = trim($_POST['fonction']);
    $date_embauche = $_POST['date_embauche'];
    $statut = $_POST['statut_responsable'];


    // ===============================
    // VALIDATION
    // ===============================

    if (
        empty($nom) ||
        empty($prenom) ||
        empty($telephone) ||
        empty($fonction)
    ) {

        $error = "Veuillez remplir les champs obligatoires.";

    } elseif (
        mb_strlen($nom) < 3 ||
        mb_strlen($prenom) < 3
    ) {

        $error =
            "Le nom et le prénom doivent contenir au moins 3 lettres.";

    } elseif (
        !preg_match('/^[A-Za-zÀ-ÿ\s\'-]+$/u', $nom) ||
        !preg_match('/^[A-Za-zÀ-ÿ\s\'-]+$/u', $prenom)
    ) {

        $error =
            "Le nom et le prénom ne doivent pas contenir de chiffres.";

    } elseif (
        !preg_match('/^[0-9]{10}$/', $telephone)
    ) {

        $error =
            "Le téléphone doit contenir exactement 10 chiffres. Exemple : 0600000000.";

    } else {

        try {

            $pdo->beginTransaction();


            // ===============================
            // Modifier UTILISATEUR
            // ===============================

            $stmt = $pdo->prepare("
                UPDATE utilisateur

                SET
                    nom = :nom,
                    prenom = :prenom,
                    telephone = :telephone

                WHERE email = :email
            ");

            $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':telephone' => $telephone,
                ':email' => $email
            ]);


            // ===============================
            // Modifier RESPONSABLE
            // ===============================

            $stmt = $pdo->prepare("
                UPDATE responsable_de_jeu

                SET
                    fonction = :fonction,
                    date_embauche = :date_embauche,
                    statut_responsable = :statut

                WHERE email = :email
            ");

            $stmt->execute([
                ':fonction' => $fonction,
                ':date_embauche' =>
                    !empty($date_embauche)
                    ? $date_embauche
                    : null,

                ':statut' => $statut,
                ':email' => $email
            ]);


            $pdo->commit();

            header("Location: index.php");
            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                "Erreur : " .
                $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Modifier responsable</title>

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

</head>

<body>


<?php
require_once __DIR__ . '/../includes/sidebar.php';
?>


<main>

    <div class="page-header">

        <div>

            <h1>
                Modifier responsable
            </h1>

            <p>
                Modifier les informations du responsable
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


        <?php if (!empty($error)): ?>

            <div class="alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-grid">


                <!-- NOM -->

                <div class="form-group">

                    <label>
                        Nom *
                    </label>

                    <input
                        type="text"
                        name="nom"

                        value="<?= htmlspecialchars(
                            $_POST['nom']
                            ?? $responsable['nom']
                        ) ?>"

                        minlength="3"

                        pattern="[A-Za-zÀ-ÿ '-]{3,}"

                        title="Minimum 3 lettres, sans chiffres"

                        required
                    >

                </div>


                <!-- PRENOM -->

                <div class="form-group">

                    <label>
                        Prénom *
                    </label>

                    <input
                        type="text"
                        name="prenom"

                        value="<?= htmlspecialchars(
                            $_POST['prenom']
                            ?? $responsable['prenom']
                        ) ?>"

                        minlength="3"

                        pattern="[A-Za-zÀ-ÿ '-]{3,}"

                        title="Minimum 3 lettres, sans chiffres"

                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"

                        value="<?= htmlspecialchars(
                            $responsable['email']
                        ) ?>"

                        disabled
                    >

                </div>


                <!-- TELEPHONE -->

                <div class="form-group">

                    <label>
                        Téléphone *
                    </label>

                    <input
                        type="text"
                        name="telephone"

                        value="<?= htmlspecialchars(
                            $_POST['telephone']
                            ?? $responsable['telephone']
                        ) ?>"

                        minlength="10"
                        maxlength="10"

                        pattern="[0-9]{10}"

                        placeholder="0600000000"

                        title="Le téléphone doit contenir exactement 10 chiffres"

                        required
                    >

                </div>


                <!-- FONCTION -->

                <div class="form-group">

                    <label>
                        Fonction *
                    </label>

                    <input
                        type="text"
                        name="fonction"

                        value="<?= htmlspecialchars(
                            $_POST['fonction']
                            ?? $responsable['fonction']
                        ) ?>"

                        required
                    >

                </div>


                <!-- DATE EMBAUCHE -->

                <div class="form-group">

                    <label>
                        Date d'embauche
                    </label>

                    <input
                        type="date"
                        name="date_embauche"

                        value="<?= htmlspecialchars(
                            $_POST['date_embauche']
                            ?? $responsable['date_embauche']
                        ) ?>"
                    >

                </div>


                <!-- STATUT -->

                <div class="form-group">

                    <label>
                        Statut
                    </label>

                    <?php
                    $statutActuel =
                        $_POST['statut_responsable']
                        ?? $responsable['statut_responsable'];
                    ?>

                    <select name="statut_responsable">

                        <option
                            value="actif"
                            <?= $statutActuel === 'actif'
                                ? 'selected'
                                : '' ?>
                        >
                            Actif
                        </option>

                        <option
                            value="inactif"
                            <?= $statutActuel === 'inactif'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactif
                        </option>

                    </select>

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