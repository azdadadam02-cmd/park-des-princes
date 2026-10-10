<?php
// À lancer UNE SEULE FOIS dans le navigateur : http://localhost/<projet>/creer_responsable_test.php
// Puis SUPPRIMER ce fichier.
require_once 'db.php';

$email = 'responsable1@exemple.com';
$mdp   = 'Test1234';

$pdo->beginTransaction();
$pdo->prepare("INSERT IGNORE INTO UTILISATEUR (email, mot_passe, nom, prenom, role) VALUES (?,?,?,?, 'RESPONSABLE')")
    ->execute([$email, password_hash($mdp, PASSWORD_DEFAULT), 'Alaoui', 'Youssef']);
$pdo->prepare("INSERT IGNORE INTO RESPONSABLE_DE_JEU (email, fonction, date_embauche, statut_responsable) VALUES (?, 'Responsable manège', CURDATE(), 'actif')")
    ->execute([$email]);

$pdo->exec("INSERT IGNORE INTO CATEGORIE (id_categorie, nom_categorie, type_categorie) VALUES (1, 'Sensations fortes', 'manège')");
$pdo->prepare("INSERT INTO JEU (nom, description, age_min, capacite, duree_session, tarif, etat, date_mise_service, id_categorie, email_responsable)
               VALUES ('Grande Roue', 'Vue panoramique du parc', 0, 30, 10, 40.00, 'disponible', CURDATE(), 1, ?)")
    ->execute([$email]);
$pdo->commit();

echo "OK. Connexion : $email / $mdp  (supprime ce fichier maintenant)";
