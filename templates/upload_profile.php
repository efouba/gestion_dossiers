<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérification de l'authentification
$user = getLoggedInUser($pdo);
if (!$user) {
    $_SESSION['error'] = "Authentification requise";
    redirect('/auth/login.php');
}

// Vérification du fichier uploadé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['profile_picture'];
    $fileName = $user['id'] . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filePath = $uploadDir . $fileName;
    $allowedTypes = ['image/jpeg', 'image/png'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Validation
    if (!in_array($file['type'], $allowedTypes)) {
        $_SESSION['error'] = "Seuls les fichiers JPEG et PNG sont autorisés";
    } elseif ($file['size'] > $maxSize) {
        $_SESSION['error'] = "Le fichier ne doit pas dépasser 2MB";
    } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $_SESSION['error'] = "Erreur lors de l'upload du fichier";
    } else {
        // Suppression de l'ancienne photo si elle existe
        if (!empty($user['photo_profil']) && file_exists($uploadDir . $user['photo_profil'])) {
            unlink($uploadDir . $user['photo_profil']);
        }

        // Mise à jour en base de données
        try {
            $query = $pdo->prepare("UPDATE users SET photo_profil = ? WHERE id = ?");
            $query->execute([$fileName, $user['id']]);
            $_SESSION['success'] = "Photo de profil mise à jour avec succès";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la mise à jour du profil";
            unlink($filePath); // Supprimer le fichier uploadé en cas d'erreur
        }
    }
}

redirect('/templates/profile.php');