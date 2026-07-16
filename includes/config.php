<?php
$host = 'sql305.infinityfree.com';
$db = 'if0_38704426_gestion_dossiers';
$user = 'if0_38704426';
$password = 'byt1EZG5Piark';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>