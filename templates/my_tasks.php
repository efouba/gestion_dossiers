<?php
// En tête de tous vos fichiers
require_once __DIR__ . '/../includes/path.php';
require_once INCLUDES_DIR . '/config.php';
require_once INCLUDES_DIR . '/functions.php';

startSecureSession();

$user_id = $_SESSION['user_id'];
$query = $pdo->prepare("SELECT * FROM taches WHERE responsable_id = ?");
$query->execute([$user_id]);
$taches = $query->fetchAll();
?>

<h1>Mes tâches</h1>
<table>
    <tr>
        <th>Intitulé</th>
        <th>Date d'attribution</th>
        <th>Statut</th>
        <th>Date terminée</th>
    </tr>
    <?php foreach ($taches as $tache): ?>
    <tr>
        <td><?= $tache['intitule'] ?></td>
        <td><?= $tache['date_attribution'] ?></td>
        <td><?= $tache['statut'] ?></td>
        <td><?= $tache['date_terminee'] ?: 'En Cours' ?></td>
    </tr>
    <?php endforeach; ?>
</table>