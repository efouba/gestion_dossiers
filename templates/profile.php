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
    redirect('/auth/login.php');
}

// Messages de feedback
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = sanitize($_POST['nom']);
    $prenom = sanitize($_POST['prenom']);
    $email = sanitize($_POST['email']);
    $telephone = sanitize($_POST['telephone']);

    try {
        $query = $pdo->prepare("
            UPDATE users 
            SET nom = ?, prenom = ?, email_pro = ?, telephone = ?
            WHERE id = ?
        ");
        $query->execute([$nom, $prenom, $email, $telephone, $user['id']]);
        
        $_SESSION['success'] = "Profil mis à jour avec succès";
        redirect('/templates/profile.php');
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Debug: Afficher les valeurs reçues
    error_log("Tentative de changement de mot de passe pour l'utilisateur ID: " . $user['id']);
    error_log("Mot de passe actuel saisi: " . $currentPassword);
    error_log("Nouveau mot de passe saisi: " . $newPassword);
    error_log("Confirmation mot de passe: " . $confirmPassword);

    // Vérification initiale des erreurs
    $error = null;

    // Récupération des données fraîches de l'utilisateur
    try {
        $query = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $query->execute([$user['id']]);
        $freshUserData = $query->fetch();

        if (!$freshUserData) {
            throw new Exception("Utilisateur introuvable en base de données");
        }

        // Debug: Afficher le hash stocké
        error_log("Hash du mot de passe en base: " . $freshUserData['password']);

        // Vérification du mot de passe actuel
        $passwordVerified = password_verify($currentPassword, $freshUserData['password']);
        error_log("Résultat vérification mot de passe: " . ($passwordVerified ? 'OK' : 'Échec'));

        if (!$passwordVerified) {
            $error = "Mot de passe actuel incorrect";
        }
        // Vérification que le nouveau mot de passe est différent de l'actuel
        elseif (password_verify($newPassword, $freshUserData['password'])) {
            $error = "Le nouveau mot de passe doit être différent de l'actuel";
        }
        // Vérification de la correspondance des nouveaux mots de passe
        elseif ($newPassword !== $confirmPassword) {
            $error = "Les nouveaux mots de passe ne correspondent pas";
        } 
        // Vérification de la longueur minimale
        elseif (strlen($newPassword) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères";
        }

    } catch (PDOException $e) {
        error_log("Erreur base de données: " . $e->getMessage());
        $error = "Erreur lors de la vérification des informations";
    } catch (Exception $e) {
        error_log("Erreur: " . $e->getMessage());
        $error = $e->getMessage();
    }

    // Si aucune erreur n'a été détectée
    if (!$error) {
        try {
            // Vérification supplémentaire pour les mots de passe faibles
            if (isWeakPassword($newPassword)) {
                $error = "Le mot de passe est trop faible. Utilisez des lettres, chiffres et caractères spéciaux";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                // Debug: Afficher le nouveau hash
                error_log("Nouveau hash généré: " . $hashedPassword);

                $query = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $query->execute([$hashedPassword, $user['id']]);
                
                $_SESSION['success'] = "Mot de passe changé avec succès";
                redirect('/templates/profile.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
            $error = "Une erreur technique est survenue. Veuillez réessayer.";
        }
    }
}

// Fonction pour vérifier la force du mot de passe
function isWeakPassword($password) {
    // Vérifie s'il contient au moins un chiffre
    if (!preg_match('/[0-9]/', $password)) {
        error_log("Mot de passe faible: aucun chiffre détecté");
        return true;
    }
    // Vérifie s'il contient au moins une lettre majuscule
    if (!preg_match('/[A-Z]/', $password)) {
        error_log("Mot de passe faible: aucune majuscule détectée");
        return true;
    }
    // Vérifie s'il contient au moins un caractère spécial
    if (!preg_match('/[\W]/', $password)) {
        error_log("Mot de passe faible: aucun caractère spécial détecté");
        return true;
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-user-circle mr-2"></i> Mon Profil
            </h1>
            <nav class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                </a>
                <a href="../auth/logout.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <!-- Messages d'alerte -->
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $success ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informations du profil -->
            <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-user-edit mr-2 text-blue-500"></i> Informations personnelles
                </h2>

                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="nom" class="block text-gray-700 mb-1">Nom</label>
                            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>"
                                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="prenom" class="block text-gray-700 mb-1">Prénom</label>
                            <input type="text" id="prenom" name="prenom"
                                value="<?= htmlspecialchars($user['prenom']) ?>"
                                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="email" class="block text-gray-700 mb-1">Email professionnel</label>
                            <input type="email" id="email" name="email"
                                value="<?= htmlspecialchars($user['email_pro']) ?>"
                                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="telephone" class="block text-gray-700 mb-1">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone"
                                value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" name="update_profile"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-1"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>

            <!-- Photo de profil -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-camera mr-2 text-blue-500"></i> Photo de profil
                </h2>

                <div class="flex flex-col items-center">
                    <div class="w-32 h-32 bg-gray-200 rounded-full mb-4 overflow-hidden">
                        <?php if (!empty($user['photo_profil'])): ?>
                        <img src="../uploads/profiles/<?= htmlspecialchars($user['photo_profil']) ?>"
                            alt="Photo de profil" class="w-full h-full object-cover">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-4xl text-gray-400">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="upload_profile.php" enctype="multipart/form-data" class="w-full">
                        <div class="mb-2">
                            <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png"
                                class="hidden">
                            <label for="profile_picture"
                                class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded cursor-pointer transition">
                                <i class="fas fa-upload mr-1"></i> Changer la photo
                            </label>
                        </div>
                        <small class="text-gray-500 block text-center">JPEG ou PNG, max 2MB</small>
                    </form>
                </div>
            </div>

            <!-- Changement de mot de passe -->
            <!-- Changement de mot de passe -->
            <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-lock mr-2 text-blue-500"></i> Changement de mot de passe
                </h2>

                <form method="POST" action="">
                    <div class="space-y-4">
                        <div class="mb-4">
                            <label for="current_password" class="block text-gray-700 mb-1">Mot de passe actuel</label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password" required
                                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                                    placeholder="Saisissez votre mot de passe actuel">
                                <button type="button" onclick="togglePasswordVisibility('current_password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-600 hover:text-gray-800">
                                    <i id="current_password_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="new_password" class="block text-gray-700 mb-1">Nouveau mot de passe</label>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password" required minlength="8"
                                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                                    placeholder="Saisissez votre nouveau mot de passe">
                                <button type="button" onclick="togglePasswordVisibility('new_password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-600 hover:text-gray-800">
                                    <i id="new_password_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-gray-500">Minimum 8 caractères</small>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="block text-gray-700 mb-1">Confirmer le nouveau mot de
                                passe</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" required
                                    minlength="8"
                                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                                    placeholder="Confirmez votre nouveau mot de passe">
                                <button type="button" onclick="togglePasswordVisibility('confirm_password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-600 hover:text-gray-800">
                                    <i id="confirm_password_icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button type="submit" name="change_password"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                            <i class="fas fa-key mr-1"></i> Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>

            <!-- Informations de compte -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i> Informations du compte
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-gray-500 text-sm">Rôle</label>
                        <p class="font-medium">
                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                            <?php if ($user['role'] === 'admin'): ?>
                            <span
                                class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded ml-2">Administrateur</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm">Date de création</label>
                        <p><?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm">Dernière connexion</label>

                        </p>
                    </div>
                </div>
            </div>

            <!-- Activité récente (optionnel) -->
            <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-3">
                <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-history mr-2 text-blue-500"></i> Activité récente
                </h2>

                <div class="space-y-4">
                    <?php
                    // Exemple d'activité - à remplacer par une vraie requête
                    $activities = [
                        ['date' => '2023-05-15 14:30', 'action' => 'Connexion au système', 'icon' => 'sign-in-alt'],
                        ['date' => '2023-05-14 10:15', 'action' => 'Modification du profil', 'icon' => 'user-edit'],
                        ['date' => '2023-05-12 16:45', 'action' => 'Consultation de dossier', 'icon' => 'folder-open'],
                    ];
                    
                    foreach ($activities as $activity): ?>
                    <div class="flex items-start">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-<?= $activity['icon'] ?> text-blue-600"></i>
                        </div>
                        <div>
                            <p class="font-medium"><?= $activity['action'] ?></p>
                            <p class="text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($activity['date'])) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-4">
                        <a href="#" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-list mr-1"></i> Voir tout l'historique
                        </a>
                    </div>
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
    // Prévisualisation de la photo de profil
    document.getElementById('profile_picture').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.querySelector('.w-32.h-32 img')?.setAttribute('src', event.target.result);
            };
            reader.readAsDataURL(this.files[0]);
            this.form.submit();
        }
    });

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