<?php
// Debug temporaire
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérification des permissions
$user = getLoggedInUser($pdo);
if (!$user || getUserRole($user) === 'agent') {
    redirect('/templates/dashboard.php');
}

// Récupération et filtrage des données
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : null;
$decisionFilter = isset($_GET['decision']) ? sanitize($_GET['decision']) : null;

try {
    // Requête de base avec jointure corrigée
    $sql = "SELECT archives.*, 
                   users.nom AS agent_nom, 
                   users.prenom AS agent_prenom 
            FROM archives 
            LEFT JOIN users ON archives.traite_par = users.id";
    
    $whereClauses = [];
    $params = [];
    
    // Filtre de recherche
    if (!empty($search)) {
        $whereClauses[] = "(archives.objet LIKE :search OR archives.annee LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Filtre par année
    if ($yearFilter) {
        $whereClauses[] = "archives.annee = :year";
        $params[':year'] = $yearFilter;
    }
    
    // Filtre par décision
    if ($decisionFilter) {
        $whereClauses[] = "archives.decision = :decision";
        $params[':decision'] = $decisionFilter;
    }
    
    // Combinaison des clauses WHERE
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
    // Tri
    $sql .= " ORDER BY archives.date DESC";
    
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $archives = $query->fetchAll();
    
    // Récupération des années distinctes pour le filtre
    $years = $pdo->query("SELECT DISTINCT annee FROM archives ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupération des décisions distinctes
    $decisions = $pdo->query("SELECT DISTINCT decision FROM archives WHERE decision IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives des Dossiers</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-archive mr-2"></i> Archives des Dossiers
            </h1>
            <nav class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                </a>
                <a href="profile.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Mon profile
                </a>
                <a href="../auth/logout.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <i class="fas fa-filter mr-2"></i> Filtres de recherche
            </h2>

            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Recherche par texte -->
                <div>
                    <label class="block text-gray-700 mb-1">Recherche</label>
                    <input type="text" name="search" placeholder="Objet ou description"
                        class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($search) ?>">
                </div>

                <!-- Filtre par année -->
                <div>
                    <label class="block text-gray-700 mb-1">Année</label>
                    <select name="year" class="w-full px-3 py-2 border rounded">
                        <option value="">Toutes les années</option>
                        <?php foreach ($years as $year): ?>
                        <option value="<?= $year ?>" <?= $yearFilter == $year ? 'selected' : '' ?>>
                            <?= $year ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre par décision -->
                <div>
                    <label class="block text-gray-700 mb-1">Décision</label>
                    <select name="decision" class="w-full px-3 py-2 border rounded">
                        <option value="">Toutes les décisions</option>
                        <?php foreach ($decisions as $decision): ?>
                        <option value="<?= htmlspecialchars($decision) ?>"
                            <?= $decisionFilter === $decision ? 'selected' : '' ?>>
                            <?= htmlspecialchars($decision) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Boutons -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                        <i class="fas fa-search mr-1"></i> Filtrer
                    </button>
                    <a href="?" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-redo mr-1"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- En-tête avec statistiques -->
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <span class="font-medium">Total : <?= count($archives) ?> dossier(s)</span>
                </div>
                <div class="flex space-x-2">
                    <button onclick="exportToExcel()"
                        class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700 flex items-center">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </button>
                    <button onclick="printPage()"
                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 flex items-center">
                        <i class="fas fa-print mr-1"></i> Imprimer
                    </button>
                </div>
            </div>

            <!-- Tableau des archives -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left">Objet</th>
                            <th class="px-6 py-3 text-left">Date</th>
                            <th class="px-6 py-3 text-left">Année</th>
                            <th class="px-6 py-3 text-left">Agent</th>
                            <th class="px-6 py-3 text-left">Décision</th>
                            <th class="px-6 py-3 text-left">Fichier</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($archives)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                Aucun dossier archivé trouvé
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($archives as $archive): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium"><?= htmlspecialchars($archive['objet']) ?></div>
                                <?php if (!empty($archive['description'])): ?>
                                <div class="text-sm text-gray-500 mt-1">
                                    <?= truncateText(htmlspecialchars($archive['description']), 100) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4"><?= formatDate($archive['date']) ?></td>
                            <td class="px-6 py-4"><?= $archive['annee'] ?></td>
                            <td class="px-6 py-4">
                                <?= $archive['agent_nom'] ? 
                                        htmlspecialchars($archive['agent_prenom'] . ' ' . $archive['agent_nom']) : 
                                        'Non attribué' ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                        <?= getDecisionBadgeClass($archive['decision']) ?>">
                                    <?= $archive['decision'] ? htmlspecialchars($archive['decision']) : 'N/A' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($archive['fiche_archive']): ?>
                                <a href="../files/<?= htmlspecialchars($archive['fiche_archive']) ?>"
                                    class="text-blue-600 hover:text-blue-800 flex items-center" download>
                                    <i class="fas fa-file-pdf mr-1"></i> PDF
                                </a>
                                <?php else: ?>
                                <span class="text-gray-400">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-2">
                                    <a href="archive_detail.php?id=<?= $archive['id'] ?>"
                                        class="text-blue-600 hover:text-blue-800 p-1" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form method="POST" action="generate_archive_pdf.php" class="inline">
                                        <input type="hidden" name="archive_id" value="<?= $archive['id'] ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 p-1"
                                            title="Générer PDF">
                                            <i class="fas fa-file-export"></i>
                                        </button>
                                    </form>
                                    <?php if (getUserRole($user) === 'admin'): ?>
                                    <form method="POST" action="delete_archive.php" class="inline"
                                        onsubmit="return confirm('Supprimer définitivement cet archive ?');">
                                        <input type="hidden" name="archive_id" value="<?= $archive['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1"
                                            title="Supprimer">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-gray-50 px-6 py-3 border-t flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Affichage de 1 à <?= count($archives) ?> sur <?= count($archives) ?> archives
                </div>
                <div class="flex space-x-1">
                    <button class="px-3 py-1 border rounded bg-white text-gray-700 disabled:opacity-50" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="px-3 py-1 border rounded bg-blue-600 text-white">
                        1
                    </button>
                    <button class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
    function exportToExcel() {
        // Implémentez l'export Excel ici
        alert('Fonctionnalité d\'export Excel à implémenter');
    }

    function printPage() {
        window.print();
    }

    // Ajouter ici d'autres fonctions JavaScript si nécessaire
    </script>
</body>

</html>

<?php
// Fonctions utilitaires supplémentaires
function formatDate(string $date): string {
    return date('d/m/Y', strtotime($date));
}

function truncateText(string $text, int $length): string {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getDecisionBadgeClass(?string $decision): string {
    switch ($decision) {
        case 'Accepté':
            return 'bg-green-100 text-green-800';
        case 'Refusé':
            return 'bg-red-100 text-red-800';
        case 'En attente':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>