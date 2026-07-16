<?php
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

// Vérifier si un dossier spécifique est demandé pour modification
$dossier = null;
if (isset($_GET['id'])) {
    $query = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
    $query->execute([$_GET['id']]);
    $dossier = $query->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $numero_unique = sanitize($_POST['numero_unique']);
    $expediteur = sanitize($_POST['expediteur']);
    $objet = sanitize($_POST['objet']);
    $date_reception = sanitize($_POST['date_reception']);
    $date_enregistrement = date('Y-m-d'); // La date actuelle est automatiquement attribuée

    // Validation des dates
    if ($date_reception > $date_enregistrement) {
        $error_message = "La date de réception doit être antérieure ou égale à la date d'enregistrement.";
    } else {
        // Si modification d'un dossier existant
        if ($dossier) {
            $query = $pdo->prepare("UPDATE dossiers SET numero_unique = ?, expediteur = ?, objet = ?, date_reception_guerite = ?, date_enregistrement_systeme = ? WHERE id = ?");
            $success = $query->execute([$numero_unique, $expediteur, $objet, $date_reception, $date_enregistrement, $dossier['id']]);
        } else {
            // Si ajout d'un nouveau dossier
            $query = $pdo->prepare("INSERT INTO dossiers (numero_unique, expediteur, objet, date_reception_guerite, date_enregistrement_systeme) VALUES (?, ?, ?, ?, ?)");
            $success = $query->execute([$numero_unique, $expediteur, $objet, $date_reception, $date_enregistrement]);
        }

        // Vérification du succès de l'opération
        if ($success) {
            header("Location: list_dossiers.php");
            exit;
        } else {
            $error_message = "Erreur lors de l'enregistrement du dossier.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $dossier ? "Modifier un dossier" : "Ajouter un dossier" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-500 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold"><?= $dossier ? "Modifier un dossier" : "Ajouter un dossier" ?></h1>
            <nav>
                <a href="list_dossiers.php" class="text-white hover:underline">Retour à la liste des dossiers</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-4"><?= $dossier ? "Modification" : "Création" ?> d'un dossier</h2>

        <?php if (isset($error_message)): ?>
            <p class="text-red-500"><?= $error_message; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label for="numero_unique" class="block text-gray-700">Numéro Unique</label>
                <input type="text" id="numero_unique" name="numero_unique" class="w-full px-3 py-2 border rounded"
                    required value="<?= $dossier ? sanitize($dossier['numero_unique']) : '' ?>">
            </div>
            <div class="mb-4">
                <label for="expediteur" class="block text-gray-700">Expéditeur</label>
                <input type="text" id="expediteur" name="expediteur" class="w-full px-3 py-2 border rounded" required
                    value="<?= $dossier ? sanitize($dossier['expediteur']) : '' ?>">
            </div>
            <div class="mb-4">
                <label for="objet" class="block text-gray-700">Objet de la demande</label>
                <input type="text" id="objet" name="objet" class="w-full px-3 py-2 border rounded" required
                    value="<?= $dossier ? sanitize($dossier['objet']) : '' ?>">
            </div>
            <div class="mb-4">
                <label for="date_reception" class="block text-gray-700">Date de réception à la guérite</label>
                <input type="date" id="date_reception" name="date_reception" class="w-full px-3 py-2 border rounded"
                    required value="<?= $dossier ? sanitize($dossier['date_reception_guerite']) : '' ?>">
            </div>
            <button type="submit"
                class="w-full bg-blue-500 text-white py-2 rounded"><?= $dossier ? "Modifier" : "Ajouter" ?></button>
        </form>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="container mx-auto text-center">
            <p>© 2025 Gestion des Dossiers. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>