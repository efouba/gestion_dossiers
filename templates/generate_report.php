<?php
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();
require '../fpdf/fpdf.php';


// Récupérer les dates de début et de fin de la semaine
$start_date = sanitize($_POST['start_date']);
$end_date = sanitize($_POST['end_date']);
$user_id = $_SESSION['user_id'];

// Récupérer les tâches entre lundi et vendredi
$query = $pdo->prepare("
    SELECT * FROM taches 
    WHERE responsable_id = ? 
    AND date_attribution BETWEEN ? AND ? 
    ORDER BY statut, date_attribution ASC
");
$query->execute([$user_id, $start_date, $end_date]);
$taches = $query->fetchAll();

if (isset($_POST['generate_pdf']) && $_POST['generate_pdf'] == 'true') {
    // Générer un rapport au format PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode("Rapport Hebdomadaire des Tâches"), 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);
    foreach (['Initial', 'En cours', 'Terminee'] as $statut) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode("Statut : " . $statut), 0, 1);
        $pdf->SetFont('Arial', '', 12);
        foreach ($taches as $tache) {
            if ($tache['statut'] == $statut) {
                $pdf->Cell(0, 10, utf8_decode("Intitulé : " . $tache['intitule']), 0, 1);
                $pdf->Cell(0, 10, utf8_decode("Description : " . $tache['description']), 0, 1);
                $pdf->Cell(0, 10, utf8_decode("Date d'attribution : " . $tache['date_attribution']), 0, 1);
                $pdf->Ln(5);
            }
        }
        $pdf->Ln(10);
    }

    // Afficher ou télécharger le PDF
    $pdf->Output();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Hebdomadaire</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <header class="bg-blue-500 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Rapport Hebdomadaire</h1>
            <nav>
                <a href="tasks.php" class="text-white hover:underline">Retour aux tâches</a>
                <a href="../auth/logout.php" class="ml-4 text-white hover:underline">Se déconnecter</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-4">Tâches de la semaine (<?= $start_date ?> à <?= $end_date ?>)</h2>
        <table class="w-full bg-white rounded shadow-lg overflow-hidden mb-6">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2">Intitulé</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Statut</th>
                    <th class="px-4 py-2">Date d'attribution</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taches as $tache): ?>
                <tr>
                    <td class="border px-4 py-2"><?= sanitize($tache['intitule']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['description']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['statut']) ?></td>
                    <td class="border px-4 py-2"><?= sanitize($tache['date_attribution']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Générer le PDF -->
        <form method="POST" action="">
            <input type="hidden" name="start_date" value="<?= $start_date ?>">
            <input type="hidden" name="end_date" value="<?= $end_date ?>">
            <input type="hidden" name="generate_pdf" value="true">
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Exporter en PDF</button>
        </form>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="container mx-auto text-center">
            <p>© 2025 Gestion des Tâches. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>