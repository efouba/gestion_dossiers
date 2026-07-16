<?php
/**
 * Fonctions utilitaires pour l'application
 * 
 * @package Application
 */

// Activation du rapport d'erreurs strict
declare(strict_types=1);

/**
 * Démarre une session sécurisée
 */
// function startSecureSession(): void {
//     if (session_status() === PHP_SESSION_NONE) {
//         // Configuration sécurisée des cookies de session
//         $sessionName = 'SECURE_SESSION_' . md5(__DIR__);
//         $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
//         $httponly = true;
        
//         session_name($sessionName);
//         session_set_cookie_params([
//             'lifetime' => 86400, // 1 jour
//             'path' => '/',
//             'domain' => $_SERVER['HTTP_HOST'],
//             'secure' => $secure,
//             'httponly' => $httponly,
//             'samesite' => 'Strict'
//         ]);
        
//         ini_set('session.cookie_httponly', '1');
//         ini_set('session.cookie_secure', $secure ? '1' : '0');
//         ini_set('session.use_strict_mode', '1');
//         ini_set('session.cookie_samesite', 'Strict');
        
//         session_start();
        
//         // Régénération périodique de l'ID de session
//         if (!isset($_SESSION['CREATED'])) {
//             $_SESSION['CREATED'] = time();
//         } elseif (time() - $_SESSION['CREATED'] > 1800) { // 30 minutes
//             session_regenerate_id(true);
//             $_SESSION['CREATED'] = time();
//         }
        
//         // Protection contre le fixation de session
//         if (!isset($_SESSION['initiated'])) {
//             session_regenerate_id();
//             $_SESSION['initiated'] = true;
//         }
//     }
// }

function startSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionName = 'SECURE_SESSION';
    $secure = true;
    $httponly = true;

    // Configuration session
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Strict'
    ]);

    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $secure ? 1 : 0);
    ini_set('session.use_strict_mode', 1);

    session_name($sessionName);
    
    if (!session_start()) {
        throw new Exception("Impossible de démarrer la session");
    }

    // Régénération ID
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isLoggedIn(): bool {
    return isset(
        $_SESSION['user_id'], 
        $_SESSION['user_role'], 
        $_SESSION['ip_address'], 
        $_SESSION['user_agent']
    ) && $_SESSION['ip_address'] === $_SERVER['REMOTE_ADDR']
    && $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'];
}

/**
 * Redirige les utilisateurs non connectés
 */
function redirectIfNotLoggedIn(): void {
    if (!isLoggedIn()) {
        redirect(getBaseUrl() . '/auth/login.php');
    }
}

/**
 * Récupère les informations de l'utilisateur connecté
 * @param PDO $pdo Instance PDO
 * @return array|null
 */
function getLoggedInUser(PDO $pdo): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $query = $pdo->prepare("
            SELECT id, matricule, email_pro, nom, prenom, role, created_at 
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $query->execute([$_SESSION['user_id']]);
        $user = $query->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getLoggedInUser: ' . $e->getMessage());
        return null;
    }
}

/**
 * Nettoie les données utilisateur
 * @param mixed $data Donnée à nettoyer
 * @param bool $stripTags Si vrai, supprime les balises HTML
 * @return mixed
 */
function sanitize($data, bool $stripTags = true) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    
    $data = trim($data);
    if ($stripTags) {
        $data = strip_tags($data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Vérifie et retourne le rôle de l'utilisateur
 * @param array|null $user
 * @return string
 */
function getUserRole(?array $user): string {
    if ($user && isset($user['role'])) {
        $role = strtolower($user['role']);
        if (in_array($role, ['admin', 'chef', 'agent'], true)) {
            return $role;
        }
    }
    return 'guest'; // Rôle par défaut
}

/**
 * Redirection sécurisée
 * @param string $url URL de destination
 * @param int $statusCode Code HTTP (303 par défaut)
 */
function redirect(string $url, int $statusCode = 303): void {
    // Nettoyer l'URL
    $url = trim($url);
    
    // Si l'URL est déjà absolue (commence par http), l'utiliser directement
    if (strpos($url, 'http') === 0) {
        header("Location: $url", true, $statusCode);
    } 
    // Sinon, utiliser BASE_URL comme préfixe
    else {
        // Supprimer les doubles slashes accidentels
        $redirectUrl = BASE_URL . '/' . ltrim($url, '/');
        $redirectUrl = str_replace('//', '/', $redirectUrl);
        $redirectUrl = str_replace(':/', '://', $redirectUrl);
        
        header("Location: " . $redirectUrl, true, $statusCode);
    }
    exit;
}



// /**
//  * Génère l'URL de base de l'application
//  * @return string
//  */
// function getBaseUrl(): string {
//     $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
//     return "$protocol://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']);
// }

/**
 * Vérifie les permissions utilisateur
 * @param string $requiredRole
 * @return bool
 */
function checkPermission(string $requiredRole): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    $hierarchy = [
        'admin' => 3,
        'chef' => 3,
        'agent' => 1,
        'guest' => 0
    ];
    
    $userRole = $_SESSION['user_role'] ?? 'guest';
    return ($hierarchy[$userRole] ?? 0) >= ($hierarchy[$requiredRole] ?? 0);
}
function getBaseUrl(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    return $protocol . $host . $path;
}