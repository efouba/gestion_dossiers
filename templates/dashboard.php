<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();
// redirectIfNotLoggedIn();


// Récupérer l'utilisateur connecté avec vérification
// Vérification de l'utilisateur
$user = getLoggedInUser($pdo);
if (!$user) {
    session_destroy();
    redirect(AUTH_PATH . '/login.php');
}

// Vérifier les permissions selon le rôle
$userRole = getUserRole($user);
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des Dossiers</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-full flex flex-col">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-folder-open text-xl"></i>
                <h1 class="text-xl font-bold">Gestion des Dossiers</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden md:inline">
                    <i class="fas fa-user-circle mr-1"></i>
                    <?= htmlspecialchars($user['prenom'] . ' ' . htmlspecialchars($user['nom']) )?>
                    <span class="ml-2 px-2 py-1 bg-blue-700 rounded-full text-xs">
                        <?= strtoupper(htmlspecialchars($userRole)) ?>
                    </span>
                </span>
                <a href="profile.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-user-circle mr-2"></i> Mon profile
                </a>
                <a href="" class="bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <div class="max-w-6xl mx-auto">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">
                <i class="fas fa-tachometer-alt mr-2 text-blue-500"></i>Tableau de bord
            </h2>
            <p class="text-gray-600 mb-6">Bienvenue dans votre espace de travail</p>

            <!-- Cartes de fonctionnalités -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Gestion des dossiers -->
                <a href="list_dossiers.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-blue-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-blue-100 p-3 rounded-full mr-3">
                                <i class="fas fa-folder-open text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Gestion des dossiers</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Consultez, ajoutez ou traitez les dossiers</p>
                    </div>
                </a>

                <!-- Tâches -->
                <a href="tasks.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-green-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-green-100 p-3 rounded-full mr-3">
                                <i class="fas fa-tasks text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Suivi des tâches</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Visualisez vos tâches en cours</p>
                    </div>
                </a>

                <!-- Archives -->
                <a href="archives.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-purple-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-purple-100 p-3 rounded-full mr-3">
                                <i class="fas fa-archive text-purple-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Archives</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Accédez aux dossiers archivés</p>
                    </div>
                </a>

                <?php if (checkPermission('chef')): ?>
                <!-- Gestion des tâches (Chef/Admin) -->
                <a href="../admin/manage_tasks.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-yellow-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-yellow-100 p-3 rounded-full mr-3">
                                <i class="fas fa-clipboard-list text-yellow-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Gestion des tâches</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Attribuez et suivez les tâches</p>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (checkPermission('chef')): ?>
                <!-- Gestion utilisateurs (Admin) -->
                <a href="../admin/manage_users.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-red-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-red-100 p-3 rounded-full mr-3">
                                <i class="fas fa-users-cog text-red-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Gestion Utilisateurs</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Gérez les comptes utilisateurs</p>
                    </div>
                </a>

                <!-- Statistiques (Admin) -->
                <a href="../admin/stats.php"
                    class="bg-white rounded-lg shadow-md hover:shadow-lg transition overflow-hidden border-l-4 border-indigo-500">
                    <div class="p-5">
                        <div class="flex items-center mb-3">
                            <div class="bg-indigo-100 p-3 rounded-full mr-3">
                                <i class="fas fa-chart-bar text-indigo-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Statistiques</h3>
                        </div>
                        <p class="text-gray-600 text-sm">Visualisez les rapports</p>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>© <?= date('Y') ?> Gestion des Dossiers. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>