<?php
// À lancer UNE SEULE FOIS dans le navigateur : http://localhost/<projet>/creer_agent_test.php
// Puis SUPPRIMER ce fichier.
// Prérequis : database_park.sql importé, ou migration_commande_reservation.sql exécuté sur une base existante.
require_once 'db.php';

$mdp = 'Test1234';
$hash = password_hash($mdp, PASSWORD_DEFAULT);

$pdo->beginTransaction();

// Agent de vente de démonstration
$pdo->prepare("INSERT IGNORE INTO utilisateur (email, mot_passe, nom, prenom, role) VALUES ('agent1@exemple.com', ?, 'Idrissi', 'Salma', 'AGENT')")
    ->execute([$hash]);
$pdo->exec("INSERT IGNORE INTO agent_de_vente (email) VALUES ('agent1@exemple.com')");

// Client de démonstration (données fictives)
$pdo->prepare("INSERT IGNORE INTO utilisateur (email, mot_passe, nom, prenom, telephone, role) VALUES ('client1@exemple.com', ?, 'Bennani', 'Sara', '0600000000', 'CLIENT')")
    ->execute([$hash]);
$pdo->exec("INSERT IGNORE INTO client (email, date_inscription) VALUES ('client1@exemple.com', CURDATE())");

// Démo : une réservation liée à une commande en attente et à deux billets.
// La visite est prévue dans 4 jours, le paiement au guichet demain.
$id_jeu = $pdo->query("SELECT id_jeu FROM jeu ORDER BY id_jeu LIMIT 1")->fetchColumn();
$deja   = $pdo->query("SELECT COUNT(*) FROM commande WHERE email_client = 'client1@exemple.com' AND statut_commande = 'en attente'")->fetchColumn();
if ($id_jeu && !$deja) {
    $pdo->prepare(
        "INSERT INTO reservation (date_visite, nb_personnes, montant, statut_reservation, email_client)
         VALUES (DATE_ADD(NOW(), INTERVAL 4 DAY), 2, 150.00, 'en attente', 'client1@exemple.com')"
    )->execute();
    $num_res = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO choisir (num_reservation, id_jeu) VALUES (?, ?)")->execute([$num_res, $id_jeu]);

    $pdo->prepare(
        "INSERT INTO commande (montant_total, statut_commande, email_client, date_paiment, num_reservation)
         VALUES (150.00, 'en attente', 'client1@exemple.com', DATE_ADD(CURDATE(), INTERVAL 1 DAY), ?)"
    )->execute([$num_res]);
    $num_cmd = (int)$pdo->lastInsertId();
    foreach ([['Adulte', 100.00], ['Enfant', 50.00]] as [$type, $prix]) {
        $pdo->prepare("INSERT INTO billet (date_fin, type, statut_billet, quantite, prix_unitaire, num_commande)
                       VALUES (DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 23 HOUR + INTERVAL 59 MINUTE, ?, 'en attente', 1, ?, ?)")
            ->execute([$type, $prix, $num_cmd]);
        $id_b = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO contient (id_billet, id_jeu) VALUES (?, ?)")->execute([$id_b, $id_jeu]);
    }
}

$pdo->commit();

echo "OK. Connexion agent : agent1@exemple.com / $mdp (supprime ce fichier maintenant)";
