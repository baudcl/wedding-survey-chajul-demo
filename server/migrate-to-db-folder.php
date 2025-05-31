<?php
// Script de migration pour déplacer les fichiers dans le dossier db
// Usage: php migrate_to_db_folder.php

$log_file = __DIR__ . '/migration.log';

function log_message($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
    echo "[$date] $message\n";
}

log_message("=== Début de la migration vers le dossier db ===");

try {
    // Chemins source (actuels)
    $source_db = __DIR__ . '/responses.db';
    $source_json = __DIR__ . '/responses.json';
    
    // Chemins destination (nouveau dossier db)
    $db_dir = __DIR__ . '/db';
    $dest_db = $db_dir . '/responses.db';
    $dest_json = $db_dir . '/responses.json';
    
    // Créer le dossier db s'il n'existe pas
    if (!is_dir($db_dir)) {
        if (mkdir($db_dir, 0755, true)) {
            log_message("Dossier db créé: $db_dir");
        } else {
            throw new Exception("Impossible de créer le dossier db");
        }
    } else {
        log_message("Dossier db existe déjà: $db_dir");
    }
    
    // Déplacer la base de données SQLite
    if (file_exists($source_db)) {
        if (file_exists($dest_db)) {
            // Créer une sauvegarde du fichier existant
            $backup_name = $dest_db . '.backup.' . date('Y-m-d_H-i-s');
            if (copy($dest_db, $backup_name)) {
                log_message("Sauvegarde créée: $backup_name");
            }
        }
        
        if (rename($source_db, $dest_db)) {
            log_message("Base de données déplacée: $source_db -> $dest_db");
        } else {
            throw new Exception("Impossible de déplacer la base de données");
        }
    } else {
        log_message("Fichier source responses.db non trouvé, pas de déplacement nécessaire");
    }
    
    // Déplacer le fichier JSON
    if (file_exists($source_json)) {
        if (file_exists($dest_json)) {
            // Créer une sauvegarde du fichier existant
            $backup_name = $dest_json . '.backup.' . date('Y-m-d_H-i-s');
            if (copy($dest_json, $backup_name)) {
                log_message("Sauvegarde JSON créée: $backup_name");
            }
        }
        
        if (rename($source_json, $dest_json)) {
            log_message("Fichier JSON déplacé: $source_json -> $dest_json");
        } else {
            throw new Exception("Impossible de déplacer le fichier JSON");
        }
    } else {
        log_message("Fichier source reponses.json non trouvé, pas de déplacement nécessaire");
    }
    
    // Vérifier les permissions du nouveau dossier
    if (is_writable($db_dir)) {
        log_message("Permissions du dossier db: OK (écriture autorisée)");
    } else {
        log_message("ATTENTION: Le dossier db n'est pas accessible en écriture");
        // Tenter de corriger les permissions
        if (chmod($db_dir, 0755)) {
            log_message("Permissions corrigées pour le dossier db");
        }
    }
    
    // Vérifier les permissions des fichiers
    if (file_exists($dest_db) && is_writable($dest_db)) {
        log_message("Permissions responses.db: OK");
    } elseif (file_exists($dest_db)) {
        chmod($dest_db, 0644);
        log_message("Permissions responses.db corrigées");
    }
    
    if (file_exists($dest_json) && is_writable($dest_json)) {
        log_message("Permissions responses.json: OK");
    } elseif (file_exists($dest_json)) {
        chmod($dest_json, 0644);
        log_message("Permissions responses.json corrigées");
    }
    
    log_message("=== Migration terminée avec succès ===");
    
} catch (Exception $e) {
    log_message("ERREUR: " . $e->getMessage());
    exit(1);
}

// Proposer de mettre à jour les fichiers PHP
echo "\n";
echo "Migration terminée !\n";
echo "N'oubliez pas de :\n";
echo "1. Mettre à jour les chemins dans handle-form.php\n";
echo "2. Mettre à jour les chemins dans admin.php\n";
echo "3. Mettre à jour les chemins dans vos scripts utilitaires\n";
echo "4. Tester que tout fonctionne correctement\n";
echo "\nConsultez le fichier migration.log pour plus de détails.\n";
?>