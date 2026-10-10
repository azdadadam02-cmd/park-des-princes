<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$email = $_GET['email'] ?? '';

if (empty($email)) {
    header("Location: index.php");
    exit;
}


// vérifier réservations
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservation
    WHERE email_client = :email
");

$stmt->execute([
    ':email' => $email
]);

$nbReservations = $stmt->fetchColumn();


// vérifier commandes
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM commande
    WHERE email_client = :email
");

$stmt->execute([
    ':email' => $email
]);

$nbCommandes = $stmt->fetchColumn();


// empêcher suppression si historique existe
if ($nbReservations > 0 || $nbCommandes > 0) {

    die("
        Impossible de supprimer ce client.
        Il possède des réservations ou des commandes.
    ");
}


try {

    $pdo->beginTransaction();


    // supprimer de CLIENT
    $stmt = $pdo->prepare("
        DELETE FROM client
        WHERE email = :email
    ");

    $stmt->execute([
        ':email' => $email
    ]);


    // supprimer de UTILISATEUR
    $stmt = $pdo->prepare("
        DELETE FROM utilisateur
        WHERE email = :email
    ");

    $stmt->execute([
        ':email' => $email
    ]);


    $pdo->commit();


} catch (PDOException $e) {

    $pdo->rollBack();

    die(
        "Erreur : "
        . $e->getMessage()
    );
}


header("Location: index.php");
exit;