<?php

session_start();

require_once '../config/database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "
        SELECT u.*
        FROM utilisateur u

        INNER JOIN admin a
        ON u.email = a.email

        WHERE u.email = :email
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':email' => $email
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $admin &&
        password_verify(
            $password,
            $admin['mot_passe']
        )
    ) {

        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_nom'] = $admin['nom'];
        $_SESSION['admin_prenom'] = $admin['prenom'];

        header("Location: dashboard.php");
        exit;

    } else {

        $error = "Email ou mot de passe incorrect.";

    }
}

?>











<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Connexion Admin</title>
</head>

<body>

<h2>Connexion administrateur</h2>

<?php if ($error): ?>

    <p>
        <?= htmlspecialchars($error) ?>
    </p>

<?php endif; ?>

<form method="POST">

    <label>Email</label>

    <input
        type="email"
        name="email"
        required
    >

    <br><br>

    <label>Mot de passe</label>

    <input
        type="password"
        name="password"
        required
    >

    <br><br>

    <button type="submit">
        Se connecter
    </button>

</form>

</body>
</html>