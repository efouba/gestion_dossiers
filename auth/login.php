<?php
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérifier si des utilisateurs existent
$checkUsers = $pdo->query("SELECT id FROM users LIMIT 1");
if ($checkUsers->rowCount() === 0) {
    redirect('/auth/install.php'); // Utilisation de redirect() au lieu de header()
    // exit inutile car déjà dans redirect()
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_pro = sanitize($_POST['email_pro']);
    $password = $_POST['password'];

    $query = $pdo->prepare("SELECT * FROM users WHERE email_pro = ? AND is_active = 1");
    $query->execute([$email_pro]);
    $user = $query->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Authentification réussie
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        // Redirection sécurisée vers le dashboard - CORRECTION ICI
        redirect('/templates/dashboard.php'); // Chemin relatif depuis la racine du site
        // exit inutile car déjà dans redirect()
    } else {
        $error_message = "Identifiants incorrects ou compte désactivé";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion des Dossiers</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-blue-600 text-white py-4 px-6">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-sign-in-alt mr-2"></i> Connexion
            </h1>
        </div>

        <div class="p-6">
            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="mb-4">
                    <label for="email_pro" class="block text-gray-700 font-medium mb-2">
                        <i class="fas fa-envelope mr-1"></i> Email professionnel
                    </label>
                    <input type="email" id="email_pro" name="email_pro"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required autofocus>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">
                        <i class="fas fa-lock mr-1"></i> Mot de passe
                    </label>
                    <input type="password" id="password" name="password"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="forgot_password.php" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-question-circle mr-1"></i> Mot de passe oublié ?
                </a>
            </div>
        </div>
    </div>
</body>

</html>