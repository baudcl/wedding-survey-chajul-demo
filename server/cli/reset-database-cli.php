<?php
// Script de réinitialisation de la base de données SQLite
// Optimisé pour être exécuté en ligne de commande
// Usage: php reset_cli.php [--force]

// Configuration
$db_file = __DIR__ . '/responses.db';
$backup_dir = __DIR__ . '/backups';
$log_file = __DIR__ . '/reset.log';

// Détection du mode (CLI ou navigateur)
$is_cli = (php_sapi_name() === 'cli');

// Fonction pour afficher et journaliser les messages
function log_message($message) {
    global $log_file, $is_cli;
    $date = date('Y-m-d H:i:s');
    
    // Journaliser dans le fichier
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
    
    // Afficher dans la console ou le navigateur
    if ($is_cli) {
        echo "[$date] $message\n";
    } else {
        echo "$message<br>\n";
    }
}

// En-tête
log_message("=== Début de la réinitialisation de la base de données ===");
log_message("Répertoire : " . __DIR__);
log_message("Base de données : $db_file");
log_message("Répertoire de sauvegarde : $backup_dir");

// Vérifier si l'option --force est spécifiée en mode CLI
$force = false;
if ($is_cli) {
    global $argv;
    $force = in_array('--force', $argv);
}

// Demander confirmation (sauf si --force est spécifié)
if (!$force && $is_cli) {
    echo "ATTENTION: Cette action va effacer toutes les données de la base de données.\n";
    echo "Voulez-vous continuer ? [y/N] ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if (strtolower($line) !== 'y') {
        echo "Opération annulée.\n";
        exit(0);
    }
    fclose($handle);
}

// Si on est dans un navigateur et sans confirmation
if (!$is_cli && !isset($_GET['confirm'])) {
    echo '<h1>Réinitialisation de la base de données</h1>';
    echo '<p style="color: red; font-weight: bold;">ATTENTION : Cette action va effacer toutes les données de la base de données.</p>';
    echo '<p>Voulez-vous continuer ?</p>';
    echo '<a href="?confirm=yes" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">Oui, réinitialiser</a>';
    echo '<a href="admin.php" style="background-color: #EFA8B4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Annuler</a>';
    exit;
}

try {
    // Créer le répertoire de sauvegarde
    if (!is_dir($backup_dir)) {
        if (mkdir($backup_dir, 0755, true)) {
            log_message("Répertoire de sauvegarde créé");
        } else {
            log_message("ERREUR: Impossible de créer le répertoire de sauvegarde");
            exit(1);
        }
    }
    
    // Vérifier si la base de données existe
    if (file_exists($db_file)) {
        // Vérifier si on peut lire la base de données
        if (!is_readable($db_file)) {
            log_message("ERREUR: La base de données existe mais n'est pas accessible en lecture");
            exit(1);
        }
        
        // Créer une sauvegarde avant la réinitialisation
        $backup_file = $backup_dir . '/responses_' . date('Y-m-d_H-i-s') . '.db';
        if (copy($db_file, $backup_file)) {
            log_message("Sauvegarde créée: $backup_file");
        } else {
            log_message("ERREUR: Impossible de créer une sauvegarde");
            exit(1);
        }
        
        // Extraire les statistiques avant la réinitialisation
        try {
            $db = new PDO('sqlite:' . $db_file);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Compter les entrées
            $count = $db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
            log_message("Nombre d'entrées avant réinitialisation: $count");
            
            // Fermer la connexion
            $db = null;
        } catch (PDOException $e) {
            log_message("Erreur de lecture des statistiques: " . $e->getMessage());
        }
        
        // Supprimer la base de données
        if (unlink($db_file)) {
            log_message("Base de données supprimée");
        } else {
            log_message("ERREUR: Impossible de supprimer la base de données");
            exit(1);
        }
    } else {
        log_message("Aucune base de données existante à réinitialiser");
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
    
} catch (Exception $e) {
    log_message("ERREUR CRITIQUE: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    exit(1);
}

log_message("=== Réinitialisation terminée avec succès ===");

// Si on n'est pas en mode CLI, afficher un lien pour revenir à l'administration
if (!$is_cli) {
    echo '<p><a href="admin.php" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Retourner à l\'administration</a>';
    echo '<a href="sync_cli.php" style="background-color: #EFA8B4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;">Synchroniser les données</a></p>';
}

exit(0);
?>