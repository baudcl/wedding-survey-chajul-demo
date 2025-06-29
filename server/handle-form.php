<?php
// Chargement automatique de Composer et PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration des chemins vers le dossier db
$db_dir = __DIR__ . '/db';
$db_file = $db_dir . '/responses.db';
$json_file = $db_dir . '/responses.json';
$log_file = __DIR__ . '/form.log';

// Créer le dossier db s'il n'existe pas
if (!is_dir($db_dir)) {
    if (!mkdir($db_dir, 0755, true)) {
        log_message("ERREUR: Impossible de créer le dossier db");
        return_json(['success' => false, 'message' => 'Erreur de configuration du serveur'], 500);
    }
    log_message("Dossier db créé: $db_dir");
}

// Configuration de base
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Fonctions utilitaires
function log_message($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $message\n", FILE_APPEND | LOCK_EX);
}

function return_json($data, $status_code = 200) {
    // Vider tout buffer de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    http_response_code(200);
    exit;
}

// Protection contre les soumissions multiples
session_start();
$submission_id = md5($_SERVER['REMOTE_ADDR'] . time());

// Vérifier si le même formulaire a été soumis récemment
if (isset($_SESSION['last_form_submission']) && time() - $_SESSION['last_form_submission'] < 5) {
    log_message("Soumission multiple détectée (délai de 5 secondes)");
    return_json(['success' => true, 'message' => 'Votre réponse a déjà été enregistrée. Merci !']);
}

// Début du traitement
try {
    // Log des informations de base
    log_message("=== Nouvelle soumission de formulaire ===");
    log_message("Méthode: " . $_SERVER['REQUEST_METHOD']);
    log_message("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'non défini'));

    // Vérification de la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        log_message("Méthode non autorisée: " . $_SERVER['REQUEST_METHOD']);
        return_json(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    // Vérification des champs requis minimaux
    if (empty($_POST['email']) || empty($_POST['prenom']) || empty($_POST['nom'])) {
        log_message("Champs requis manquants");
        return_json(['success' => false, 'message' => 'Champs requis manquants'], 400);
    }

    // Traitement des données des enfants
    $enfants = [];
    if (isset($_POST['enfants_present']) && $_POST['enfants_present'] === 'oui') {
        if (isset($_POST['prenom_enfant']) && is_array($_POST['prenom_enfant'])) {
            $prenoms = $_POST['prenom_enfant'];
            $ages = isset($_POST['age_enfant']) && is_array($_POST['age_enfant']) ? $_POST['age_enfant'] : [];
            
            for ($i = 0; $i < count($prenoms); $i++) {
                $prenom = $prenoms[$i];
                $age = isset($ages[$i]) ? $ages[$i] : '';
                
                if (!empty($prenom)) {
                    $enfants[] = [
                        'prenom' => $prenom,
                        'age' => $age
                    ];
                }
            }
        }
    }
    
    // Préparation des données
    $data = [
        'id' => $submission_id,
        'date' => date('Y-m-d H:i:s'),
        'prenom' => $_POST['prenom'] ?? '',
        'nom' => $_POST['nom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'telephone' => ($_POST['indicatif'] ?? '') . ' ' . ($_POST['telephone'] ?? ''),
        'adresse' => $_POST['adresse'] ?? '',
        'code_postal' => $_POST['code_postal'] ?? '',
        'ville' => $_POST['ville'] ?? '',
        'pays' => $_POST['pays'] ?? '',
        'adultes' => isset($_POST['adultes']) ? (int)$_POST['adultes'] : 0,
        'enfants_present' => $_POST['enfants_present'] ?? 'non',
        'enfants' => $enfants,
        'hebergement' => $_POST['hebergement'] ?? '',
        'precisions_allergies' => $_POST['precisions_allergies'] ?? '',
        'chanson' => $_POST['chanson'] ?? '',
        'suggestion_magique' => $_POST['suggestion_magique'] ?? '',
        'mot_maries' => $_POST['mot_maries'] ?? ''
    ];
    
    // 1. ENREGISTREMENT DANS LA BASE DE DONNÉES
    try {
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

        // Vérifier si l'email existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM responses WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetchColumn() > 0) {
            log_message("Email en double détecté dans la BDD: " . $data['email']);
            return_json(['success' => true, 'message' => 'Votre réponse a déjà été enregistrée. Merci !']);
        }

        // Préparer les données des enfants pour la base de données
        $enfants_json = !empty($enfants) ? json_encode($enfants, JSON_UNESCAPED_UNICODE) : '';

        // Insérer les données
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
            ':date' => $data['date'],
            ':prenom' => $data['prenom'],
            ':nom' => $data['nom'],
            ':email' => $data['email'],
            ':telephone' => $data['telephone'],
            ':adresse' => $data['adresse'],
            ':code_postal' => $data['code_postal'],
            ':ville' => $data['ville'],
            ':pays' => $data['pays'],
            ':adultes' => $data['adultes'],
            ':enfants_present' => $data['enfants_present'],
            ':enfants' => $enfants_json,
            ':hebergement' => $data['hebergement'],
            ':precisions_allergies' => $data['precisions_allergies'],
            ':chanson' => $data['chanson'],
            ':suggestion_magique' => $data['suggestion_magique'],
            ':mot_maries' => $data['mot_maries']
        ]);

        $lastInsertId = $db->lastInsertId();
        log_message("Données enregistrées avec succès dans la base de données SQLite (ID: $lastInsertId)");
        
        if ($lastInsertId <= 0) {
            throw new Exception("Erreur d'insertion : aucun ID retourné");
        }
    } catch (PDOException $e) {
        log_message("Erreur SQLite: " . $e->getMessage());
        throw new Exception("Erreur lors de l'enregistrement en base de données: " . $e->getMessage());
    }
    
    // 2. ENREGISTREMENT DANS LE FICHIER JSON (SAUVEGARDE)
    try {
        $all_responses = [];
        
        if (file_exists($json_file) && filesize($json_file) > 0) {
            $json_content = file_get_contents($json_file);
            
            if ($json_content && $json_content[0] === '[') {
                $all_responses = json_decode($json_content, true) ?: [];
            }
        }
        
        $all_responses[] = $data;
        $json_data = json_encode($all_responses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($json_file, $json_data, LOCK_EX) === false) {
            throw new Exception("Impossible d'écrire dans le fichier JSON");
        }
        
        log_message("Données enregistrées avec succès dans le fichier JSON");
    } catch (Exception $e) {
        log_message("Erreur JSON: " . $e->getMessage());
        // On continue quand même pour les emails
    }
    
    // 3. ENVOI DES EMAILS (optionnel)
    try {
        // Vérifier que PHPMailer est installé
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            log_message("PHPMailer n'est pas installé - emails désactivés");
            throw new Exception("PHPMailer non disponible");
        }
        
        // Charger les variables d'environnement
        if (file_exists(__DIR__ . '/.env')) {
            if (class_exists('Dotenv\Dotenv')) {
                $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
                $dotenv->load();
            }
        }
        
        // Configuration du SMTP
        $smtp_host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $smtp_port = $_ENV['SMTP_PORT'] ?? 587;
        $smtp_user = $_ENV['MAIL_USER'] ?? '';
        $smtp_pass = $_ENV['MAIL_PASS'] ?? '';
        $couple_email = $_ENV['COUPLE_EMAIL'] ?? 'charlotteandjulien@gmail.com';
        
        if (empty($smtp_pass) || empty($smtp_user)) {
            log_message("Configuration email incomplète - emails désactivés");
            throw new Exception("Configuration email manquante");
        }
        
        // Préparation du mail
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        $mail->CharSet = 'UTF-8';
        
        // 3.1 EMAIL À L'INVITÉ
        try {
            $mail->setFrom($smtp_user, 'Charlotte & Julien');
            $mail->addAddress($data['email'], $data['prenom'] . ' ' . $data['nom']);
            $mail->isHTML(true);
            $mail->Subject = "Confirmation - Mariage Charlotte & Julien";
            
            // Construction de la liste des enfants pour l'email
            $enfants_html = '';
            if (!empty($enfants)) {
                $enfants_html = '<ul>';
                foreach ($enfants as $enfant) {
                    $enfants_html .= sprintf(
                        '<li>%s (%s ans)</li>',
                        htmlspecialchars($enfant['prenom']),
                        htmlspecialchars($enfant['age'])
                    );
                }
                $enfants_html .= '</ul>';
            }
            
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        h2 { color: var(--color-primary); }
                        .footer { margin-top: 30px; font-style: italic; color: #777; }
                        ul { padding-left: 20px; }
                        li { margin-bottom: 5px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Merci pour votre réponse !</h2>
                        <p>Cher(e) " . htmlspecialchars($data['prenom']) . ",</p>
                        <p>Nous avons bien reçu votre réponse pour notre mariage et nous vous en remercions.</p>
                        <p>Voici un récapitulatif de vos informations :</p>
                        <ul>
                            <li><strong>Nombre d'adultes :</strong> " . htmlspecialchars($data['adultes']) . "</li>";
                            
            if ($data['enfants_present'] === 'oui' && !empty($enfants)) {
                $mail->Body .= "<li><strong>Enfants :</strong> " . $enfants_html . "</li>";
            } else {
                $mail->Body .= "<li><strong>Enfants :</strong> Non</li>";
            }
                            
            $mail->Body .= "<li><strong>Hébergement :</strong> " . htmlspecialchars($data['hebergement']) . "</li>
                        </ul>
                        <p>À très bientôt !</p>
                        <div class='footer'>Charlotte & Julien</div>
                    </div>
                </body>
                </html>";
                
            $mail->send();
            log_message("Email envoyé avec succès à l'invité: " . $data['email']);
        } catch (Exception $e) {
            log_message("Erreur lors de l'envoi de l'email à l'invité: " . $e->getMessage());
        }
        
        // 3.2 EMAIL AUX MARIÉS
        try {
            $mail->clearAddresses();
            $mail->addAddress($couple_email);
            $mail->Subject = "[RSVP] Nouvelle réponse - " . $data['prenom'] . " " . $data['nom'];
            
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        h2 { color: #EFA8B4; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        table, th, td { border: 1px solid #ddd; }
                        th, td { padding: 10px; text-align: left; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Nouvelle réponse reçue</h2>
                        <table>
                            <tr>
                                <th>De</th>
                                <td>" . htmlspecialchars($data['prenom'] . ' ' . $data['nom']) . "</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>" . htmlspecialchars($data['email']) . "</td>
                            </tr>
                            <tr>
                                <th>Téléphone</th>
                                <td>" . htmlspecialchars($data['telephone']) . "</td>
                            </tr>
                            <tr>
                                <th>Adresse</th>
                                <td>" . htmlspecialchars($data['adresse'] . ', ' . $data['code_postal'] . ', ' . $data['ville'] . ', ' . $data['pays']) . "</td>
                            </tr>
                            <tr>
                                <th>Nombre d'adultes</th>
                                <td>" . htmlspecialchars($data['adultes']) . "</td>
                            </tr>";
                            
            if ($data['enfants_present'] === 'oui' && !empty($enfants)) {
                $mail->Body .= "
                            <tr>
                                <th>Enfants</th>
                                <td>" . $enfants_html . "</td>
                            </tr>";
            } else {
                $mail->Body .= "
                            <tr>
                                <th>Enfants</th>
                                <td>Non</td>
                            </tr>";
            }
                            
            $mail->Body .= "
                            <tr>
                                <th>Hébergement</th>
                                <td>" . htmlspecialchars($data['hebergement']) . "</td>
                            </tr>
                            <tr>
                                <th>Précisions allergies</th>
                                <td>" . htmlspecialchars($data['precisions_allergies']) . "</td>
                            </tr>
                            <tr>
                                <th>Chanson suggérée</th>
                                <td>" . htmlspecialchars($data['chanson']) . "</td>
                            </tr>
                            <tr>
                                <th>Détail magique</th>
                                <td>" . htmlspecialchars($data['suggestion_magique']) . "</td>
                            </tr>
                            <tr>
                                <th>Message</th>
                                <td>" . nl2br(htmlspecialchars($data['mot_maries'])) . "</td>
                            </tr>
                        </table>
                    </div>
                </body>
                </html>";
                
            $mail->send();
            log_message("Email envoyé avec succès aux mariés: " . $couple_email);
        } catch (Exception $e) {
            log_message("Erreur lors de l'envoi de l'email aux mariés: " . $e->getMessage());
        }
    } catch (Exception $e) {
        log_message("Erreur générale d'email: " . $e->getMessage());
        // On continue - les données sont déjà sauvegardées
    }
    
    // Enregistrer l'horodatage de la soumission
    $_SESSION['last_form_submission'] = time();
    
    // Réponse positive
    return_json(['success' => true, 'message' => 'Votre réponse a bien été enregistrée. Merci !']);
    
} catch (Exception $e) {
    log_message("Erreur critique: " . $e->getMessage());
    log_message("Trace: " . $e->getTraceAsString());
    return_json(['success' => false, 'message' => 'Une erreur est survenue lors du traitement de votre demande.'], 500);
}
?>