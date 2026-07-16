<?php
// include '../includes/config.php';
require __DIR__ . '/../includes/config.php'; // Chemin absolu plus fiable

// Empêcher l'accès si un admin existe déjà
$checkAdmin = $pdo->query("SELECT id FROM users WHERE role = 'chef' LIMIT 1");
if ($checkAdmin->rowCount() > 0) {
    header("HTTP/1.1 403 Forbidden");
    exit("Le système a déjà été initialisé");
}
// Vérifier si la table users existe et est vide
$check = $pdo->query("SELECT 1 FROM users LIMIT 1");
$isFirstUser = ($check->rowCount() === 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $isFirstUser) {
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    $query = $pdo->prepare("
        INSERT INTO users 
        (matricule, email_pro, nom, prenom, password, role, created_at) 
        VALUES (?, ?, ?, ?, ?, 'chef', NOW())
    ");
    $query->execute([
        $_POST['matricule'],
        $_POST['email_pro'],
        $_POST['nom'],
        $_POST['prenom'],
        $password
    ]);

    // Rediriger vers la page de login
    // header("Location: login.php");
    header("Location: /auth/login.php"); // Chemin absolu depuis la racine
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Initialisation du système</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <?php if ($isFirstUser): ?>
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-center">Création du compte administrateur</h1>
        <p class="mb-6 text-gray-600">Bienvenue dans le système. Veuillez créer le premier compte administrateur.</p>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Matricule</label>
                <input type="text" name="matricule" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Email professionnel</label>
                <input type="email" name="email_pro" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Nom</label>
                <input type="text" name="nom" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Prénom</label>
                <input type="text" name="prenom" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 mb-2">Mot de passe</label>
                <input type="password" name="password" class="w-full px-4 py-2 border rounded" required minlength="8">
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                Créer le compte admin
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full text-center">
        <h1 class="text-2xl font-bold mb-4">Le système est déjà initialisé</h1>
        <p class="mb-4">Un compte administrateur existe déjà.</p>
        <a href="/auth/login.php" class="text-blue-500 hover:underline">Aller à la page de connexion</a>
    </div>
    <?php endif; ?>
</body>

</html>