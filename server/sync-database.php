<?php
// Script de synchronisation entre le fichier JSON et la base de données SQLite
// Placez ce fichier à côté de votre handle-form.php et exécutez-le directement dans votre navigateur
// ou en ligne de commande : php sync_database.php

// Configuration
$db_dir = __DIR__ . '/db';
$json_file = $db_dir . '/responses.json';
$db_file = $db_dir . '/responses.db';
$log_file = __DIR__ . '/sync.log';


// Créer le dossier db s'il n'existe pas
if (!is_dir($db_dir)) {
    if (mkdir($db_dir, 0755, true)) {
        log_message("Dossier db créé: $db_dir");
    } else {
        log_message("ERREUR: Impossible de créer le dossier db");
        exit(1);
    }
}

// Fonction pour journaliser les messages
function log_message($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
    echo "$message<br>";
}

// Vérifier si les fichiers existent
if (!file_exists($json_file)) {
    log_message("Erreur : Le fichier JSON n'existe pas: $json_file");
    exit;
}

try {
    // Lire le fichier JSON
    $json_content = file_get_contents($json_file);
    
    // Vérifier le format du JSON
    if (empty($json_content)) {
        log_message("Erreur : Fichier JSON vide");
        exit;
    }
    
    // Tenter de décoder le JSON
    $responses = [];
    
    // Vérifier si le JSON commence par "[" (format tableau)
    if ($json_content[0] === '[') {
        $responses = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("Erreur de décodage JSON: " . json_last_error_msg());
            exit;
        }
    } else {
        // Format non standard - chaque ligne peut être un objet JSON séparé
        $lines = explode("\n", $json_content);
        foreach ($lines as $line) {
            $line = trim($line);
            // Supprimer la virgule à la fin si elle existe
            if (substr($line, -1) === ',') {
                $line = substr($line, 0, -1);
            }
            if (!empty($line) && $line[0] === '{') {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $responses[] = $decoded;
                }
            }
        }
    }
    
    log_message("Nombre d'entrées trouvées dans le fichier JSON: " . count($responses));
    
    // Connexion à la base de données
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table si elle n'existe pas
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
    foreach ($responses as $response) {
        // Vérifier si l'email existe déjà
        if (in_array($response['email'], $existingEmails)) {
            $skipped++;
            continue;
        }
        
        // Générer un ID de soumission s'il n'existe pas
        $submission_id = isset($response['id']) ? $response['id'] : md5($response['email'] . time());
        
        // Traiter les données des enfants
        $enfants_json = '';
        if (isset($response['enfants']) && is_array($response['enfants']) && !empty($response['enfants'])) {
            $enfants_json = json_encode($response['enfants'], JSON_UNESCAPED_UNICODE);
        }
        
        // Préparer l'insertion
        $stmt = $db->prepare("INSERT INTO responses (
            submission_id, date, prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
            adultes, enfants_present, enfants, hebergement, regimes, precisions_allergies, 
            chanson, suggestion_magique, mot_maries
        ) VALUES (
            :submission_id, :date, :prenom, :nom, :email, :telephone, :adresse, :code_postal, :ville, :pays,
            :adultes, :enfants_present, :enfants, :hebergement, :regimes, :precisions_allergies,
            :chanson, :suggestion_magique, :mot_maries
        )");
        
        $stmt->execute([
            ':submission_id' => $submission_id,
            ':date' => $response['date'] ?? date('Y-m-d H:i:s'),
            ':prenom' => $response['prenom'] ?? '',
            ':nom' => $response['nom'] ?? '',
            ':email' => $response['email'] ?? '',
            ':telephone' => $response['telephone'] ?? '',
            ':adresse' => $response['adresse'] ?? '',
            ':code_postal' => $response['code_postal'] ?? '',
            ':ville' => $response['ville'] ?? '',
            ':pays' => $response['pays'] ?? '',
            ':adultes' => isset($response['adultes']) ? (int)$response['adultes'] : 0,
            ':enfants_present' => $response['enfants_present'] ?? 'non',
            ':enfants' => $enfants_json,
            ':hebergement' => $response['hebergement'] ?? '',
            ':regimes' => $response['regimes'] ?? '',
            ':precisions_allergies' => $response['precisions_allergies'] ?? '',
            ':chanson' => $response['chanson'] ?? '',
            ':suggestion_magique' => $response['suggestion_magique'] ?? '',
            ':mot_maries' => $response['mot_maries'] ?? ''
        ]);
        
        $inserted++;
        $existingEmails[] = $response['email']; // Ajouter à la liste pour éviter les doublons
    }
    
    log_message("Synchronisation terminée: $inserted entrées ajoutées, $skipped entrées ignorées (déjà présentes)");
    
} catch (Exception $e) {
    log_message("Erreur: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
}
?>