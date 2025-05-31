<?php
// Script pour vider complètement la base de données SQLite
// Usage: php truncate_db.php [--force]

// Configuration
$db_dir = __DIR__ . '/db';
$db_file = $db_dir . '/responses.db';
$backup_dir = __DIR__ . '/backups';
$log_file = __DIR__ . '/truncate.log';

// Détection du mode (CLI ou navigateur)
$is_cli = (php_sapi_name() === 'cli');

// Fonction pour journaliser les messages
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
log_message("=== Début du vidage de la base de données ===");
log_message("Base de données: $db_file");

// Vérifier si l'option --force est spécifiée en mode CLI
$force = false;
if ($is_cli) {
    global $argv;
    $force = in_array('--force', $argv);
}

// Demander confirmation (sauf si --force est spécifié)
if (!$force && $is_cli) {
    echo "ATTENTION: Cette action va supprimer TOUTES les données de la base de données.\n";
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
    echo '<h1>Vidage de la base de données</h1>';
    echo '<p style="color: red; font-weight: bold;">ATTENTION : Cette action va supprimer TOUTES les données de la base de données.</p>';
    echo '<p>Voulez-vous continuer ?</p>';
    echo '<a href="?confirm=yes" style="background-color: #f44336; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">Oui, vider la base de données</a>';
    echo '<a href="admin.php" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Annuler</a>';
    exit;
}

try {
    // Vérifier si la base de données existe
    if (!file_exists($db_file)) {
        log_message("La base de données n'existe pas: $db_file");
        exit(1);
    }
    
    // Créer le répertoire de sauvegarde s'il n'existe pas
    if (!is_dir($backup_dir)) {
        if (mkdir($backup_dir, 0755, true)) {
            log_message("Répertoire de sauvegarde créé: $backup_dir");
        } else {
            log_message("ERREUR: Impossible de créer le répertoire de sauvegarde");
        }
    }
    
    // Créer une sauvegarde avant de vider la base de données
    $backup_file = $backup_dir . '/responses_before_truncate_' . date('Y-m-d_H-i-s') . '.db';
    if (copy($db_file, $backup_file)) {
        log_message("Sauvegarde créée: $backup_file");
    } else {
        log_message("ERREUR: Impossible de créer une sauvegarde");
        exit(1);
    }
    
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer la liste des tables
    $tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['name'];
    }
    
    log_message("Tables trouvées: " . implode(", ", $tables));
    
    // Désactiver les contraintes de clé étrangère si elles existent
    $db->exec('PRAGMA foreign_keys = OFF');
    
    // Vider chaque table
    foreach ($tables as $table) {
        try {
            // Compter les lignes avant
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            log_message("Table $table: $count lignes à supprimer");
            
            // Vider la table
            $db->exec("DELETE FROM $table");
            
            // Réinitialiser l'auto-increment
            $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
            
            log_message("Table $table vidée avec succès");
        } catch (Exception $e) {
            log_message("Erreur lors du vidage de la table $table: " . $e->getMessage());
        }
    }
    
    // Réactiver les contraintes de clé étrangère
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Optimiser la base de données
    $db->exec('VACUUM');
    log_message("Base de données optimisée (VACUUM)");
    
    log_message("Toutes les tables ont été vidées avec succès");
    
} catch (Exception $e) {
    log_message("ERREUR CRITIQUE: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    exit(1);
}

log_message("=== Vidage de la base de données terminé ===");

// Si on n'est pas en mode CLI, afficher un lien pour revenir à l'administration
if (!$is_cli) {
    echo '<p style="color: green; font-weight: bold;">La base de données a été vidée avec succès.</p>';
    echo '<p><a href="admin.php" style="background-color: #8DB1A8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Retourner à l\'administration</a>';
    echo '<a href="sync_adaptive.php" style="background-color: #EFA8B4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;">Synchroniser les données</a></p>';
}

exit(0);
?>