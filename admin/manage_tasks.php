<?php
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérifier si l'utilisateur est un administrateur
// if ($_SESSION['role'] != "chef") { // Supposons que l'ID 1 représente un administrateur
//     header("Location: ../templates/dashboard.php");
//     exit;
// }
$user = getLoggedInUser($pdo);

// Vérification du rôle
$role = getUserRole($user);
if ($role === 'agent') {
    header("Location: ../templates/dashboard.php");
} 

// Récupérer toutes les tâches
$query = $pdo->query("
    SELECT taches.*, users.nom AS agent_nom, users.prenom AS agent_prenom 
    FROM taches 
    LEFT JOIN users ON taches.responsable_id = users.id
    ORDER BY date_attribution DESC
");
$taches = $query->fetchAll();

// Ajouter ou modifier une tâche
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // Ajouter une tâche
        $intitule = sanitize($_POST['intitule']);
        $description = sanitize($_POST['description']);
        $date_attribution = date('Y-m-d');
        $responsable_id = sanitize($_POST['responsable_id']);

        $query = $pdo->prepare("INSERT INTO taches (intitule, description, date_attribution, responsable_id) VALUES (?, ?, ?, ?)");
        $query->execute([$intitule, $description, $date_attribution, $responsable_id]);
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        // Supprimer une tâche
        $task_id = sanitize($_POST['task_id']);
        $query = $pdo->prepare("DELETE FROM taches WHERE id = ?");
        $query->execute([$task_id]);
    }

    header("Location: manage_tasks.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tâches</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-500 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Gestion des Tâches</h1>
            <nav>
                <a href="<?= TEMPLATES_PATH ?>/dashboard.php" class="text-white hover:underline">Retour au tableau de
                    bord</a>
                <a href="../auth/logout.php" class="ml-4 text-white hover:underline">Se déconnecter</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-4">Liste des Tâches</h2>

        <table class="w-full bg-white rounded shadow-lg overflow-hidden mb-6">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2">Intitulé</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Responsable</th>
                    <th class="px-4 py-2">Statut</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taches as $tache): ?>
                <tr>
                    <td class="border px-4 py-2"><?= sanitize($tache['intitule']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['description']) ?></td>
                    <td class="border px-4 py-2">
                        <?= $tache['agent_nom'] ? sanitize($tache['agent_nom'] . ' ' . $tache['agent_prenom']) : 'Non attribué' ?>
                    </td>
                    <td class="border px-4 py-2"><?= sanitize($tache['statut']) ?></td>
                    <td class="border px-4 py-2">
                        <form method="POST" action="" class="inline-block">
                            <input type="hidden" name="task_id" value="<?= $tache['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="text-red-500 hover:underline">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="text-2xl font-bold mb-4">Ajouter une Tâche</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label for="intitule" class="block text-gray-700">Intitulé</label>
                <input type="text" id="intitule" name="intitule" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="description" class="block text-gray-700">Description</label>
                <textarea id="description" name="description" class="w-full px-3 py-2 border rounded"></textarea>
            </div>
            <div class="mb-4">
                <label for="responsable_id" class="block text-gray-700">Attribuer à</label>
                <select id="responsable_id" name="responsable_id" class="w-full px-3 py-2 border rounded" required>
                    <?php
                    $users = $pdo->query("SELECT id, nom, prenom FROM users")->fetchAll();
                    foreach ($users as $user):
                    ?>
                    <option value="<?= $user['id'] ?>"><?= sanitize($user['nom'] . ' ' . $user['prenom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">Ajouter</button>
        </form>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="container mx-auto text-center">
            <p>© 2025 Gestion des Tâches. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>