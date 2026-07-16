<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Debug: Vérifier si la session est active
error_log("Session user ID: " . ($_SESSION['user_id'] ?? 'non connecté'));

// Vérification des permissions (seuls les chefs/admin peuvent assigner)
$currentUser = getLoggedInUser($pdo);
if (!$currentUser || !in_array(getUserRole($currentUser), ['chef', 'admin'])) {
    error_log("Tentative d'accès non autorisée");
    redirect(TEMPLATES_PATH . '/dashboard.php');
    exit;
}

// Vérification de l'ID du dossier
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de dossier invalide";
    error_log("ID de dossier invalide: " . ($_GET['id'] ?? 'non défini'));
    redirect('/templates/list_dossiers.php');
    exit;
}

$dossier_id = (int)$_GET['id'];
error_log("Traitement du dossier ID: $dossier_id");

try {
    // Récupération des informations du dossier
    $query = $pdo->prepare("SELECT d.*, u.nom as agent_nom, u.prenom as agent_prenom 
                           FROM dossiers d 
                           LEFT JOIN users u ON d.agent_id = u.id 
                           WHERE d.id = ?");
    $query->execute([$dossier_id]);
    $dossier = $query->fetch(PDO::FETCH_ASSOC);

    if (!$dossier) {
        throw new Exception("Dossier introuvable");
    }

    // Récupération des agents disponibles
    $agents = $pdo->query("SELECT id, nom, prenom FROM users WHERE role = 'agent' AND is_active = 1")->fetchAll();
    error_log("Nombre d'agents disponibles: " . count($agents));

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        error_log("Formulaire POST reçu");
        
        if (!isset($_POST['agent_id']) || !is_numeric($_POST['agent_id'])) {
            throw new Exception("Sélection d'agent invalide");
        }

        $agent_id = (int)$_POST['agent_id'];
        error_log("Agent sélectionné ID: $agent_id");

        // Vérification que l'agent existe
        $agentExists = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'agent' AND is_active = 1");
        $agentExists->execute([$agent_id]);
        
        if (!$agentExists->fetch()) {
            throw new Exception("Agent sélectionné invalide");
        }

        // Mise à jour de l'assignation
        $updateQuery = $pdo->prepare("UPDATE dossiers SET agent_id = ?, date_assignation = NOW() WHERE id = ?");
        $result = $updateQuery->execute([$agent_id, $dossier_id]);
        
        // Debug du résultat de la mise à jour
        error_log("Résultat de l'update: " . ($result ? 'succès' : 'échec'));
        error_log("Lignes affectées: " . $updateQuery->rowCount());

        if ($result && $updateQuery->rowCount() > 0) {
            // Journalisation de l'action
            error_log("Dossier #$dossier_id assigné à l'agent #$agent_id par l'utilisateur #{$currentUser['id']}");
            
            $_SESSION['success'] = "Dossier assigné avec succès à " . getAgentName($pdo, $agent_id);
        } else {
            throw new Exception("Échec de l'assignation du dossier");
        }
        
        redirect('/templates/list_dossiers.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    redirect('/templates/list_dossiers.php');
    exit;
}

// Fonction helper pour récupérer le nom de l'agent
function getAgentName($pdo, $agent_id) {
    $query = $pdo->prepare("SELECT nom, prenom FROM users WHERE id = ?");
    $query->execute([$agent_id]);
    $agent = $query->fetch(PDO::FETCH_ASSOC);
    return $agent ? $agent['nom'] . ' ' . $agent['prenom'] : '';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigner un dossier</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-lg shadow-md overflow-hidden">
        <!-- En-tête -->
        <div class="bg-blue-600 text-white p-4">
            <h1 class="text-xl font-bold flex items-center">
                <i class="fas fa-tasks mr-2"></i> Assignation de dossier
            </h1>
        </div>

        <!-- Contenu -->
        <div class="p-6">
            <!-- Informations du dossier -->
            <div class="mb-6 bg-gray-50 p-4 rounded border border-gray-200">
                <h2 class="text-lg font-bold mb-2 flex items-center">
                    <i class="fas fa-folder-open mr-2 text-blue-500"></i> Dossier
                    #<?= htmlspecialchars($dossier['numero_unique']) ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-500 text-sm">Objet</label>
                        <p class="font-medium"><?= htmlspecialchars($dossier['objet']) ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm">Date création</label>
                        <p><?= date('d/m/Y', strtotime($dossier['date_enregistrement_systeme'])) ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm">Statut</label>
                        <span
                            class="px-2 py-1 text-xs rounded-full 
                            <?= $dossier['statut'] === 'nouveau' ? 'bg-blue-100 text-blue-800' : 
                               ($dossier['statut'] === 'en cours' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                            <?= htmlspecialchars($dossier['statut']) ?>
                        </span>
                    </div>
                    <?php if ($dossier['agent_id']): ?>
                    <div>
                        <label class="block text-gray-500 text-sm">Actuellement assigné à</label>
                        <p><?= htmlspecialchars($dossier['agent_nom'] . ' ' . $dossier['agent_prenom']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulaire d'assignation -->
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="agent_id" class="block text-gray-700 mb-2 font-medium">
                        <i class="fas fa-user-shield mr-1"></i> Sélectionner un agent
                    </label>
                    <select id="agent_id" name="agent_id"
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- Sélectionner un agent --</option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>"
                            <?= ($dossier['agent_id'] == $agent['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent['nom'] . ' ' . $agent['prenom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-between mt-6">
                    <a href="/templates/list_dossiers.php"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Retour
                    </a>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center">
                        <i class="fas fa-save mr-2"></i> Enregistrer l'assignation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Script pour améliorer l'UX
    document.addEventListener('DOMContentLoaded', function() {
        // Focus sur le select
        document.getElementById('agent_id').focus();

        // Debug: Vérifier que le formulaire est soumis correctement
        document.querySelector('form').addEventListener('submit', function(e) {
            console.log("Formulaire soumis, agent sélectionné:",
                document.getElementById('agent_id').value);
        });
    });
    </script>
</body>

</html>