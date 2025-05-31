<?php
// Script pour nettoyer la base de données SQLite
// Placez ce fichier à côté de votre handle-form.php et exécutez-le directement dans votre navigateur
// ou en ligne de commande : php reset_database.php

// Configuration
$db_dir = __DIR__ . '/db';
$db_file = $db_dir . '/responses.db';
$backup_dir = __DIR__ . '/backups';
$log_file = __DIR__ . '/reset.log';

// Fonction pour journaliser les messages
function log_message($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
    echo "$message<br>";
}

// Vérifier la confirmation
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo '<h1>Réinitialisation de la base de données</h1>';
    echo '<p style="color: red; font-weight: bold;">Attention : Cette action va effacer toutes les données de la base de données.</p>';
    echo '<p>Souhaitez-vous continuer ?</p>';
    echo '<a href="?confirm=yes" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">Oui, réinitialiser la base de données</a>';
    echo '<a href="admin.php" style="background-color: #EFA8B4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Non, retourner à l\'administration</a>';
    exit;
}

try {
    // Créer le répertoire de sauvegarde s'il n'existe pas
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Vérifier si la base de données existe
    if (file_exists($db_file)) {
        // Créer une sauvegarde avant la réinitialisation
        $backup_file = $backup_dir . '/responses_' . date('Y-m-d_H-i-s') . '.db';
        if (copy($db_file, $backup_file)) {
            log_message("Sauvegarde créée: $backup_file");
        } else {
            log_message("Erreur lors de la création de la sauvegarde");
        }
        
        // Extraire les données avant la réinitialisation (optionnel)
        try {
            $db = new PDO('sqlite:' . $db_file);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Compter les entrées
            $count = $db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
            log_message("Nombre d'entrées avant réinitialisation: $count");
            
            // Exporter les données en JSON (optionnel)
            $rows = $db->query("SELECT * FROM responses")->fetchAll(PDO::FETCH_ASSOC);
            $json_backup = $backup_dir . '/responses_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($json_backup, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            log_message("Exportation JSON créée: $json_backup");
            
            // Fermer la connexion
            $db = null;
        } catch (PDOException $e) {
            log_message("Erreur lors de l'exportation des données: " . $e->getMessage());
        }
        
        // Supprimer le fichier de base de données
        if (unlink($db_file)) {
            log_message("Base de données supprimée");
        } else {
            log_message("Erreur lors de la suppression de la base de données");
            exit;
        }
    } else {
        log_message("La base de données n'existe pas encore: $db_file");
    }
    
    // Créer une nouvelle base de données vide
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table
    $db->exec("CREATE TABLE IF NOT EXISTS responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        submission_id TEXT,
        date TEXT,
        prenom TEXT,
        nom TEXT,
        email TEXT,
        telephone TEXT,
        adresse TEXT,
        code_postal TEXT,
        ville TEXT,
        pays TEXT,
        adultes INTEGER,
        enfants_present TEXT,
        enfants TEXT,
        hebergement TEXT,
        regimes TEXT,
        precisions_allergies TEXT,
        chanson TEXT,
        suggestion_magique TEXT,
        mot_maries TEXT
    )");
    
    log_message("Nouvelle base de données créée avec succès");
    
    // Proposer options
    echo '<h2>Réinitialisation terminée</h2>';
    echo '<p>La base de données a été réinitialisée avec succès. Que souhaitez-vous faire maintenant ?</p>';
    echo '<a href="admin.php" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">Retourner à l\'administration</a>';
    echo '<a href="sync_database.php" style="background-color: #EFA8B4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Synchroniser les données depuis le fichier JSON</a>';
    
} catch (Exception $e) {
    log_message("Erreur: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    echo '<p style="color: red;">Une erreur est survenue. Consultez le fichier de log pour plus de détails.</p>';
    echo '<a href="admin.php">Retourner à l\'administration</a>';
}
?>