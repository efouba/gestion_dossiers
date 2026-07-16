<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $intitule = $_POST['intitule'];
    $responsable_id = $_POST['responsable_id'];
    $date_attribution = date('Y-m-d'); // Date du jour
    $statut = 'Non démarrée';

    $query = $pdo->prepare("INSERT INTO taches (intitule, date_attribution, responsable_id, statut) VALUES (?, ?, ?, ?)");
    if ($query->execute([$intitule, $date_attribution, $responsable_id, $statut])) {
        echo "Tâche créée avec succès !";
    } else {
        echo "Erreur lors de la création de la tâche.";
    }
}
?>

<form method="POST" action="">
    <input type="text" name="intitule" placeholder="Intitulé de la tâche" required>
    <select name="responsable_id" required>
        <?php
        $users = $pdo->query("SELECT id, nom, prenom FROM users")->fetchAll();
        foreach ($users as $user) {
            echo "<option value='{$user['id']}'>{$user['nom']} {$user['prenom']}</option>";
        }
        ?>
    </select>
    <button type="submit">Créer la tâche</button>
</form>