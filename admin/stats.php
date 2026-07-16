<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérification de l'authentification et des permissions
$user = getLoggedInUser($pdo);
if (!$user) {
    redirect('/auth/login.php');
}

// Vérification du rôle
if (getUserRole($user) === 'agent') {
    redirect('/templates/dashboard.php');
}

// Période par défaut (30 derniers jours)
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

// Gestion des filtres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = sanitize($_POST['start_date'] ?? $startDate);
    $endDate = sanitize($_POST['end_date'] ?? $endDate);
    $agentFilter = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
}

// Récupération des données statistiques
$stats = getAdvancedStats($pdo, $startDate, $endDate, $agentFilter ?? null);

// Récupération de la liste des agents pour le filtre
$agents = $pdo->query("SELECT id, CONCAT(nom, ' ', prenom) AS full_name FROM users WHERE role = 'agent'")->fetchAll();

/**
 * Récupère les statistiques avancées
 */
function getAdvancedStats(PDO $pdo, string $startDate, string $endDate, ?int $agentId = null): array {
    $params = [':start' => $startDate, ':end' => $endDate];
    $agentCondition = $agentId ? " AND d.agent_id = :agent_id" : "";
    
    if ($agentId) {
        $params[':agent_id'] = $agentId;
    }

    // Statistiques principales
    $query = $pdo->prepare("
        SELECT 
            COUNT(d.id) AS total_dossiers,
            SUM(CASE WHEN d.date_traitement IS NULL THEN 1 ELSE 0 END) AS dossiers_non_traites,
            SUM(CASE WHEN d.date_traitement IS NOT NULL THEN 1 ELSE 0 END) AS dossiers_traites,
            AVG(DATEDIFF(COALESCE(d.date_traitement, NOW()), d.date_enregistrement_systeme)) AS delai_moyen
        FROM dossiers d
        WHERE d.date_enregistrement_systeme BETWEEN :start AND :end $agentCondition
    ");
    $query->execute($params);
    $mainStats = $query->fetch(PDO::FETCH_ASSOC);

    // Statistiques par agent
    $query = $pdo->prepare("
        SELECT 
            u.id, u.nom, u.prenom, 
            COUNT(d.id) AS total_dossiers,
            SUM(CASE WHEN d.date_traitement IS NULL THEN 1 ELSE 0 END) AS non_traites,
            SUM(CASE WHEN d.date_traitement IS NOT NULL THEN 1 ELSE 0 END) AS traites,
            ROUND(AVG(DATEDIFF(COALESCE(d.date_traitement, NOW()), d.date_enregistrement_systeme)), 1) AS delai_moyen
        FROM users u
        LEFT JOIN dossiers d ON u.id = d.agent_id AND d.date_enregistrement_systeme BETWEEN :start AND :end
        WHERE u.role = 'agent'
        GROUP BY u.id
        ORDER BY total_dossiers DESC
    ");
    $query->execute([':start' => $startDate, ':end' => $endDate]);
    $agentsStats = $query->fetchAll();

    // Évolution mensuelle
    $query = $pdo->prepare("
        SELECT 
            DATE_FORMAT(date_enregistrement_systeme, '%Y-%m') AS mois,
            COUNT(*) AS total,
            SUM(CASE WHEN date_traitement IS NOT NULL THEN 1 ELSE 0 END) AS traites
        FROM dossiers
        WHERE date_enregistrement_systeme BETWEEN :start AND :end $agentCondition
        GROUP BY mois
        ORDER BY mois
    ");
    $query->execute($params);
    $monthlyStats = $query->fetchAll();

    // Statistiques des avis
    $query = $pdo->prepare("
        SELECT 
            avis,
            COUNT(*) AS total,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dossiers WHERE date_enregistrement_systeme BETWEEN :start AND :end $agentCondition), 1) AS pourcentage
        FROM dossiers
        WHERE date_enregistrement_systeme BETWEEN :start AND :end $agentCondition AND avis IS NOT NULL
        GROUP BY avis
        ORDER BY total DESC
    ");
    $query->execute($params);
    $avisStats = $query->fetchAll();

    return [
        'main' => $mainStats,
        'agents' => $agentsStats,
        'monthly' => $monthlyStats,
        'avis' => $avisStats,
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Statistique</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-50">
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-chart-line mr-2"></i> Tableau de Bord Statistique
            </h1>
            <nav class="flex items-center space-x-4">
                <a href="<?= TEMPLATES_PATH ?>/dashboard.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                </a>
                <a href="../auth/logout.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-filter mr-2"></i> Filtres
            </h2>
            <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 mb-1">Date de début</label>
                    <input type="date" name="start_date" value="<?= $stats['start_date'] ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Date de fin</label>
                    <input type="date" name="end_date" value="<?= $stats['end_date'] ?>"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Agent</label>
                    <select name="agent_id" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">Tous les agents</option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>"
                            <?= isset($agentFilter) && $agentFilter == $agent['id'] ? 'selected' : '' ?>>
                            <?= sanitize($agent['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition w-full">
                        <i class="fas fa-sync-alt mr-1"></i> Appliquer
                    </button>
                </div>
            </form>
        </div>

        <!-- KPI Principaux -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-gray-500 mb-1">Dossiers totaux</div>
                <div class="text-3xl font-bold text-blue-600"><?= $stats['main']['total_dossiers'] ?></div>
                <div class="text-sm text-gray-500 mt-2">Période sélectionnée</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-gray-500 mb-1">Dossiers traités</div>
                <div class="text-3xl font-bold text-green-600"><?= $stats['main']['dossiers_traites'] ?></div>
                <div class="text-sm text-gray-500 mt-2">
                    <?= round($stats['main']['dossiers_traites'] / max(1, $stats['main']['total_dossiers']) * 100) ?>%
                    du total
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-gray-500 mb-1">Dossiers en attente</div>
                <div class="text-3xl font-bold text-orange-600"><?= $stats['main']['dossiers_non_traites'] ?></div>
                <div class="text-sm text-gray-500 mt-2">
                    <?= round($stats['main']['dossiers_non_traites'] / max(1, $stats['main']['total_dossiers']) * 100) ?>%
                    du total
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-gray-500 mb-1">Délai moyen</div>
                <div class="text-3xl font-bold text-purple-600"><?= $stats['main']['delai_moyen'] ?> jours</div>
                <div class="text-sm text-gray-500 mt-2">Temps de traitement moyen</div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Évolution mensuelle -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-bar mr-2"></i> Évolution mensuelle
                </h3>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>

            <!-- Répartition des avis -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-chart-pie mr-2"></i> Répartition des avis
                </h3>
                <canvas id="avisChart" height="250"></canvas>
            </div>
        </div>

        <!-- Performance des agents -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-users mr-2"></i> Performance par agent
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Agent</th>
                            <th class="px-4 py-2 text-left">Dossiers traités</th>
                            <th class="px-4 py-2 text-left">En attente</th>
                            <th class="px-4 py-2 text-left">Délai moyen (jours)</th>
                            <th class="px-4 py-2 text-left">Taux de traitement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($stats['agents'] as $agent): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><?= sanitize($agent['prenom'] . ' ' . sanitize($agent['nom'])) ?></td>
                            <td class="px-4 py-3"><?= $agent['traites'] ?></td>
                            <td class="px-4 py-3"><?= $agent['non_traites'] ?></td>
                            <td class="px-4 py-3"><?= $agent['delai_moyen'] ?></td>
                            <td class="px-4 py-3">
                                <?php 
                                    $total = $agent['traites'] + $agent['non_traites'];
                                    $rate = $total > 0 ? round($agent['traites'] / $total * 100) : 0;
                                ?>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $rate ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-600"><?= $rate ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export des données -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-file-export mr-2"></i> Export des données
            </h3>
            <div class="flex flex-wrap gap-3">
                <button onclick="exportToExcel()"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Excel
                </button>
                <button onclick="exportToPDF()"
                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
                <button onclick="printDashboard()"
                    class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition flex items-center">
                    <i class="fas fa-print mr-2"></i> Imprimer
                </button>
            </div>
        </div>
    </main>

    <script>
    // Graphique d'évolution mensuelle
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($stats['monthly'], 'mois')) ?>,
            datasets: [{
                label: 'Dossiers créés',
                data: <?= json_encode(array_column($stats['monthly'], 'total')) ?>,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true
            }, {
                label: 'Dossiers traités',
                data: <?= json_encode(array_column($stats['monthly'], 'traites')) ?>,
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Graphique des avis
    const avisCtx = document.getElementById('avisChart').getContext('2d');
    const avisChart = new Chart(avisCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($stats['avis'], 'avis')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($stats['avis'], 'total')) ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(239, 68, 68, 0.7)',
                    'rgba(99, 102, 241, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Fonctions d'export
    function exportToExcel() {
        // Implémentez la logique d'export Excel ici
        alert('Export Excel à implémenter');
    }

    function exportToPDF() {
        // Implémentez la logique d'export PDF ici
        alert('Export PDF à implémenter');
    }

    function printDashboard() {
        window.print();
    }
    </script>
</body>

</html>