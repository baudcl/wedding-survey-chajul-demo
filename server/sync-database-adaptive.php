<?php
// Script de synchronisation adaptatif pour s'ajuster à la structure de table existante
// Usage: php sync_adaptive.php

// Configuration
$db_dir = __DIR__ . '/db';
$json_file = $db_dir . '/responses.json';
$db_file = $db_dir . '/responses.db';
$log_file = __DIR__ . '/sync.log';

// Fonction pour journaliser les messages
function log_message($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
    echo "$message<br>\n";
}

// En-tête
log_message("=== Début de la synchronisation adaptative ===");
log_message("Répertoire : " . __DIR__);
log_message("Fichier JSON : $json_file");
log_message("Base de données : $db_file");

// Vérifier si les fichiers existent
if (!file_exists($json_file)) {
    log_message("ERREUR : Le fichier JSON n'existe pas");
    exit(1);
}

try {
    // Lire le fichier JSON
    $json_content = file_get_contents($json_file);
    log_message("Fichier JSON lu : " . strlen($json_content) . " octets");
    
    // Vérifier le format du JSON
    if (empty($json_content)) {
        log_message("ERREUR : Fichier JSON vide");
        exit(1);
    }
    
    // Tenter de décoder le JSON
    $responses = [];
    
    // Vérifier si le JSON commence par "[" (format tableau)
    if ($json_content[0] === '[') {
        $responses = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("ERREUR de décodage JSON: " . json_last_error_msg());
            exit(1);
        }
    } else {
        // Format non standard - chaque ligne peut être un objet JSON séparé
        log_message("Format JSON non standard détecté, traitement ligne par ligne");
        $lines = explode("\n", $json_content);
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            // Supprimer la virgule à la fin si elle existe
            if (substr($line, -1) === ',') {
                $line = substr($line, 0, -1);
            }
            if (!empty($line) && $line[0] === '{') {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $responses[] = $decoded;
                } else {
                    log_message("Erreur ligne $line_num : " . json_last_error_msg());
                }
            }
        }
    }
    
    log_message("Nombre d'entrées trouvées dans le fichier JSON: " . count($responses));
    
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier la structure de la table existante
    $has_submission_id = false;
    $columns = [];
    
    try {
        // Vérifier si la table existe
        $stmt = $db->query("PRAGMA table_info(responses)");
        $table_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($table_info) > 0) {
            log_message("Table 'responses' existante détectée");
            
            // Récupérer les noms de colonnes
            foreach ($table_info as $column) {
                $columns[] = $column['name'];
                if ($column['name'] === 'submission_id') {
                    $has_submission_id = true;
                }
            }
            
            log_message("Colonnes existantes: " . implode(", ", $columns));
            
            // Ajouter la colonne submission_id si elle n'existe pas
            if (!$has_submission_id) {
                log_message("Ajout de la colonne 'submission_id' à la table existante");
                $db->exec("ALTER TABLE responses ADD COLUMN submission_id TEXT");
                $columns[] = 'submission_id';
                $has_submission_id = true;
            }
        } else {
            // La table n'existe pas, on la crée
            log_message("Table 'responses' non trouvée, création...");
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
                precisions_allergies TEXT,
                chanson TEXT,
                suggestion_magique TEXT,
                mot_maries TEXT
            )");
            
            // Définir les colonnes pour une nouvelle table
            $columns = [
                'id', 'submission_id', 'date', 'prenom', 'nom', 'email', 'telephone', 'adresse',
                'code_postal', 'ville', 'pays', 'adultes', 'enfants_present', 'enfants',
                'hebergement', 'precisions_allergies', 'chanson', 'suggestion_magique', 'mot_maries'
            ];
            $has_submission_id = true;
        }
    } catch (PDOException $e) {
        log_message("Erreur lors de la vérification de la structure: " . $e->getMessage());
        // On suppose que la table n'existe pas
        log_message("Tentative de création de la table 'responses'");
        
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
            precisions_allergies TEXT,
            chanson TEXT,
            suggestion_magique TEXT,
            mot_maries TEXT
        )");
        
        // Définir les colonnes pour une nouvelle table
        $columns = [
            'id', 'submission_id', 'date', 'prenom', 'nom', 'email', 'telephone', 'adresse',
            'code_postal', 'ville', 'pays', 'adultes', 'enfants_present', 'enfants',
            'hebergement', 'precisions_allergies', 'chanson', 'suggestion_magique', 'mot_maries'
        ];
        $has_submission_id = true;
    }
    
    // Récupérer les emails déjà enregistrés dans la base de données
    $existingEmails = [];
    $stmt = $db->query("SELECT email FROM responses");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingEmails[] = $row['email'];
    }
    
    log_message("Nombre d'entrées déjà dans la base de données: " . count($existingEmails));
    
    // Compteurs
    $inserted = 0;
    $skipped = 0;
    
    // Insérer les réponses JSON dans la base de données
    foreach ($responses as $index => $response) {
        // Vérifier que l'email existe
        if (!isset($response['email']) || empty($response['email'])) {
            log_message("Entrée #$index ignorée: pas d'email");
            $skipped++;
            continue;
        }
        
        // Vérifier si l'email existe déjà
        if (in_array($response['email'], $existingEmails)) {
            log_message("Entrée pour {$response['email']} ignorée: déjà présente");
            $skipped++;
            continue;
        }
        
        // Générer un ID de soumission s'il n'existe pas
        $submission_id = isset($response['id']) ? $response['id'] : md5($response['email'] . time());
        
        // Traiter les données des enfants
        $enfants_json = '';
        if (isset($response['enfants'])) {
            // Si c'est déjà une chaîne JSON, l'utiliser directement
            if (is_string($response['enfants'])) {
                $enfants_json = $response['enfants'];
            } 
            // Si c'est un tableau, l'encoder en JSON
            else if (is_array($response['enfants'])) {
                $enfants_json = json_encode($response['enfants'], JSON_UNESCAPED_UNICODE);
            }
        }
        
        try {
            // Construire la requête d'insertion de manière dynamique
            $fields = [];
            $placeholders = [];
            $data = [];
            
            // Ajouter les colonnes disponibles
            foreach ($columns as $column) {
                // Ignorer la colonne 'id' car c'est une auto-increment
                if ($column === 'id') continue;
                
                $fields[] = $column;
                $placeholders[] = ":$column";
                
                // Définir la valeur en fonction de la colonne
                switch ($column) {
                    case 'submission_id':
                        $data[":$column"] = $submission_id;
                        break;
                    case 'date':
                        $data[":$column"] = $response['date'] ?? date('Y-m-d H:i:s');
                        break;
                    case 'prenom':
                    case 'nom':
                    case 'email':
                    case 'telephone':
                    case 'adresse':
                    case 'code_postal':
                    case 'ville':
                    case 'pays':
                    case 'hebergement':
                    case 'precisions_allergies':
                    case 'chanson':
                    case 'suggestion_magique':
                    case 'mot_maries':
                        $data[":$column"] = $response[$column] ?? '';
                        break;
                    case 'adultes':
                        $data[":$column"] = isset($response['adultes']) ? (int)$response['adultes'] : 0;
                        break;
                    case 'enfants_present':
                        $data[":$column"] = $response['enfants_present'] ?? 'non';
                        break;
                    case 'enfants':
                        $data[":$column"] = $enfants_json;
                        break;
                    default:
                        // Colonne inconnue, mettre une valeur vide
                        $data[":$column"] = '';
                }
            }
            
            // Construire la requête SQL
            $sql = "INSERT INTO responses (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            log_message("Requête SQL: $sql");
            
            // Préparer et exécuter la requête
            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            
            $inserted++;
            $existingEmails[] = $response['email']; // Ajouter à la liste pour éviter les doublons
            log_message("Entrée pour {$response['email']} ajoutée avec succès");
        } catch (PDOException $e) {
            log_message("Erreur lors de l'insertion de {$response['email']}: " . $e->getMessage());
        }
    }
    
    log_message("Synchronisation terminée: $inserted entrées ajoutées, $skipped entrées ignorées");
    
    // Vérifier le contenu de la base de données
    $count = $db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
    log_message("Nombre total d'entrées après synchronisation: $count");
    
} catch (Exception $e) {
    log_message("ERREUR CRITIQUE: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    exit(1);
}

log_message("=== Fin de la synchronisation ===");
?>