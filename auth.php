<?php
session_start();
require_once 'db.php';

$errors = [];
$action_form = 'login'; // Par défaut, on suppose que c'est le login

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ================== PARTIE LOGIN ==================
    if (isset($_POST['btn_login'])) {
        $action_form = 'login'; // On retient qu'on est dans le Login
        
        $email = trim($_POST['email']);
        $mot_passe = $_POST['mot_passe'];
        
        if (empty($email)) {
            $errors[] = "Il faut entrer ton email.";
        }
        if (empty($mot_passe)) {
            $errors[] = "Il faut entrer ton mot de passe.";
        }
        
        if (empty($errors)) { 
            try {
                $stmt = $pdo->prepare("SELECT * FROM UTILISATEUR WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
                if ($user && password_verify($mot_passe, $user['mot_passe'])){
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    switch ($user['role']) {
                        case 'ADMIN': header("Location: admin_dashboard.php"); break;
                        case 'CLIENT': header("Location: index.php"); break;
                        case 'RESPONSABLE': header("Location: responsable_dashboard.php"); break;
                        case 'AGENT': header("Location: agent_dashboard.php"); break;
                        default: header("Location: login.php");
                    } 
                    exit();
                } else { 
                    $errors[] = "Les informations sont incorrectes."; 
                }
            } catch (PDOException $e) {
                $errors[] = "Problème de connexion.";
            }
        }
    }

    // ================== PARTIE SIGN UP ==================
    elseif (isset($_POST['btn_signup'])) {
        $action_form = 'signup'; // On retient qu'on est dans le Sign Up
        
        $nom_complet = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $mot_passe = $_POST['mot_passe'];
        
        if (empty($email) || empty($mot_passe) || empty($nom_complet)) {
            $errors[] = "Tous les champs sont obligatoires.";
        }
        
        if (empty($nom_complet)) {
            $errors[] = "Le nom est obligatoire.";
        } elseif (strlen($nom_complet) < 3 || strlen($nom_complet) > 50) {
            $errors[] = "Le nom doit contenir entre 3 et 50 caractères.";
        } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/", $nom_complet)) {
            $errors[] = "Le nom ne doit contenir que des lettres.";
        }
        
        if (empty($email)) {
            $errors[] = "L'email est obligatoire.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Le format de l'email est invalide.";
        } elseif (strlen($email) > 100) {
            $errors[] = "L'email est trop long.";
        }
        
        if (empty($mot_passe)) {
            $errors[] = "Le mot de passe est obligatoire.";
        } elseif (strlen($mot_passe) < 8) {
            $errors[] = "le mot de pass au moins 8 caractères.";
        } elseif (!preg_match("#[0-9]+#", $mot_passe)) {
            $errors[] = "le mot de pass au moins un chiffre.";
        } elseif (!preg_match("#[a-zA-Z]+#", $mot_passe)) {
            $errors[] = "le mot de pass au moins une lettre.";
        }

        if (empty($errors)) { 
            $parts = explode(" ", $nom_complet, 2);
            $nom = $parts[0];
            $prenom = isset($parts[1]) ? $parts[1] : 'Client';

            try {
                $checkStmt = $pdo->prepare("SELECT email FROM UTILISATEUR WHERE email = :email");
                $checkStmt->execute([':email' => $email]);
                
                if ($checkStmt->rowCount() > 0) {
                    $errors[] = "Cet email est déjà utilisé.";
                } else {
                    $hashed_password = password_hash($mot_passe, PASSWORD_DEFAULT);
                    $role = 'CLIENT';
                    $date_inscription = date('Y-m-d'); 

                    $pdo->beginTransaction();

                    $stmt1 = $pdo->prepare("INSERT INTO UTILISATEUR (email, mot_passe, nom, prenom, role) VALUES (:email, :mot_passe, :nom, :prenom, :role)");
                    $stmt1->execute([':email' => $email, ':mot_passe' => $hashed_password, ':nom' => $nom, ':prenom' => $prenom, ':role' => $role]);

                    $stmt2 = $pdo->prepare("INSERT INTO CLIENT (email, date_inscription) VALUES (:email, :date_inscription)");
                    $stmt2->execute([':email' => $email, ':date_inscription' => $date_inscription]);

                    $pdo->commit();

                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_nom'] = $nom;
                    $_SESSION['user_role'] = $role;
                    header("Location: index.php");
                    exit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de la création du compte.";
            }
        }
    }

    // ================== REDIRECTION AVEC ERREURS ==================
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        // On envoie un indice dans l'URL pour savoir quel formulaire garder ouvert !
        header("Location: login.php?form=" . $action_form); 
        exit();
    }
}
?>