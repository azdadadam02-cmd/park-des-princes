<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {

    header("Location: index.php");
    exit;

}


$stmt = $pdo->prepare("
    UPDATE jeu
    SET etat = 'hors service'
    WHERE id_jeu = :id
");

$stmt->execute([
    ':id' => $id
]);


header("Location: index.php");
exit;