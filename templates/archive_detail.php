<?php
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
startSecureSession();

// Vérification de l'authentification
$user = getLoggedInUser($pdo);
if (!$user) {
    redirect('/auth/login.php');
}

// Vérification de l'ID de l'archive
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID d'archive invalide";
    redirect('/templates/archives.php');
}

$archiveId = (int)$_GET['id'];

try {
    // Récupération des données de l'archive
    $query = $pdo->prepare("
        SELECT archives.*, 
               users.nom AS agent_nom, 
               users.prenom AS agent_prenom,
               users.email_pro AS agent_email,
               users.telephone AS agent_telephone
        FROM archives
        LEFT JOIN users ON archives.traité_par = users.id
        WHERE archives.id = ?
    ");
    $query->execute([$archiveId]);
    $archive = $query->fetch();

    if (!$archive) {
        $_SESSION['error'] = "Archive introuvable";
        redirect('/templates/archives.php');
    }

    // Formatage des dates
    $archive['date_formatted'] = date('d/m/Y', strtotime($archive['date']));
    $archive['date_archivage_formatted'] = !empty($archive['date_archivage']) 
        ? date('d/m/Y', strtotime($archive['date_archivage'])) 
        : 'Non spécifiée';

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la récupération de l'archive";
    redirect('/templates/archives.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Archive #<?= $archiveId ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50">
    <header class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-archive mr-2"></i> Détails de l'Archive
            </h1>
            <nav class="flex items-center space-x-4">
                <a href="archives.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
                <a href="../auth/logout.php" class="text-white hover:underline flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <!-- En-tête avec actions -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                Archive #<?= $archiveId ?> - <?= htmlspecialchars($archive['objet']) ?>
            </h2>
            <div class="flex space-x-2">
                <form method="POST" action="generate_archive_pdf.php" class="inline">
                    <input type="hidden" name="archive_id" value="<?= $archiveId ?>">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center">
                        <i class="fas fa-file-pdf mr-2"></i> Exporter PDF
                    </button>
                </form>
                <?php if (getUserRole($user) === 'admin'): ?>
                <a href="edit_archive.php?id=<?= $archiveId ?>"
                    class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 flex items-center">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section principale -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informations principales -->
            <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
                <h3 class="text-lg font-bold mb-4 border-b pb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i> Informations générales
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Objet</label>
                        <p class="font-medium"><?= htmlspecialchars($archive['objet']) ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Référence</label>
                        <p class="font-mono font-medium">ARCH-<?= str_pad($archiveId, 6, '0', STR_PAD_LEFT) ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Date de création</label>
                        <p><?= $archive['date_formatted'] ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Année</label>
                        <p><?= $archive['annee'] ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Date d'archivage</label>
                        <p><?= $archive['date_archivage_formatted'] ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">Décision</label>
                        <span class="px-2 py-1 text-xs rounded-full 
                              <?= getDecisionBadgeClass($archive['decision']) ?>">
                            <?= htmlspecialchars($archive['decision'] ?? 'Non spécifiée') ?>
                        </span>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($archive['description'])): ?>
                <div class="mt-6">
                    <label class="block text-gray-500 text-sm mb-1">Description</label>
                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <?= nl2br(htmlspecialchars($archive['description'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Commentaires -->
                <?php if (!empty($archive['commentaires'])): ?>
                <div class="mt-6">
                    <label class="block text-gray-500 text-sm mb-1">Commentaires</label>
                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <?= nl2br(htmlspecialchars($archive['commentaires'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Métadonnées et agent -->
            <div class="space-y-6">
                <!-- Fichier archivé -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-blue-500"></i> Document archivé
                    </h3>

                    <?php if (!empty($archive['fiche_archive']) && file_exists(__DIR__ . '/../files/' . $archive['fiche_archive'])): ?>
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-file-pdf text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="font-medium"><?= htmlspecialchars(basename($archive['fiche_archive'])) ?></p>
                            <p class="text-sm text-gray-500">
                                <?= formatBytes(filesize(__DIR__ . '/../files/' . $archive['fiche_archive'])) ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="../files/<?= htmlspecialchars($archive['fiche_archive']) ?>"
                            class="text-blue-600 hover:text-blue-800 flex items-center" download>
                            <i class="fas fa-download mr-2"></i> Télécharger
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 italic">Aucun document associé</p>
                    <?php endif; ?>
                </div>

                <!-- Agent responsable -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2 flex items-center">
                        <i class="fas fa-user-shield mr-2 text-blue-500"></i> Agent responsable
                    </h3>

                    <?php if ($archive['agent_nom']): ?>
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-user text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="font-medium">
                                <?= htmlspecialchars($archive['agent_prenom'] . ' ' . $archive['agent_nom']) ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-envelope mr-1"></i>
                                <?= htmlspecialchars($archive['agent_email'] ?? 'Non spécifié') ?>
                            </p>
                            <?php if (!empty($archive['agent_telephone'])): ?>
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-phone mr-1"></i>
                                <?= htmlspecialchars($archive['agent_telephone']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 italic">Non attribué</p>
                    <?php endif; ?>
                </div>

                <!-- Historique (exemple) -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-bold mb-4 border-b pb-2 flex items-center">
                        <i class="fas fa-history mr-2 text-blue-500"></i> Historique
                    </h3>

                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="bg-green-100 p-2 rounded-full mr-3">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-medium">Archivage</p>
                                <p class="text-sm text-gray-500">
                                    <?= $archive['date_archivage_formatted'] ?> par Système
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                <i class="fas fa-file-import text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium">Création</p>
                                <p class="text-sm text-gray-500">
                                    <?= $archive['date_formatted'] ?>
                                    <?= $archive['agent_nom'] ? 'par ' . htmlspecialchars($archive['agent_prenom'] . ' ' . $archive['agent_nom']) : '' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 border-t mt-8 py-4">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            <p>Système d'archivage - <?= date('Y') ?></p>
        </div>
    </footer>
</body>

</html>

<?php
// Fonction pour obtenir la classe CSS selon la décision
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

// Fonction pour formater la taille des fichiers
function formatBytes($bytes, $precision = 2) {
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}