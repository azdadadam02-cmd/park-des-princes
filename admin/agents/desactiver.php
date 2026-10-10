<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$email =
    $_GET['email'] ?? '';

if (empty($email)) {

    header("Location: index.php");
    exit;

}


try {

    $pdo->beginTransaction();


    // supprimer rôle agent
    $stmt = $pdo->prepare("
        DELETE FROM agent_de_vente

        WHERE email = :email
    ");

    $stmt->execute([
        ':email' => $email
    ]);


    // désactiver le rôle utilisateur
    $stmt = $pdo->prepare("
        UPDATE utilisateur

        SET role = 'inactif'

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