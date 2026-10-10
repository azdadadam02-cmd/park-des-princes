<?php
session_start(); 

// Récupération des erreurs
$erreurs_a_afficher = [];
if (isset($_SESSION['login_errors']) && !empty($_SESSION['login_errors'])) {
    $erreurs_a_afficher = $_SESSION['login_errors'];
    unset($_SESSION['login_errors']); 
}

// On regarde quel formulaire a causé l'erreur
$form_action = isset($_GET['form']) ? $_GET['form'] : 'login';

// Si c'est le Sign Up, on prépare la classe d'animation pour le garder ouvert
// (Vérifie que c'est bien la classe "right-panel-active" que tu utilises dans ton login.js)
$container_class = ($form_action === 'signup') ? 'right-panel-active' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parc des Princes - Login</title>
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
</head>
<body>
    
    <!-- On ajoute la classe ici dynamiquement si on vient de faire une erreur dans Sign Up -->
    <div class="cadrLogin <?php echo $container_class; ?>" id="mainContainer">
        
        <!-- ================= FORMULAIRE SIGN UP ================= -->
        <div class="formLogin">
            <img src="img/logo.png" alt="Logo" class="logoLoginR" >
            <form action="auth.php" method="POST" class="form1">
                <h1>Créer Compte</h1>
                
                <?php
                // On affiche les erreurs ICI seulement si ça vient du Sign up
                if ($form_action === 'signup' && !empty($erreurs_a_afficher)) {
                    echo '<div class="error-box">';
                    foreach ($erreurs_a_afficher as $error) {
                        echo '<p class="error-text">' . htmlspecialchars($error) . '</p>';
                    }
                    echo '</div>';
                }
                ?>

                <input type="text" name="nom" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="mot_passe" placeholder="password" required>
                <button type="submit" name="btn_signup">sign up</button>
            </form>
        </div>

        <!-- ================= FORMULAIRE LOGIN ================= -->
        <div class="formSingin">
            <img src="img/logo.png" alt="Logo" class="logoLoginL" >
            <form action="auth.php" method="POST" class="form2">
                <h1>Se Connecter</h1>
                
                <?php
                // On affiche les erreurs ICI seulement si ça vient du Login
                if ($form_action === 'login' && !empty($erreurs_a_afficher)) {
                    echo '<div class="error-box">';
                    foreach ($erreurs_a_afficher as $error) {
                        echo '<p class="error-text">' . htmlspecialchars($error) . '</p>';
                    }
                    echo '</div>';
                }
                ?>

                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="mot_passe" placeholder="password" required>
                <button type="submit" name="btn_login">login</button>
            </form>
        </div>

        <!-- ================= OVERLAYS (Plus d'erreurs ici !) ================= -->
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlayRight">
                    <h1>Welcome Back!</h1>
                    <p>Nice to have you back hero, let's login and enjoy.</p>
                    <button class="ghost" id="signUpBtn">Sign Up</button>
                </div>
                
                <div class="overlayLeft">
                    <h1>Joining us!</h1>
                    <p> You don't have any account, easy create now</p>
                    <button class="ghost" id="signInBtn">Sign In</button> 
                </div>
            </div>
        </div>
    </div>
    
    <script src="login.js"></script>
</body>
</html>