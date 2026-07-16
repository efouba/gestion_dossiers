<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../fpdf/fpdf.php';

// Vérification de la méthode et authentification
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['archive_id'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Accès non autorisé');
}

// Récupération et validation de l'ID
$archive_id = (int)$_POST['archive_id'];
if ($archive_id <= 0) {
    header('HTTP/1.0 400 Bad Request');
    die('ID d\'archive invalide');
}

try {
    // Récupération des données
    $query = $pdo->prepare("
        SELECT archives.*, 
               users.nom AS agent_nom, 
               users.prenom AS agent_prenom,
               users.email_pro AS agent_email
        FROM archives 
        LEFT JOIN users ON archives.traite_par = users.id 
        WHERE archives.id = ?
    ");
    $query->execute([$archive_id]);
    $archive = $query->fetch(PDO::FETCH_ASSOC);

    if (!$archive) {
        header('HTTP/1.0 404 Not Found');
        die('Archive introuvable');
    }

    // Initialisation du PDF
    class PDF extends FPDF {
        // En-tête personnalisé
        function Header() {
            // Logo (ajuster le chemin selon votre structure)
            if (file_exists(__DIR__ . '/../assets/images/logo.png')) {
                $this->Image(__DIR__ . '/../assets/images/logo.png', 10, 10, 30);
            }
            // Titre
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 15, utf8_decode("FICHE D'ARCHIVAGE"), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, utf8_decode("Référence: ARCH-" . str_pad($GLOBALS['archive']['id'], 6, '0', STR_PAD_LEFT)), 0, 1, 'C');
            $this->Ln(10);
        }

        // Pied de page personnalisé
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, utf8_decode('Page ' . $this->PageNo() . '/{nb} - Généré le ' . date('d/m/Y à H:i')), 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages(); // Pour le numéro de page dans le footer
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    // Section principale
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, utf8_decode('Détails de l\'archive'), 0, 1);
    $pdf->Ln(5);

    // Fonction pour ajouter une ligne au PDF
    function addInfoLine($pdf, $label, $value, $labelWidth = 50) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($labelWidth, 8, utf8_decode($label . ':'), 0, 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 8, utf8_decode($value), 0, 1);
        $pdf->Ln(2);
    }

    // Informations de base
    addInfoLine($pdf, 'Objet', $archive['objet']);
    addInfoLine($pdf, 'Date', date('d/m/Y', strtotime($archive['date'])));
    addInfoLine($pdf, 'Année', $archive['annee']);
    addInfoLine($pdf, 'Décision', $archive['decision'] ?? 'Non spécifiée');
    addInfoLine($pdf, 'Traité par', $archive['agent_nom'] . ' ' . $archive['agent_prenom']);
    addInfoLine($pdf, 'Email agent', $archive['agent_email'] ?? 'Non spécifié');

    // Ajout du fichier joint s'il existe
    if (!empty($archive['fiche_archive']) && file_exists(__DIR__ . '/../files/' . $archive['fiche_archive'])) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, utf8_decode('Document archivé joint:'), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, utf8_decode(basename($archive['fiche_archive'])), 0, 1);
        $pdf->Cell(0, 6, utf8_decode('Taille: ' . formatBytes(filesize(__DIR__ . '/../files/' . $archive['fiche_archive']))), 0, 1);
    }

    // Génération du PDF
    $pdf->Output('archive_' . $archive_id . '.pdf', 'I');

} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}

// Fonction utilitaire pour formater la taille des fichiers
function formatBytes($bytes, $precision = 2) {
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}