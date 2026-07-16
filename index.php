<?php
// Active le buffering et les erreurs en haut du fichier
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers nécessaires
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/path.php';

// Debug temporaire - À supprimer après résolution
echo "<pre>";
echo "BASE_URL: " . BASE_URL . "\n";
echo "AUTH_PATH: " . AUTH_PATH . "\n";
echo "PDO Connection: " . ($pdo ? "OK" : "FAILED") . "\n";
echo "</pre>";

// Démarrer la session
try {
    startSecureSession();
} catch (Exception $e) {
    die("Erreur de session: " . $e->getMessage());
}

// Vérification DB
if (!isset($pdo)) {
    die("Erreur: Connexion DB non initialisée");
}

// Premier démarrage
if (isFirstRun($pdo)) {
    header("Location: /auth/install.php");
    exit;
}

// Redirection selon authentification
if (isLoggedIn()) {
    $dashboardPage = getDashboardPageForUser($_SESSION['user_role'] ?? '');
    header("Location: " . $dashboardPage);
} else {
    header("Location: /auth/login.php");
}
exit;

/**
 * Fonctions supplémentaires pour la gestion des sessions et redirections
 */

 

function isFirstRun(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
        return $stmt->rowCount() === 0;
    } catch (PDOException $e) {
        error_log("Erreur de vérification de première exécution: " . $e->getMessage());
        return false;
    }
}

// function isLoggedIn(): bool {
//     return isset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['ip_address']) 
//            && $_SESSION['ip_address'] === $_SERVER['REMOTE_ADDR'];
// }

function getDashboardPageForUser(string $role): string {
    $pages = [
        'admin' => '/templates/admin/dashboard.php',
        'chef' => '/templates/chef/dashboard.php',
        'agent' => '/templates/agent/dashboard.php'
    ];
    
    return $pages[$role] ?? '/templates/dashboard.php';
}

// function getBaseUrl(): string {
//     $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
//     $host = $_SERVER['HTTP_HOST'];
//     $path = dirname($_SERVER['SCRIPT_NAME']);
//     return $protocol . $host . $path;
// }