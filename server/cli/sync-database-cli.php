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
        precisions_allergies TEXT,
        chanson TEXT,
        suggestion_magique TEXT,
        mot_maries TEXT
    )");
    log_message("Table 'responses' vérifiée/créée");
    
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
            // Préparer l'insertion
            $stmt = $db->prepare("INSERT INTO responses (
                submission_id, date, prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
                adultes, enfants_present, enfants, hebergement, precisions_allergies, 
                chanson, suggestion_magique, mot_maries
            ) VALUES (
                :submission_id, :date, :prenom, :nom, :email, :telephone, :adresse, :code_postal, :ville, :pays,
                :adultes, :enfants_present, :enfants, :hebergement, :precisions_allergies,
                :chanson, :suggestion_magique, :mot_maries
            )");
            
            $stmt->execute([
                ':submission_id' => $submission_id,
                ':date' => $response['date'] ?? date('Y-m-d H:i:s'),
                ':prenom' => $response['prenom'] ?? '',
                ':nom' => $response['nom'] ?? '',
                ':email' => $response['email'],
                ':telephone' => $response['telephone'] ?? '',
                ':adresse' => $response['adresse'] ?? '',
                ':code_postal' => $response['code_postal'] ?? '',
                ':ville' => $response['ville'] ?? '',
                ':pays' => $response['pays'] ?? '',
                ':adultes' => isset($response['adultes']) ? (int)$response['adultes'] : 0,
                ':enfants_present' => $response['enfants_present'] ?? 'non',
                ':enfants' => $enfants_json,
                ':hebergement' => $response['hebergement'] ?? '',
                ':precisions_allergies' => $response['precisions_allergies'] ?? '',
                ':chanson' => $response['chanson'] ?? '',
                ':suggestion_magique' => $response['suggestion_magique'] ?? '',
                ':mot_maries' => $response['mot_maries'] ?? ''
            ]);
            
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
exit(0);
?>