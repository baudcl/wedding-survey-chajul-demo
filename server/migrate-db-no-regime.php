<?php
/**
 * Script de migration pour supprimer la colonne 'regimes' de la base de données
 * Usage: Placez ce fichier dans le dossier server/ et exécutez-le via le navigateur ou en CLI
 */

// Configuration
$db_dir = __DIR__ . '/db';
$db_file = $db_dir . '/responses.db';
$backup_dir = __DIR__ . '/backups';
$log_file = __DIR__ . '/migration.log';

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
        echo "<p>[$date] $message</p>\n";
        flush(); // Force l'affichage immédiat
    }
}

// En-tête HTML pour le navigateur
if (!$is_cli) {
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Migration Base de Données</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
        </style>
    </head>
    <body>
    <h1>Migration de la base de données - Suppression de la colonne "regimes"</h1>';
}

log_message("=== Début de la migration ===");

try {
    // Vérifier si la base de données existe
    if (!file_exists($db_file)) {
        log_message("ERREUR: Base de données non trouvée: $db_file");
        exit(1);
    }
    
    // Créer le répertoire de sauvegarde s'il n'existe pas
    if (!is_dir($backup_dir)) {
        if (mkdir($backup_dir, 0755, true)) {
            log_message("Répertoire de sauvegarde créé: $backup_dir");
        } else {
            log_message("ERREUR: Impossible de créer le répertoire de sauvegarde");
            exit(1);
        }
    }
    
    // Créer une sauvegarde avant migration
    $backup_file = $backup_dir . '/responses_before_migration_' . date('Y-m-d_H-i-s') . '.db';
    if (copy($db_file, $backup_file)) {
        log_message("Sauvegarde créée: $backup_file");
    } else {
        log_message("ERREUR: Impossible de créer une sauvegarde");
        exit(1);
    }
    
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si la colonne 'regimes' existe
    $columns = $db->query("PRAGMA table_info(responses)")->fetchAll(PDO::FETCH_ASSOC);
    $regimes_column_exists = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'regimes') {
            $regimes_column_exists = true;
            break;
        }
    }
    
    if (!$regimes_column_exists) {
        log_message("INFO: La colonne 'regimes' n'existe pas dans la table. Migration non nécessaire.");
        if (!$is_cli) {
            echo '<p class="info">Migration terminée - Aucune action nécessaire.</p>';
            echo '</body></html>';
        }
        exit(0);
    }
    
    log_message("INFO: Colonne 'regimes' détectée. Début de la migration...");
    
    // Compter les enregistrements avant migration
    $count_before = $db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
    log_message("Nombre d'enregistrements avant migration: $count_before");
    
    // Commencer une transaction
    $db->beginTransaction();
    
    try {
        // 1. Créer la nouvelle table sans la colonne 'regimes'
        $create_sql = "CREATE TABLE responses_new (
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
            precisions_allergies TEXT,
            chanson TEXT,
            suggestion_magique TEXT,
            mot_maries TEXT
        )";
        
        $db->exec($create_sql);
        log_message("Nouvelle table 'responses_new' créée");
        
        // 2. Copier les données (sans la colonne 'regimes')
        $copy_sql = "INSERT INTO responses_new (
            id, submission_id, date, prenom, nom, email, telephone, adresse, 
            code_postal, ville, pays, adultes, enfants_present, enfants, 
            hebergement, precisions_allergies, chanson, suggestion_magique, mot_maries
        ) SELECT 
            id, submission_id, date, prenom, nom, email, telephone, adresse, 
            code_postal, ville, pays, adultes, enfants_present, enfants, 
            hebergement, precisions_allergies, chanson, suggestion_magique, mot_maries 
        FROM responses";
        
        $db->exec($copy_sql);
        log_message("Données copiées vers la nouvelle table");
        
        // 3. Vérifier que le nombre d'enregistrements correspond
        $count_new = $db->query("SELECT COUNT(*) FROM responses_new")->fetchColumn();
        
        if ($count_before != $count_new) {
            throw new Exception("Erreur: nombre d'enregistrements différent ($count_before vs $count_new)");
        }
        
        log_message("Vérification OK: $count_new enregistrements copiés");
        
        // 4. Supprimer l'ancienne table
        $db->exec("DROP TABLE responses");
        log_message("Ancienne table supprimée");
        
        // 5. Renommer la nouvelle table
        $db->exec("ALTER TABLE responses_new RENAME TO responses");
        log_message("Nouvelle table renommée");
        
        // Valider la transaction
        $db->commit();
        log_message("Transaction validée");
        
        // 6. Optimiser la base de données
        $db->exec('VACUUM');
        log_message("Base de données optimisée");
        
        log_message("=== Migration terminée avec succès ===");
        
        if (!$is_cli) {
            echo '<p class="success"><strong>Migration réussie !</strong></p>';
            echo '<p>La colonne "regimes" a été supprimée avec succès.</p>';
            echo '<p>Sauvegarde disponible: ' . basename($backup_file) . '</p>';
            echo '<p><a href="admin.php">Retourner à l\'administration</a></p>';
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    log_message("ERREUR: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    
    if (!$is_cli) {
        echo '<p class="error">ERREUR: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Consultez le fichier de log pour plus de détails: ' . basename($log_file) . '</p>';
        echo '</body></html>';
    }
    
    exit(1);
}

if (!$is_cli) {
    echo '</body></html>';
}

exit(0);
?>