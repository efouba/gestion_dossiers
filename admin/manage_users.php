<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérification des permissions
$currentUser = getLoggedInUser($pdo);
if (!$currentUser || getUserRole($currentUser) !== 'chef') {
    redirect(TEMPLATES_PATH . '/dashboard.php');
    exit;
}

// Récupérer tous les utilisateurs (sauf l'utilisateur courant)
$query = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY nom, prenom ASC");
$query->execute([$currentUser['id']]);
$users = $query->fetchAll();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Ajouter un utilisateur
        if (isset($_POST['action']) && $_POST['action'] == 'add') {
            $matricule = sanitize($_POST['matricule']);
            $email_pro = sanitize($_POST['email_pro']);
            $nom = sanitize($_POST['nom']);
            $prenom = sanitize($_POST['prenom']);
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $role = sanitize($_POST['role']);

            // Validation supplémentaire
            if (strlen($_POST['password']) < 8) {
                throw new Exception("Le mot de passe doit contenir au moins 8 caractères");
            }

            $query = $pdo->prepare("INSERT INTO users (matricule, email_pro, nom, prenom, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $query->execute([$matricule, $email_pro, $nom, $prenom, $password, $role]);
            
            $_SESSION['success'] = "Utilisateur ajouté avec succès";
        }

        // Supprimer un utilisateur (avec confirmation)
        if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['user_id'])) {
            if (!isset($_POST['confirm_delete'])) {
                throw new Exception("Confirmation de suppression requise");
            }

            $userId = (int)$_POST['user_id'];
            if ($userId === $currentUser['id']) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte");
            }

            $query = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $query->execute([$userId]);
            
            $_SESSION['success'] = "Utilisateur supprimé avec succès";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    redirect('/admin/manage_users.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-users-cog mr-2"></i> Gestion des Utilisateurs
            </h1>
            <nav class="flex items-center space-x-4">
                <a href="<?= TEMPLATES_PATH ?>/dashboard.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                </a>
                <a href="<?= TEMPLATES_PATH ?>/profile.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-user-circle mr-1"></i> Mon profil
                </a>
                <a href="<?= AUTH_PATH ?>/logout.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <!-- Messages de feedback -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Liste des utilisateurs -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-list mr-2"></i> Liste des utilisateurs
                </h2>

                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left">Matricule</th>
                                <th class="px-6 py-3 text-left">Nom</th>
                                <th class="px-6 py-3 text-left">Email</th>
                                <th class="px-6 py-3 text-left">Rôle</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><?= htmlspecialchars($user['matricule']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($user['email_pro']) ?></td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full 
                                        <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                           ($user['role'] === 'chef' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <a href="edit_user.php?id=<?= $user['id'] ?>"
                                            class="text-blue-600 hover:text-blue-800 p-1" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="" class="inline" id="deleteForm<?= $user['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="confirm_delete" value="1">
                                            <button type="button" onclick="confirmDelete(<?= $user['id'] ?>)"
                                                class="text-red-600 hover:text-red-800 p-1" title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Formulaire d'ajout -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-user-plus mr-2"></i> Ajouter un utilisateur
                </h2>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="matricule" class="block text-gray-700 mb-1">Matricule</label>
                                <input type="text" id="matricule" name="matricule"
                                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label for="email_pro" class="block text-gray-700 mb-1">Email professionnel</label>
                                <input type="email" id="email_pro" name="email_pro"
                                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label for="nom" class="block text-gray-700 mb-1">Nom</label>
                                <input type="text" id="nom" name="nom"
                                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label for="prenom" class="block text-gray-700 mb-1">Prénom</label>
                                <input type="text" id="prenom" name="prenom"
                                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label for="password" class="block text-gray-700 mb-1">Mot de passe</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" minlength="8"
                                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500 pr-10"
                                        required>
                                    <button type="button" onclick="togglePasswordVisibility('password')"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-600 hover:text-gray-800">
                                        <i id="password_icon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-gray-500">Minimum 8 caractères</small>
                            </div>
                            <div>
                                <label for="role" class="block text-gray-700 mb-1">Rôle</label>
                                <select id="role" name="role"
                                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                                    <option value="agent">Agent</option>
                                    <option value="chef">Chef</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center">
                                <i class="fas fa-save mr-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 border-t mt-8 py-4">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            <p>Système de gestion - <?= date('Y') ?></p>
        </div>
    </footer>

    <script>
    // Confirmation avant suppression
    function confirmDelete(userId) {
        if (confirm("Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.")) {
            document.getElementById('deleteForm' + userId).submit();
        }
    }

    // Afficher/masquer le mot de passe
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '_icon');

        if (field.type === "password") {
            field.type = "text";
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = "password";
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>

</html>