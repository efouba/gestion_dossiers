<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Récupérer les tâches de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$query = $pdo->prepare("
    SELECT * FROM taches 
    WHERE responsable_id = ? 
    ORDER BY date_attribution DESC
");
$query->execute([$user_id]);
$taches = $query->fetchAll();

// Mise à jour du statut d'une tâche
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = sanitize($_POST['task_id']);
    $statut = sanitize($_POST['statut']);

    $updateQuery = $pdo->prepare("UPDATE taches SET statut = ?, date_terminee = ? WHERE id = ?");
    $date_terminee = ($statut == 'Terminee') ? date('Y-m-d') : null; // Ajouter une date seulement si la tâche est terminée
    $updateQuery->execute([$statut, $date_terminee, $task_id]);

    header("Location: tasks.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Tâches</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-500 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Mes Tâches</h1>
            <nav>
                <a href="dashboard.php" class="text-white hover:underline">Retour au tableau de bord</a>
                <a href="logout.php" class="ml-4 text-white hover:underline">Se déconnecter</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-4">Liste des Tâches</h2>

        <!-- Regroupement des tâches par statut -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Filtrer par statut :</h3>
            <form method="GET" action="" class="flex items-center">
                <select name="filter" class="px-3 py-2 border rounded">
                    <option value="">Tous</option>
                    <option value="Initial"
                        <?= isset($_GET['filter']) && $_GET['filter'] == 'Initial' ? 'selected' : '' ?>>Initial</option>
                    <option value="En cours"
                        <?= isset($_GET['filter']) && $_GET['filter'] == 'En cours' ? 'selected' : '' ?>>En cours
                    </option>
                    <option value="Terminee"
                        <?= isset($_GET['filter']) && $_GET['filter'] == 'Terminée' ? 'selected' : '' ?>>Terminée
                    </option>
                </select>
                <button type="submit" class="ml-4 bg-blue-500 text-white px-4 py-2 rounded">Filtrer</button>
            </form>
        </div>

        <!-- Liste des tâches -->
        <table class="w-full bg-white rounded shadow-lg overflow-hidden mb-6">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2">Intitulé</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Statut</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taches as $tache): ?>
                <?php 
                    // Filtrage par statut si nécessaire
                    if (isset($_GET['filter']) && $_GET['filter'] != '' && $_GET['filter'] != $tache['statut']) {
                        continue;
                    }
                    ?>
                <tr>
                    <td class="border px-4 py-2"><?= sanitize($tache['intitule']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['description']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['statut']) ?></td>
                    <td class="border px-4 py-2">
                        <!-- Formulaire pour mettre à jour le statut -->
                        <form method="POST" action="" class="inline-block">
                            <input type="hidden" name="task_id" value="<?= $tache['id'] ?>">
                            <select name="statut" class="px-3 py-2 border rounded" required>
                                <option value="Initial" <?= $tache['statut'] == 'Initial' ? 'selected' : '' ?>>Initial
                                </option>
                                <option value="En cours" <?= $tache['statut'] == 'En cours' ? 'selected' : '' ?>>En
                                    cours</option>
                                <option value="Terminee" <?= $tache['statut'] == 'Terminee' ? 'selected' : '' ?>>
                                    Terminée</option>
                            </select>
                            <button type="submit" class="ml-2 bg-blue-500 text-white px-4 py-2 rounded">Mettre à
                                jour</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Générer un rapport hebdomadaire -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">Générer un rapport hebdomadaire</h3>
            <form method="POST" action="generate_report.php">
                <input type="hidden" name="start_date" value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
                <input type="hidden" name="end_date" value="<?= date('Y-m-d', strtotime('friday this week')) ?>">
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Générer le rapport</button>
            </form>
        </div>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="container mx-auto text-center">
            <p>© 2025 Gestion des Tâches. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>