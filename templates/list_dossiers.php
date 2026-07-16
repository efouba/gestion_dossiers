<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérification de l'utilisateur
$user = getLoggedInUser($pdo);
if (!$user) {
    session_destroy();
    redirect(AUTH_PATH . '/login.php');
}

// Vérification du rôle
$userRole = getUserRole($user);
$isChef = ($userRole === 'chef');
$isAdmin = ($userRole === 'admin');

// Fonctions utilitaires
function formatDate($date)
{
    return $date ? date('d/m/Y', strtotime($date)) : 'N/A';
}

function truncateText($text, $length = 50)
{
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

// Gestion de la recherche
$searchQuery = '';
$searchParams = [];
if (!empty($_GET['search'])) {
    $searchQuery = " WHERE dossiers.objet LIKE :search OR dossiers.expediteur LIKE :search";
    $searchParams = [':search' => '%' . sanitize($_GET['search']) . '%'];
}

// Récupération des dossiers
$query = $pdo->prepare("
    SELECT dossiers.*, 
           users.nom AS agent_nom, 
           users.prenom AS agent_prenom 
    FROM dossiers 
    LEFT JOIN users ON dossiers.agent_id = users.id 
    $searchQuery
    ORDER BY date_enregistrement_systeme DESC
");
$query->execute($searchParams);
$dossiers = $query->fetchAll();

// Récupération des agents pour l'assignation
$agents = $pdo->query("SELECT id, nom, prenom FROM users WHERE role = 'agent' AND is_active = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Dossiers - Gestion des Dossiers</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .avatar-initials {
        font-size: 0.75rem;
    }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-folder-open text-xl"></i>
                <h1 class="text-xl font-bold">Gestion des Dossiers</h1>
            </div>
            <nav class="flex items-center space-x-4">
                <a href="<?= TEMPLATES_PATH ?>/dashboard.php"
                    class="text-white hover:text-blue-200 transition flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                </a>
                <a href="<?= AUTH_PATH ?>/logout.php"
                    class="text-white hover:text-blue-200 transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- En-tête avec titre et boutons -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-list-ol mr-2"></i> Liste des Dossiers
                        </h2>
                        <p class="text-gray-600 text-sm mt-1">
                            <?= count($dossiers) ?> dossier(s) trouvé(s)
                        </p>
                    </div>
                    <div class="mt-4 md:mt-0 flex space-x-3">
                        <!-- Bouton Ajouter -->
                        <a href="<?= TEMPLATES_PATH ?>/add_dossiers.php"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow transition flex items-center">
                            <i class="fas fa-plus mr-2"></i> Nouveau dossier
                        </a>

                        <!-- Bouton Export -->
                        <button onclick="exportToExcel()"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow transition flex items-center">
                            <i class="fas fa-file-excel mr-2"></i> Exporter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" placeholder="Rechercher par objet ou description..."
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                        <select
                            class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                            <option>Tous les statuts</option>
                            <option>Non traités</option>
                            <option>En cours</option>
                            <option>Terminés</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Actions</label>
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow transition flex items-center justify-center">
                            <i class="fas fa-filter mr-2"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tableau des dossiers -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                id
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Numéro
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Objet
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Dates
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Statut
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Agent
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($dossiers as $dossier):
                            // Déterminer le statut
                            if (!$dossier['date_traitement']) {
                                $statusClass = 'bg-red-100 text-red-800';
                                $statusText = 'Non traité';
                            } elseif ($dossier['date_traitement'] && !$dossier['avis']) {
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                $statusText = 'En cours';
                            } else {
                                $statusClass = 'bg-green-100 text-green-800';
                                $statusText = 'Terminé';
                            }
                        ?>
                        <tr class="hover:bg-gray-50">

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-mono font-bold text-blue-600">
                                    <?= htmlspecialchars($dossier['id']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-mono font-bold text-blue-600">
                                    <?= htmlspecialchars($dossier['numero_unique']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars($dossier['objet']) ?>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <?= htmlspecialchars(truncateText($dossier['expediteur'] ?? '')) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <span class="font-medium">Réception:</span>
                                    <?= formatDate($dossier['date_reception_guerite']) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-medium">Enregistrement:</span>
                                    <?= formatDate($dossier['date_enregistrement_systeme']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="status-badge px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                    <?= $statusText ?>
                                </span>
                                <?php if ($dossier['avis']): ?>
                                <div class="text-xs mt-1 text-gray-500">
                                    Avis: <?= htmlspecialchars($dossier['avis']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($dossier['agent_nom']): ?>
                                <div class="flex items-center">
                                    <div
                                        class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span class="text-blue-600 font-medium avatar-initials">
                                            <?= htmlspecialchars(substr($dossier['agent_prenom'], 0, 1) . substr($dossier['agent_nom'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($dossier['agent_prenom'] . ' ' . $dossier['agent_nom']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-sm italic text-gray-500">Non attribué</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">


                                    <!-- Bouton Assigner (visible seulement pour les chefs) -->
                                    <?php if ($isChef && !$dossier['agent_id']): ?>
                                    <a href="<?= TEMPLATES_PATH ?>/assign_dossiers.php?id=<?= (int)($dossier['id']) ?>&objet=<?= urlencode(sanitize($dossier['objet'])) ?>'"
                                        class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-2 rounded-full"
                                        title="Assigner">
                                        <i class="fas fa-user-plus"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Bouton Voir -->
                                    <a href="<?= TEMPLATES_PATH ?>/dossier_details.php?id=<?= (int)$dossier['id'] ?>"
                                        class="text-blue-600 hover:text-blue-900 bg-blue-50 p-2 rounded-full"
                                        title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- Bouton Modifier -->
                                    <a href="<?= TEMPLATES_PATH ?>/add_dossiers.php?id=<?= (int)$dossier['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-900 bg-yellow-50 p-2 rounded-full"
                                        title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <!-- Bouton Télécharger (si justificatif existe) -->
                                    <?php if (!empty($dossier['justificatif'])): ?>
                                    <a href="<?= getBaseUrl() ?>/files/<?= htmlspecialchars($dossier['justificatif']) ?>"
                                        class="text-green-600 hover:text-green-900 bg-green-50 p-2 rounded-full"
                                        title="Télécharger" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Bouton Archiver -->
                                    <a href="<?= TEMPLATES_PATH ?>/archive_dossier.php?id=<?= (int)$dossier['id'] ?>"
                                        class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-full"
                                        title="Archiver"
                                        onclick="return confirm('Êtes-vous sûr de vouloir archiver ce dossier ?')">
                                        <i class="fas fa-archive"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="text-sm text-gray-500 mb-4 md:mb-0">
                        Affichage de <span class="font-medium">1</span> à <span
                            class="font-medium"><?= min(10, count($dossiers)) ?></span> sur <span
                            class="font-medium"><?= count($dossiers) ?></span> résultats
                    </div>
                    <div class="inline-flex space-x-1">
                        <button class="px-3 py-1 border rounded bg-white text-gray-700 disabled:opacity-50" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="px-3 py-1 border rounded bg-blue-600 text-white">
                            1
                        </button>
                        <?php if (count($dossiers) > 10): ?>
                        <button class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-50">
                            2
                        </button>
                        <?php endif; ?>
                        <button
                            class="px-3 py-1 border rounded bg-white text-gray-700 <?= count($dossiers) <= 10 ? 'disabled:opacity-50' : '' ?>"
                            <?= count($dossiers) <= 10 ? 'disabled' : '' ?>>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal d'assignation -->
    <div id="assignModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Assigner le dossier</h3>
                <p id="dossierTitle" class="text-gray-600 mb-4"></p>

                <form id="assignForm" method="POST" action="<?= getBaseUrl() ?>/templates/assign_dossiers.php">
                    <input type="hidden" id="dossierId" name="dossier_id">

                    <div class="mb-4">
                        <label for="agent_id" class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
                        <select name="agent_id" id="agent_id" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                            <option value="">-- Sélectionner un agent --</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= (int)$agent['id'] ?>">
                                <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeAssignModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                            Assigner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="container mx-auto px-4 text-center text-sm">
            <p><i class="far fa-copyright mr-1"></i> <?= date('Y') ?> Gestion des Dossiers. Tous droits réservés.</p>
        </div>
    </footer>

    <script>
    // Fonctions pour la modal d'assignation
    function openAssignModal(dossierId, dossierTitle) {
        document.getElementById('dossierId').value = dossierId;
        document.getElementById('dossierTitle').textContent = 'Dossier: ' + dossierTitle;
        document.getElementById('assignModal').classList.remove('hidden');
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.add('hidden');
        document.getElementById('assignForm').reset();
    }

    // Export Excel (simulé)
    function exportToExcel() {
        // À implémenter avec une librairie comme SheetJS ou un export côté serveur
        alert('Export vers Excel sera implémenté ici');
    }

    // Fermer la modal en cliquant à l'extérieur
    window.onclick = function(event) {
        const modal = document.getElementById('assignModal');
        if (event.target === modal) {
            closeAssignModal();
        }
    }
    </script>
</body>

</html>