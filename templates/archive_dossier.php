<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

// Vérifier si un ID de dossier est passé dans l'URL
if (!isset($_GET['id'])) {
    die("Aucun ID de dossier spécifié.");
}

$dossier_id = $_GET['id'];

// Récupérer les informations du dossier
$query = $pdo->prepare("SELECT * FROM dossiers WHERE id = ? AND agent_id = ?");
$query->execute([$dossier_id, $_SESSION['user_id']]); // Vérifie si l'utilisateur connecté est assigné à ce dossier
$dossier = $query->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    die("Dossier introuvable ou vous n'êtes pas autorisé à le traiter.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $date_traitement = sanitize($_POST['date_traitement']);
    $avis = sanitize($_POST['avis']);
    $statut = 'Termine'; // Statut fixe pour un dossier traité
    $justificatif = $_FILES['justificatif'];

    // Validation des données
    $errors = [];
    if (empty($date_traitement)) {
        $errors[] = "La date de traitement est obligatoire.";
    } elseif ($date_traitement < $dossier['date_reception_guerite']) {
        $errors[] = "La date de traitement doit être supérieure ou égale à la date de réception à la guérite.";
    }
    
    if (!in_array($avis, ['Favorable', 'Rejete'])) {
        $errors[] = "L'avis doit être soit 'Favorable', soit 'Rejeté'.";
    }
    
    if ($justificatif['error'] == 0) {
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $file_extension = pathinfo($justificatif['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $errors[] = "Le justificatif doit être un fichier PDF ou Word.";
        }
    } else {
        $errors[] = "Un fichier justificatif est obligatoire.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Déplacer le fichier justificatif
            $target_directory = "../files/";
            $filename = time() . "_" . basename($justificatif['name']);
            $target_file = $target_directory . $filename;

            if (!move_uploaded_file($justificatif['tmp_name'], $target_file)) {
                throw new Exception("Erreur lors du téléchargement du fichier.");
            }

            // 2. Mettre à jour le dossier (au lieu de supprimer)
            $updateQuery = $pdo->prepare("
                UPDATE dossiers 
                SET date_traitement = ?,
                    avis = ?,
                    statut = ?,
                    justificatif = ?
              WHERE id = ?
            ");
            
            $updateQuery->execute([
                $date_traitement,
                $avis,
                $statut,
                $filename,
                $dossier_id
            ]);

            // 3. Archivage dans la table des archives (conservation historique)
            $archiveQuery = $pdo->prepare("
                INSERT INTO archives 
                (dossier_id, objet, date, annee, traite_par, decision, fiche_archive)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $archiveQuery->execute([
                $dossier_id,
                $dossier['objet'],
                $date_traitement,
                date('Y', strtotime($date_traitement)),
                $_SESSION['user_id'],
                $avis,
                $filename
            ]);

            $pdo->commit();

            // Redirection avec message de succès
            $_SESSION['success_message'] = "Le dossier a été traité et archivé avec succès.";
            redirect('/templates/list_dossiers.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // Nettoyage du fichier en cas d'erreur
            if (isset($target_file) && file_exists($target_file)) {
                unlink($target_file);
            }
            
            $errors[] = "Erreur lors du traitement : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traiter un Dossier</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white p-6 rounded shadow-lg">
        <h1 class="text-2xl font-bold text-center mb-4">Traiter un Dossier</h1>
        <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <?php foreach ($errors as $error): ?>
            <p><?= $error ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="date_traitement" class="block text-gray-700 mb-2">Date de traitement *</label>
                <input type="date" id="date_traitement" name="date_traitement"
                    min="<?= $dossier['date_reception_guerite'] ?>" class="w-full px-3 py-2 border rounded" required>
            </div>

            <div class="mb-4">
                <label for="avis" class="block text-gray-700 mb-2">Avis *</label>
                <select id="avis" name="avis" class="w-full px-3 py-2 border rounded" required>
                    <option value="">-- Sélectionnez --</option>
                    <option value="Favorable">Favorable</option>
                    <option value="Rejete">Rejeté</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="justificatif" class="block text-gray-700 mb-2">Justificatif * (PDF/Word)</label>
                <input type="file" id="justificatif" name="justificatif" class="w-full px-3 py-2 border rounded"
                    required>
                <p class="text-sm text-gray-500 mt-1">Téléversez le document justificatif</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Enregistrer le traitement
            </button>
        </form>
    </div>
</body>

</html>