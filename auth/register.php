<?php
include '../includes/config.php';
include '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a les droits nécessaires
redirectIfNotLoggedIn();

// Seuls les chefs ou admins peuvent accéder à cette page
$userRole = $_SESSION['user_role'] ?? '';
if ($userRole !== 'chef' && $userRole !== 'admin') {
    header('Location: ../templates/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données envoyées par l'utilisateur
    $matricule = sanitize($_POST['matricule']);
    $email_pro = sanitize($_POST['email_pro']);
    $nom = sanitize($_POST['nom']);
    $prenom = sanitize($_POST['prenom']);
    $role = sanitize($_POST['role']); // Nouveau champ pour le rôle
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    try {
        // Vérifier si l'utilisateur existe déjà
        $checkQuery = $pdo->prepare("SELECT id FROM users WHERE email_pro = ? OR matricule = ?");
        $checkQuery->execute([$email_pro, $matricule]);
        
        if ($checkQuery->rowCount() > 0) {
            $error_message = "Un utilisateur avec cet email ou matricule existe déjà";
        } else {
            // Insérer les données dans la table "users"
            $query = $pdo->prepare("INSERT INTO users (matricule, email_pro, nom, prenom, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $query->execute([$matricule, $email_pro, $nom, $prenom, $password, $role]);

            // Rediriger avec un message de succès
            $_SESSION['success_message'] = "Utilisateur créé avec succès";
            header("Location: register.php");
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de l'inscription : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Utilisateur</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="container mx-auto p-6">
        <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold text-center mb-6">
                <i class="fas fa-user-plus mr-2"></i>Créer un nouveau compte
            </h1>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $error_message; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="matricule" class="block text-gray-700 mb-2">Matricule <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="matricule" name="matricule"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                    </div>

                    <div>
                        <label for="email_pro" class="block text-gray-700 mb-2">Email professionnel <span
                                class="text-red-500">*</span></label>
                        <input type="email" id="email_pro" name="email_pro"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="nom" class="block text-gray-700 mb-2">Nom <span
                                    class="text-red-500">*</span></label>
                            <input type="text" id="nom" name="nom"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                        </div>
                        <div>
                            <label for="prenom" class="block text-gray-700 mb-2">Prénom <span
                                    class="text-red-500">*</span></label>
                            <input type="text" id="prenom" name="prenom"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                        </div>
                    </div>

                    <div>
                        <label for="role" class="block text-gray-700 mb-2">Rôle <span
                                class="text-red-500">*</span></label>
                        <select id="role" name="role"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="agent">Agent</option>
                            <option value="chef">Chef</option>
                            <?php if ($userRole === 'admin'): ?>
                            <option value="admin">Administrateur</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label for="password" class="block text-gray-700 mb-2">Mot de passe <span
                                class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required minlength="8">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 caractères</p>
                    </div>

                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> Créer le compte
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>

</html>