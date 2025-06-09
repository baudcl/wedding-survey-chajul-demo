<?php
session_start();

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Configuration des chemins
$db_file = __DIR__ . '/../server/db/responses.db';

// Vérifier que le fichier de base de données existe
if (!file_exists($db_file)) {
    // Créer le fichier si nécessaire
    $db_dir = dirname($db_file);
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }
}

try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table des invités si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS invites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prenom TEXT NOT NULL,
        nom TEXT NOT NULL,
        email TEXT UNIQUE,
        telephone TEXT,
        adresse TEXT,
        code_postal TEXT,
        ville TEXT,
        pays TEXT,
        groupe TEXT,
        nombre_adultes INTEGER DEFAULT 1,
        nombre_enfants INTEGER DEFAULT 0,
        table_numero INTEGER,
        notes TEXT,
        statut TEXT DEFAULT 'en_attente',
        date_ajout TEXT,
        date_modification TEXT
    )");
    
    // Créer la table des groupes si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS groupes_invites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT UNIQUE NOT NULL,
        couleur TEXT DEFAULT '#8DB1A8'
    )");
    
    // Ajouter des groupes par défaut s'ils n'existent pas
    $groupes_defaut = [
        ['Famille Charlotte', '#EFA8B4'],
        ['Famille Julien', '#8DB1A8'],
        ['Amis Charlotte', '#FFB6C1'],
        ['Amis Julien', '#87CEEB'],
        ['Amis communs', '#DDA0DD'],
        ['Collègues', '#F0E68C']
    ];
    
    foreach ($groupes_defaut as $groupe) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO groupes_invites (nom, couleur) VALUES (?, ?)");
        $stmt->execute([$groupe[0], $groupe[1]]);
    }
    
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Fonction pour synchroniser un invité avec sa réponse RSVP
function synchroniserAvecReponse($db, $email) {
    $stmt = $db->prepare("SELECT * FROM responses WHERE email = ? ORDER BY date DESC LIMIT 1");
    $stmt->execute([$email]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($response) {
        // Calculer le nombre d'enfants à partir du JSON
        $nombre_enfants = 0;
        if (!empty($response['enfants'])) {
            $enfants = json_decode($response['enfants'], true);
            if (is_array($enfants)) {
                $nombre_enfants = count($enfants);
            }
        }
        
        return [
            'prenom' => $response['prenom'],
            'nom' => $response['nom'],
            'telephone' => $response['telephone'],
            'adresse' => $response['adresse'],
            'code_postal' => $response['code_postal'],
            'ville' => $response['ville'],
            'pays' => $response['pays'],
            'nombre_adultes' => $response['adultes'],
            'nombre_enfants' => $nombre_enfants,
            'statut' => 'confirme',
            'a_repondu' => true
        ];
    }
    
    return null;
}

// Gestion de la requête AJAX pour vérifier un email
if (isset($_GET['check_email'])) {
    header('Content-Type: application/json');
    $email = $_GET['check_email'];
    
    $response = ['exists' => false];
    
    if ($email) {
        $donnees_rsvp = synchroniserAvecReponse($db, $email);
        if ($donnees_rsvp) {
            $response['exists'] = true;
            $response['info'] = $donnees_rsvp;
        }
    }
    
    echo json_encode($response);
    exit;
}

// Traitement des actions
$message = '';
$message_type = '';

// Ajouter un invité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ajouter_invite') {
        try {
            // Vérifier si l'email existe déjà dans les réponses
            $donnees_rsvp = null;
            if (!empty($_POST['email'])) {
                $donnees_rsvp = synchroniserAvecReponse($db, $_POST['email']);
            }
            
            // Si on a trouvé des données RSVP, les utiliser
            if ($donnees_rsvp) {
                $stmt = $db->prepare("INSERT INTO invites (
                    prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
                    groupe, nombre_adultes, nombre_enfants, table_numero, notes, statut, date_ajout
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $donnees_rsvp['prenom'],
                    $donnees_rsvp['nom'],
                    $_POST['email'],
                    $donnees_rsvp['telephone'],
                    $donnees_rsvp['adresse'],
                    $donnees_rsvp['code_postal'],
                    $donnees_rsvp['ville'],
                    $donnees_rsvp['pays'],
                    $_POST['groupe'] ?: null,
                    $donnees_rsvp['nombre_adultes'],
                    $donnees_rsvp['nombre_enfants'],
                    $_POST['table_numero'] ?: null,
                    $_POST['notes'] ?: null,
                    'confirme',
                    date('Y-m-d H:i:s')
                ]);
                
                $message = "Invité ajouté avec succès ! Les informations ont été récupérées depuis sa réponse RSVP.";
                $message_type = "success";
            } else {
                // Sinon, utiliser les données du formulaire
                $stmt = $db->prepare("INSERT INTO invites (
                    prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
                    groupe, nombre_adultes, nombre_enfants, table_numero, notes, date_ajout
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $_POST['prenom'],
                    $_POST['nom'],
                    $_POST['email'] ?: null,
                    $_POST['telephone'] ?: null,
                    $_POST['adresse'] ?: null,
                    $_POST['code_postal'] ?: null,
                    $_POST['ville'] ?: null,
                    $_POST['pays'] ?: 'France',
                    $_POST['groupe'] ?: null,
                    $_POST['nombre_adultes'] ?: 1,
                    $_POST['nombre_enfants'] ?: 0,
                    $_POST['table_numero'] ?: null,
                    $_POST['notes'] ?: null,
                    date('Y-m-d H:i:s')
                ]);
                
                $message = "Invité ajouté avec succès !";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'ajout : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Synchroniser tous les invités avec les réponses
    elseif ($_POST['action'] === 'synchroniser_tout') {
        try {
            $stmt = $db->query("SELECT DISTINCT email FROM responses WHERE email IS NOT NULL AND email != ''");
            $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $count_sync = 0;
            $count_new = 0;
            
            foreach ($emails as $email) {
                // Vérifier si l'invité existe déjà
                $stmt = $db->prepare("SELECT id FROM invites WHERE email = ?");
                $stmt->execute([$email]);
                $invite_existe = $stmt->fetch();
                
                if (!$invite_existe) {
                    // Récupérer les données RSVP
                    $donnees_rsvp = synchroniserAvecReponse($db, $email);
                    
                    if ($donnees_rsvp) {
                        $stmt = $db->prepare("INSERT INTO invites (
                            prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
                            nombre_adultes, nombre_enfants, statut, date_ajout
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $donnees_rsvp['prenom'],
                            $donnees_rsvp['nom'],
                            $email,
                            $donnees_rsvp['telephone'],
                            $donnees_rsvp['adresse'],
                            $donnees_rsvp['code_postal'],
                            $donnees_rsvp['ville'],
                            $donnees_rsvp['pays'],
                            $donnees_rsvp['nombre_adultes'],
                            $donnees_rsvp['nombre_enfants'],
                            'confirme',
                            date('Y-m-d H:i:s')
                        ]);
                        
                        $count_new++;
                    }
                } else {
                    // Mettre à jour le statut
                    $stmt = $db->prepare("UPDATE invites SET statut = 'confirme', date_modification = ? WHERE email = ?");
                    $stmt->execute([date('Y-m-d H:i:s'), $email]);
                    $count_sync++;
                }
            }
            
            $message = "Synchronisation terminée : $count_new nouveaux invités ajoutés, $count_sync invités mis à jour.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la synchronisation : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Modifier un invité
    elseif ($_POST['action'] === 'modifier_invite') {
        try {
            $stmt = $db->prepare("UPDATE invites SET 
                prenom = ?, nom = ?, email = ?, telephone = ?, adresse = ?, 
                code_postal = ?, ville = ?, pays = ?, groupe = ?, 
                nombre_adultes = ?, nombre_enfants = ?, table_numero = ?, 
                notes = ?, date_modification = ?
                WHERE id = ?");
            
            $stmt->execute([
                $_POST['prenom'],
                $_POST['nom'],
                $_POST['email'] ?: null,
                $_POST['telephone'] ?: null,
                $_POST['adresse'] ?: null,
                $_POST['code_postal'] ?: null,
                $_POST['ville'] ?: null,
                $_POST['pays'] ?: 'France',
                $_POST['groupe'] ?: null,
                $_POST['nombre_adultes'] ?: 1,
                $_POST['nombre_enfants'] ?: 0,
                $_POST['table_numero'] ?: null,
                $_POST['notes'] ?: null,
                date('Y-m-d H:i:s'),
                $_POST['invite_id']
            ]);
            
            $message = "Invité modifié avec succès !";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la modification : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Supprimer un invité
    elseif ($_POST['action'] === 'supprimer_invite') {
        try {
            $stmt = $db->prepare("DELETE FROM invites WHERE id = ?");
            $stmt->execute([$_POST['invite_id']]);
            
            $message = "Invité supprimé avec succès !";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Ajouter un groupe
    elseif ($_POST['action'] === 'ajouter_groupe') {
        try {
            $stmt = $db->prepare("INSERT INTO groupes_invites (nom, couleur) VALUES (?, ?)");
            $stmt->execute([$_POST['nom_groupe'], $_POST['couleur_groupe']]);
            
            $message = "Groupe ajouté avec succès !";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de l'ajout du groupe : " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Récupérer la liste des invités
$invites = $db->query("SELECT * FROM invites ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des groupes
$groupes = $db->query("SELECT * FROM groupes_invites ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$total_invites = 0;
$total_adultes = 0;
$total_enfants = 0;
$invites_par_groupe = [];
$invites_confirmes = 0;

foreach ($invites as $invite) {
    $total_adultes += $invite['nombre_adultes'];
    $total_enfants += $invite['nombre_enfants'];
    $total_invites += $invite['nombre_adultes'] + $invite['nombre_enfants'];
    
    if ($invite['groupe']) {
        if (!isset($invites_par_groupe[$invite['groupe']])) {
            $invites_par_groupe[$invite['groupe']] = 0;
        }
        $invites_par_groupe[$invite['groupe']] += $invite['nombre_adultes'] + $invite['nombre_enfants'];
    }
    
    // Vérifier si l'invité a confirmé (en croisant avec la table responses)
    $stmt = $db->prepare("SELECT COUNT(*) FROM responses WHERE email = ?");
    $stmt->execute([$invite['email']]);
    if ($stmt->fetchColumn() > 0) {
        $invites_confirmes++;
    }
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="liste_invites_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    fputcsv($output, [
        'Prénom', 'Nom', 'Email', 'Téléphone', 'Adresse', 'Code Postal', 
        'Ville', 'Pays', 'Groupe', 'Adultes', 'Enfants', 'Table', 'Notes'
    ], ',', '"', "\\", "\n");
    
    foreach ($invites as $invite) {
        fputcsv($output, [
            $invite['prenom'],
            $invite['nom'],
            $invite['email'],
            $invite['telephone'],
            $invite['adresse'],
            $invite['code_postal'],
            $invite['ville'],
            $invite['pays'],
            $invite['groupe'],
            $invite['nombre_adultes'],
            $invite['nombre_enfants'],
            $invite['table_numero'],
            $invite['notes']
        ], ',', '"', "\\", "\n");
    }
    
    fclose($output);
    exit;
}

// Importation CSV
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['csv_file']['tmp_name'];
    
    if (($handle = fopen($uploadedFile, "r")) !== FALSE) {
        // Ignorer la ligne d'en-tête
        $header = fgetcsv($handle, 1000, ",");
        
        $imported = 0;
        $errors = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            try {
                $stmt = $db->prepare("INSERT INTO invites (
                    prenom, nom, email, telephone, adresse, code_postal, ville, pays, 
                    groupe, nombre_adultes, nombre_enfants, table_numero, notes, date_ajout
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $data[0], // prénom
                    $data[1], // nom
                    $data[2] ?: null, // email
                    $data[3] ?: null, // téléphone
                    $data[4] ?: null, // adresse
                    $data[5] ?: null, // code postal
                    $data[6] ?: null, // ville
                    $data[7] ?: 'France', // pays
                    $data[8] ?: null, // groupe
                    $data[9] ?: 1, // adultes
                    $data[10] ?: 0, // enfants
                    $data[11] ?: null, // table
                    $data[12] ?: null, // notes
                    date('Y-m-d H:i:s')
                ]);
                
                $imported++;
            } catch (PDOException $e) {
                $errors++;
            }
        }
        fclose($handle);
        
        $message = "$imported invités importés avec succès" . ($errors > 0 ? " ($errors erreurs)" : "");
        $message_type = $errors > 0 ? "warning" : "success";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des invités - Mariage Charlotte & Julien</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #8DB1A8;
            --secondary: #EFA8B4;
            --background: #FFF8F1;
            --text: #333333;
            --border: #dddddd;
            --success: #4CAF50;
            --error: #f44336;
            --warning: #ff9800;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--background);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-family: 'RTL-Adam Script', serif;
            font-size: 3rem;
            margin: 0;
        }
        
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        
        .nav-btn {
            background-color: white;
            color: var(--primary);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .nav-btn:hover {
            background-color: var(--secondary);
            color: white;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }
        
        .card h3 {
            color: var(--primary);
            margin-top: 0;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--secondary);
            margin: 10px 0;
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        
        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        .message.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .message.warning {
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc80;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 5px;
            box-sizing: border-box;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        th, td {
            border: 1px solid var(--border);
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: var(--primary);
            color: white;
            position: sticky;
            top: 0;
        }
        
        tr:nth-child(even) {
            background-color: rgba(141, 177, 168, 0.1);
        }
        
        tr:hover {
            background-color: rgba(141, 177, 168, 0.2);
        }
        
        .invite-actions {
            display: flex;
            gap: 5px;
        }
        
        .invite-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .groupe-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            color: white;
        }
        
        .search-box {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 5px;
            width: 300px;
        }
        
        .import-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Gestion des invités</h1>
        <div class="nav-buttons">
            <a href="admin.php" class="nav-btn">Tableau de bord</a>
            <a href="?logout=1" class="nav-btn">Déconnexion</a>
        </div>
    </header>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="card">
                <h3>Total invités</h3>
                <div class="stats-number"><?= $total_invites ?></div>
                <p>personnes</p>
            </div>
            
            <div class="card">
                <h3>Adultes</h3>
                <div class="stats-number"><?= $total_adultes ?></div>
                <p>personnes</p>
            </div>
            
            <div class="card">
                <h3>Enfants</h3>
                <div class="stats-number"><?= $total_enfants ?></div>
                <p>personnes</p>
            </div>
            
            <div class="card">
                <h3>Confirmations</h3>
                <div class="stats-number"><?= $invites_confirmes ?></div>
                <p>sur <?= count($invites) ?> familles</p>
            </div>
            
            <?php
            $max_tables = 0;
            foreach ($invites as $invite) {
                if ($invite['table_numero'] > $max_tables) {
                    $max_tables = $invite['table_numero'];
                }
            }
            ?>
            <div class="card">
                <h3>Tables</h3>
                <div class="stats-number"><?= $max_tables ?></div>
                <p>tables prévues</p>
            </div>
        </div>
        
        <div class="actions">
            <div>
                <button class="btn btn-primary" onclick="openModal('inviteModal')">+ Ajouter un invité</button>
                <button class="btn btn-secondary" onclick="openModal('groupeModal')">+ Créer un groupe</button>
                <button class="btn btn-primary" onclick="openModal('importModal')">Importer CSV</button>
                <a href="?export=csv" class="btn btn-secondary">Exporter CSV</a>
                <button class="btn btn-primary" onclick="synchroniserReponses()">Synchroniser avec RSVP</button>
            </div>
            <input type="text" class="search-box" id="searchBox" placeholder="Rechercher un invité..." onkeyup="filterTable()">
        </div>
        
        <div class="table-container">
            <table id="invitesTable">
                <thead>
                    <tr>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Groupe</th>
                        <th>Adultes</th>
                        <th>Enfants</th>
                        <th>Table</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $invite): ?>
                        <?php
                        // Vérifier si l'invité a confirmé
                        $stmt = $db->prepare("SELECT COUNT(*) FROM responses WHERE email = ?");
                        $stmt->execute([$invite['email']]);
                        $a_confirme = $stmt->fetchColumn() > 0;
                        
                        // Trouver la couleur du groupe
                        $couleur_groupe = '#8DB1A8';
                        foreach ($groupes as $groupe) {
                            if ($groupe['nom'] === $invite['groupe']) {
                                $couleur_groupe = $groupe['couleur'];
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']) ?></td>
                            <td><?= htmlspecialchars($invite['email'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($invite['telephone'] ?: '-') ?></td>
                            <td>
                                <?php if ($invite['groupe']): ?>
                                    <span class="groupe-badge" style="background-color: <?= $couleur_groupe ?>">
                                        <?= htmlspecialchars($invite['groupe']) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= $invite['nombre_adultes'] ?></td>
                            <td><?= $invite['nombre_enfants'] ?></td>
                            <td><?= $invite['table_numero'] ?: '-' ?></td>
                            <td>
                                <?php if ($a_confirme): ?>
                                    <span style="color: green; font-weight: bold;">
                                        <i class="fas fa-check-circle"></i> Confirmé
                                    </span>
                                <?php else: ?>
                                    <span style="color: orange;">
                                        <i class="fas fa-clock"></i> En attente
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="invite-actions">
                                    <button class="btn-edit" onclick="editInvite(<?= htmlspecialchars(json_encode($invite)) ?>)">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <button class="btn-delete" onclick="deleteInvite(<?= $invite['id'] ?>, '<?= htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']) ?>')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Ajout/Modification Invité -->
    <div id="inviteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('inviteModal')">&times;</span>
            <h2 id="modalTitle">Ajouter un invité</h2>
            <form method="POST" id="inviteForm">
                <input type="hidden" name="action" id="formAction" value="ajouter_invite">
                <input type="hidden" name="invite_id" id="inviteId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" id="prenom" required>
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" id="nom" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email" onblur="verifierEmail(this.value)">
                        <small id="email-info" style="color: #8DB1A8; display: none;">
                            <i class="fas fa-check-circle"></i> Cet invité a déjà répondu ! Ses informations seront récupérées automatiquement.
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" id="telephone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse" id="adresse">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Code postal</label>
                        <input type="text" name="code_postal" id="code_postal">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="ville" id="ville">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Pays</label>
                    <input type="text" name="pays" id="pays" value="France">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Groupe</label>
                        <select name="groupe" id="groupe">
                            <option value="">-- Aucun groupe --</option>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= htmlspecialchars($groupe['nom']) ?>">
                                    <?= htmlspecialchars($groupe['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Numéro de table</label>
                        <input type="number" name="table_numero" id="table_numero" min="1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre d'adultes</label>
                        <input type="number" name="nombre_adultes" id="nombre_adultes" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label>Nombre d'enfants</label>
                        <input type="number" name="nombre_enfants" id="nombre_enfants" value="0" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Groupe -->
    <div id="groupeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('groupeModal')">&times;</span>
            <h2>Créer un groupe</h2>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter_groupe">
                
                <div class="form-group">
                    <label>Nom du groupe *</label>
                    <input type="text" name="nom_groupe" required>
                </div>
                
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" name="couleur_groupe" value="#8DB1A8">
                </div>
                
                <button type="submit" class="btn btn-primary">Créer le groupe</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Import CSV -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('importModal')">&times;</span>
            <h2>Importer une liste d'invités</h2>
            
            <div class="import-section">
                <h3>Format du fichier CSV</h3>
                <p>Le fichier doit contenir les colonnes suivantes dans cet ordre :</p>
                <ul>
                    <li>Prénom</li>
                    <li>Nom</li>
                    <li>Email</li>
                    <li>Téléphone</li>
                    <li>Adresse</li>
                    <li>Code postal</li>
                    <li>Ville</li>
                    <li>Pays</li>
                    <li>Groupe</li>
                    <li>Nombre d'adultes</li>
                    <li>Nombre d'enfants</li>
                    <li>Numéro de table</li>
                    <li>Notes</li>
                </ul>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer <strong id="deleteInviteName"></strong> ?</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="supprimer_invite">
                <input type="hidden" name="invite_id" id="deleteInviteId">
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-delete">Supprimer</button>
                    <button type="button" class="btn btn-primary" onclick="closeModal('deleteModal')">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Fonctions pour les modales
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            // Réinitialiser le formulaire si c'est le modal invité
            if (modalId === 'inviteModal') {
                document.getElementById('inviteForm').reset();
                document.getElementById('modalTitle').textContent = 'Ajouter un invité';
                document.getElementById('formAction').value = 'ajouter_invite';
                document.getElementById('inviteId').value = '';
                document.getElementById('email-info').style.display = 'none';
            }
        }
        
        // Fermer les modales en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Vérifier si un email existe déjà dans les réponses
        function verifierEmail(email) {
            if (!email) return;
            
            // Faire une requête AJAX pour vérifier si l'email existe dans les réponses
            fetch('admin-manage-guests.php?check_email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        document.getElementById('email-info').style.display = 'block';
                        // Optionnel : pré-remplir certains champs si on veut
                        if (data.info) {
                            document.getElementById('prenom').value = data.info.prenom;
                            document.getElementById('nom').value = data.info.nom;
                            document.getElementById('telephone').value = data.info.telephone || '';
                            document.getElementById('adresse').value = data.info.adresse || '';
                            document.getElementById('code_postal').value = data.info.code_postal || '';
                            document.getElementById('ville').value = data.info.ville || '';
                            document.getElementById('pays').value = data.info.pays || 'France';
                            document.getElementById('nombre_adultes').value = data.info.nombre_adultes || 1;
                            document.getElementById('nombre_enfants').value = data.info.nombre_enfants || 0;
                        }
                    } else {
                        document.getElementById('email-info').style.display = 'none';
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
        
        // Synchroniser toutes les réponses
        function synchroniserReponses() {
            if (confirm('Voulez-vous synchroniser tous les invités qui ont répondu via le formulaire RSVP ?')) {
                const formData = new FormData();
                formData.append('action', 'synchroniser_tout');
                
                fetch('admin-manage-guests.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                });
            }
        }
        
        // Fonction pour éditer un invité
        function editInvite(invite) {
            document.getElementById('modalTitle').textContent = 'Modifier l\'invité';
            document.getElementById('formAction').value = 'modifier_invite';
            document.getElementById('inviteId').value = invite.id;
            
            // Remplir les champs
            document.getElementById('prenom').value = invite.prenom;
            document.getElementById('nom').value = invite.nom;
            document.getElementById('email').value = invite.email || '';
            document.getElementById('telephone').value = invite.telephone || '';
            document.getElementById('adresse').value = invite.adresse || '';
            document.getElementById('code_postal').value = invite.code_postal || '';
            document.getElementById('ville').value = invite.ville || '';
            document.getElementById('pays').value = invite.pays || 'France';
            document.getElementById('groupe').value = invite.groupe || '';
            document.getElementById('table_numero').value = invite.table_numero || '';
            document.getElementById('nombre_adultes').value = invite.nombre_adultes || 1;
            document.getElementById('nombre_enfants').value = invite.nombre_enfants || 0;
            document.getElementById('notes').value = invite.notes || '';
            
            openModal('inviteModal');
        }
        
        // Fonction pour supprimer un invité
        function deleteInvite(id, name) {
            document.getElementById('deleteInviteId').value = id;
            document.getElementById('deleteInviteName').textContent = name;
            openModal('deleteModal');
        }
        
        // Fonction pour filtrer le tableau
        function filterTable() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('invitesTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                let found = false;
                const cells = rows[i].getElementsByTagName('td');
                
                for (let j = 0; j < cells.length - 1; j++) { // -1 pour ignorer la colonne actions
                    const cellText = cells[j].textContent || cells[j].innerText;
                    
                    if (cellText.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Échap pour fermer les modales
            if (e.key === 'Escape') {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    if (modals[i].style.display === 'block') {
                        modals[i].style.display = 'none';
                    }
                }
            }
            
            // Ctrl+F pour rechercher
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchBox').focus();
            }
        });
        
        // Statistiques par groupe
        <?php if (!empty($invites_par_groupe)): ?>
        console.log('Répartition par groupe:');
        <?php foreach ($invites_par_groupe as $groupe => $nombre): ?>
        console.log('<?= $groupe ?> : <?= $nombre ?> personnes');
        <?php endforeach; ?>
        <?php endif; ?>
    </script>
</body>
</html>