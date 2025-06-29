<?php
session_start();

// Simple authentication
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// Configuration des chemins
$db_file = __DIR__ . '/../server/db/responses.db';

try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table des tables si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS tables_mariage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        numero INTEGER UNIQUE NOT NULL,
        nom TEXT,
        capacite INTEGER DEFAULT 10,
        position_x INTEGER DEFAULT 50,
        position_y INTEGER DEFAULT 50,
        forme TEXT DEFAULT 'ronde',
        notes TEXT
    )");
    
    // Créer la table d'affectation des invités aux tables si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS affectations_tables (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invite_id INTEGER,
        table_id INTEGER,
        position INTEGER,
        type TEXT DEFAULT 'adulte',
        nombre INTEGER DEFAULT 1,
        FOREIGN KEY (invite_id) REFERENCES invites(id),
        FOREIGN KEY (table_id) REFERENCES tables_mariage(id)
    )");
    
    // Vérifier si les colonnes type et nombre existent, sinon les ajouter
    $stmt = $db->query("PRAGMA table_info(affectations_tables)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_type = false;
    $has_nombre = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'type') $has_type = true;
        if ($column['name'] === 'nombre') $has_nombre = true;
    }
    
    // Si les colonnes n'existent pas, recréer la table avec la nouvelle structure
    if (!$has_type || !$has_nombre) {
        // Sauvegarder les données existantes
        $existing_data = $db->query("SELECT * FROM affectations_tables")->fetchAll(PDO::FETCH_ASSOC);
        
        // Supprimer l'ancienne table
        $db->exec("DROP TABLE IF EXISTS affectations_tables");
        
        // Recréer avec la nouvelle structure
        $db->exec("CREATE TABLE affectations_tables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invite_id INTEGER,
            table_id INTEGER,
            position INTEGER,
            type TEXT DEFAULT 'adulte',
            nombre INTEGER DEFAULT 1,
            FOREIGN KEY (invite_id) REFERENCES invites(id),
            FOREIGN KEY (table_id) REFERENCES tables_mariage(id)
        )");
        
        // Réinsérer les données existantes avec les valeurs par défaut
        foreach ($existing_data as $row) {
            // Pour chaque ancienne affectation, récupérer le nombre d'adultes et d'enfants
            $stmt = $db->prepare("SELECT nombre_adultes, nombre_enfants FROM invites WHERE id = ?");
            $stmt->execute([$row['invite_id']]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($invite) {
                // Insérer les adultes
                if ($invite['nombre_adultes'] > 0) {
                    $stmt = $db->prepare("INSERT INTO affectations_tables (invite_id, table_id, type, nombre) VALUES (?, ?, 'adulte', ?)");
                    $stmt->execute([$row['invite_id'], $row['table_id'], $invite['nombre_adultes']]);
                }
                
                // Insérer les enfants
                if ($invite['nombre_enfants'] > 0) {
                    $stmt = $db->prepare("INSERT INTO affectations_tables (invite_id, table_id, type, nombre) VALUES (?, ?, 'enfant', ?)");
                    $stmt->execute([$row['invite_id'], $row['table_id'], $invite['nombre_enfants']]);
                }
            }
        }
    }
    
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'ajouter_table':
            try {
                // Trouver une position libre pour la nouvelle table
                $tables_existantes = $db->query("SELECT position_x, position_y FROM tables_mariage")->fetchAll(PDO::FETCH_ASSOC);
                
                // Définir la grille de positionnement
                $grid_size = 150; // Taille d'une cellule de la grille
                $grid_padding = 50; // Marge initiale
                
                // Trouver une position libre
                $position_x = $grid_padding;
                $position_y = $grid_padding;
                $position_found = false;
                
                while (!$position_found) {
                    $position_occupied = false;
                    foreach ($tables_existantes as $table) {
                        $distance_x = abs($table['position_x'] - $position_x);
                        $distance_y = abs($table['position_y'] - $position_y);
                        if ($distance_x < $grid_size && $distance_y < $grid_size) {
                            $position_occupied = true;
                            break;
                        }
                    }
                    
                    if (!$position_occupied) {
                        $position_found = true;
                    } else {
                        // Déplacer à la position suivante sur la grille
                        $position_x += $grid_size;
                        if ($position_x > 800) { // Largeur max du conteneur
                            $position_x = $grid_padding;
                            $position_y += $grid_size;
                        }
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO tables_mariage (numero, nom, capacite, forme, position_x, position_y) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['numero'],
                    $_POST['nom'] ?: 'Table ' . $_POST['numero'],
                    $_POST['capacite'] ?: 10,
                    $_POST['forme'] ?: 'ronde',
                    $position_x,
                    $position_y
                ]);
                $response['success'] = true;
                $response['message'] = 'Table ajoutée avec succès';
                $response['table_id'] = $db->lastInsertId();
            } catch (PDOException $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'modifier_table':
            try {
                $stmt = $db->prepare("UPDATE tables_mariage SET nom = ?, capacite = ?, forme = ?, notes = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['capacite'],
                    $_POST['forme'],
                    $_POST['notes'],
                    $_POST['table_id']
                ]);
                $response['success'] = true;
                $response['message'] = 'Table modifiée avec succès';
            } catch (PDOException $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'supprimer_table':
            try {
                // D'abord, retirer tous les invités de cette table
                $stmt = $db->prepare("DELETE FROM affectations_tables WHERE table_id = ?");
                $stmt->execute([$_POST['table_id']]);
                
                // Mettre à jour la table_numero dans la table invites
                $stmt = $db->prepare("UPDATE invites SET table_numero = NULL WHERE table_numero = (SELECT numero FROM tables_mariage WHERE id = ?)");
                $stmt->execute([$_POST['table_id']]);
                
                // Supprimer la table
                $stmt = $db->prepare("DELETE FROM tables_mariage WHERE id = ?");
                $stmt->execute([$_POST['table_id']]);
                
                $response['success'] = true;
                $response['message'] = 'Table supprimée et invités remis dans la liste';
            } catch (PDOException $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'affecter_invite':
            try {

                $invite_id = $_POST['invite_id'];
                $table_id = $_POST['table_id'];
                $type = $_POST['type'] ?? 'tous'; // 'tous', 'adultes', 'enfants'
                
                // Récupérer les infos de l'invité
                $stmt = $db->prepare("SELECT * FROM invites WHERE id = ?");
                $stmt->execute([$invite_id]);
                $invite = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$invite) {
                    throw new Exception("Invité introuvable");
                }
                
                // Si on place "tous", supprimer toutes les affectations existantes
                if ($type === 'tous') {
                    // Vérifier d'abord si l'invité n'est pas déjà affecté
                    $stmt = $db->prepare("DELETE FROM affectations_tables WHERE invite_id = ?");
                    $stmt->execute([$invite_id]);
                    
                    if ($table_id != '0') {
                        // Affecter les adultes
                        if ($invite['nombre_adultes'] > 0) {
                            $stmt = $db->prepare("INSERT INTO affectations_tables (invite_id, table_id, type, nombre) VALUES (?, ?, 'adulte', ?)");
                            $stmt->execute([$invite_id, $table_id, $invite['nombre_adultes']]);
                        }
                        
                        // Affecter les enfants
                        if ($invite['nombre_enfants'] > 0) {
                            $stmt = $db->prepare("INSERT INTO affectations_tables (invite_id, table_id, type, nombre) VALUES (?, ?, 'enfant', ?)");
                            $stmt->execute([$invite_id, $table_id, $invite['nombre_enfants']]);
                        }
                        
                        // Mettre à jour la table dans la fiche invité
                        $stmt = $db->prepare("UPDATE invites SET table_numero = (SELECT numero FROM tables_mariage WHERE id = ?) WHERE id = ?");
                        $stmt->execute([$table_id, $invite_id]);
                    } else {
                        // Retirer de toutes les tables
                        $stmt = $db->prepare("UPDATE invites SET table_numero = NULL WHERE id = ?");
                        $stmt->execute([$invite_id]);
                    }
                } else {
                    // Placement partiel (adultes ou enfants seulement)
                    $nombre = $type === 'adultes' ? $invite['nombre_adultes'] : $invite['nombre_enfants'];
                    $type_db = $type === 'adultes' ? 'adulte' : 'enfant';
                    
                    // Supprimer l'affectation existante pour ce type
                    $stmt = $db->prepare("DELETE FROM affectations_tables WHERE invite_id = ? AND type = ?");
                    $stmt->execute([$invite_id, $type_db]);
                    
                    if ($table_id != '0' && $nombre > 0) {
                        $stmt = $db->prepare("INSERT INTO affectations_tables (invite_id, table_id, type, nombre) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$invite_id, $table_id, $type_db, $nombre]);
                    }
                }
                
                $response['success'] = true;
                $response['message'] = 'Affectation modifiée avec succès';
            } catch (Exception $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'retirer_invite':
            try {
                $invite_id = $_POST['invite_id'];
                $table_id = $_POST['table_id'];

                // Supprimer uniquement les affectations pour cette table
                $stmt = $db->prepare("DELETE FROM affectations_tables WHERE invite_id = ? AND table_id = ?");
                $stmt->execute([$invite_id, $table_id]);
                
                // Vérifier s'il reste des affectations
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM affectations_tables WHERE invite_id = ?");
                $stmt->execute([$invite_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Mettre à jour table_numero seulement si plus aucune affectation
                if ($result['count'] == 0) {
                    $stmt = $db->prepare("UPDATE invites SET table_numero = NULL WHERE id = ?");
                    $stmt->execute([$invite_id]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Invité retiré de la table';
            } catch (PDOException $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'sauvegarder_positions':
            try {
                $positions = json_decode($_POST['positions'], true);
                foreach ($positions as $table_id => $pos) {
                    $stmt = $db->prepare("UPDATE tables_mariage SET position_x = ?, position_y = ? WHERE id = ?");
                    $stmt->execute([$pos['x'], $pos['y'], $table_id]);
                }
                $response['success'] = true;
                $response['message'] = 'Positions sauvegardées';
            } catch (PDOException $e) {
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            
        case 'get_table_details':
            try {
                $table_id = $_POST['table_id'];
                
                // Récupérer les infos de la table
                $stmt = $db->prepare("SELECT * FROM tables_mariage WHERE id = ?");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer les invités avec leurs affectations détaillées
                $stmt = $db->prepare("
                    SELECT 
                        i.*,
                        at_adulte.nombre as adultes_table,
                        at_enfant.nombre as enfants_table
                    FROM invites i
                    LEFT JOIN affectations_tables at_adulte ON i.id = at_adulte.invite_id AND at_adulte.table_id = ? AND at_adulte.type = 'adulte'
                    LEFT JOIN affectations_tables at_enfant ON i.id = at_enfant.invite_id AND at_enfant.table_id = ? AND at_enfant.type = 'enfant'
                    WHERE at_adulte.id IS NOT NULL OR at_enfant.id IS NOT NULL
                    ORDER BY i.nom, i.prenom
                ");
                $stmt->execute([$table_id, $table_id]);
                $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['table'] = $table;
                $response['invites'] = $invites;
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = 'Erreur : ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
    }
}

// Récupérer les tables
$tables = $db->query("SELECT * FROM tables_mariage ORDER BY numero")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les invités
$invites = $db->query("SELECT * FROM invites ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les affectations avec détails
$affectations = [];
$affectations_details = [];
$result = $db->query("SELECT * FROM affectations_tables");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $affectations[$row['invite_id']] = $row['table_id'];
    if (!isset($affectations_details[$row['invite_id']])) {
        $affectations_details[$row['invite_id']] = [];
    }
    
    // Vérifier que les clés existent
    $type = isset($row['type']) ? $row['type'] : 'adulte';
    $nombre = isset($row['nombre']) ? $row['nombre'] : 1;
    
    $affectations_details[$row['invite_id']][$type] = [
        'table_id' => $row['table_id'],
        'nombre' => $nombre
    ];
}

// Calculer les statistiques
$total_places = 0;
$places_occupees = 0;
$places_occupees_adultes = 0;
$places_occupees_enfants = 0;
$invites_sans_table = 0;

foreach ($tables as $table) {
    $total_places += $table['capacite'];
    
    // Compter les invités à cette table
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN type = 'adulte' THEN nombre ELSE 0 END) as adultes,
            SUM(CASE WHEN type = 'enfant' THEN nombre ELSE 0 END) as enfants
        FROM affectations_tables
        WHERE table_id = ?
    ");
    $stmt->execute([$table['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $places_occupees_adultes += $result['adultes'] ?: 0;
    $places_occupees_enfants += $result['enfants'] ?: 0;
}

$places_occupees = $places_occupees_adultes + $places_occupees_enfants;

// Compter les invités sans table
$stmt = $db->query("
    SELECT SUM(nombre_adultes + nombre_enfants) as total
    FROM invites
    WHERE id NOT IN (SELECT invite_id FROM affectations_tables)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$invites_sans_table = $result['total'] ?: 0;

// Export du plan de table
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="plan_de_table_' . date('Y-m-d') . '.html"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Plan de table - Mariage Charlotte & Julien</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .table-plan { page-break-after: always; margin-bottom: 30px; }
            h1 { text-align: center; color: var(--color-primary); }
            h2 { color: var(--color-secondary); }
            .guest-list { margin-left: 20px; }
            .guest { padding: 5px 0; }
            .child { font-style: italic; color: #666; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <h1>Plan de table - Mariage Charlotte & Julien</h1>
        <p style="text-align: center;">Le 27 Juin 2026</p>';
    
    foreach ($tables as $table) {
        echo '<div class="table-plan">';
        echo '<h2>Table ' . $table['numero'] . ' - ' . htmlspecialchars($table['nom']) . '</h2>';
        echo '<p>Capacité : ' . $table['capacite'] . ' places</p>';
        
        $stmt = $db->prepare("
            SELECT i.*
            FROM invites i
            JOIN affectations_tables at ON i.id = at.invite_id
            WHERE at.table_id = ?
            ORDER BY i.nom, i.prenom
        ");
        $stmt->execute([$table['id']]);
        $invites_table = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="guest-list">';
        $total_adultes = 0;
        $total_enfants = 0;
        
        foreach ($invites_table as $invite) {
            echo '<div class="guest">';
            echo htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']);
            if ($invite['nombre_adultes'] > 1) {
                echo ' (' . $invite['nombre_adultes'] . ' adultes)';
            }
            if ($invite['nombre_enfants'] > 0) {
                echo ' <span class="child">+ ' . $invite['nombre_enfants'] . ' enfant(s)</span>';
            }
            echo '</div>';
            $total_adultes += $invite['nombre_adultes'];
            $total_enfants += $invite['nombre_enfants'];
        }
        echo '</div>';
        echo '<p><strong>Total : ' . $total_adultes . ' adultes';
        if ($total_enfants > 0) {
            echo ' et ' . $total_enfants . ' enfants';
        }
        echo '</strong></p>';
        echo '</div>';
    }
    
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de table - Mariage Charlotte & Julien</title>    
    <link rel="stylesheet" href="../ressources/css/theme-variables.css">
    <link rel="stylesheet" href="../ressources/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary: #8DB1A8;
            --secondary: #EFA8B4;
            --background: #FFF8F1;
            --text: #333333;
            --border: #dddddd;
        }
        /* Prevent overrun */
* {
    box-sizing: border-box;
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
            max-width: 1600px;
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
        
        .stats-detail {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-height: 700px;
            overflow-y: auto;
        }
        
        .plan-area {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            position: relative;
            min-height: 700px;
            overflow: hidden;
        }
        
        .plan-container {
            position: relative;
            width: 100%;
            height: 650px;
            background-color: #f9f9f9;
            border: 2px dashed var(--border);
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
            margin: 2px;
        }
        
        .table-box {
            position: absolute;
            background-color: white;
            border: 3px solid var(--primary);
            border-radius: 50%;
            width: 140px;
            height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: move;
            user-select: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .table-box:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .table-box.rectangulaire {
            border-radius: 10px;
            width: 180px;
            height: 120px;
        }
        
        .table-box.selected {
            border-color: var(--secondary);
            box-shadow: 0 6px 12px rgba(239, 168, 180, 0.4);
        }
        
        .table-box h4 {
            margin: 0;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .table-box .table-name {
            font-size: 0.85rem;
            color: #666;
            margin: 2px 0;
        }
        
        .table-box .capacity {
            font-size: 0.8rem;
            color: #666;
        }
        
        .table-box .occupancy {
            font-size: 1rem;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .table-box.full {
            background-color: #ffebee;
            border-color: #f44336;
        }
        
        .table-box.partial {
            background-color: #fff8e1;
            border-color: #ff9800;
        }
        
        .table-box.empty {
            background-color: #e8f5e9;
            border-color: #4CAF50;
        }
        
        .invite-list {
            width: 100%;
            max-height: 700px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .invite-item {
            padding: 10px;
            margin: 5px 0;
            background-color: #f5f5f5;
            border-radius: 5px;
            cursor: move;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }
        
        .invite-item > div {
            flex: 1;
            min-width: 0; /* Pour éviter le débordement */
        }


        .invite-item > div:last-child {
            flex: 0 0 auto;
            padding-left: 10px;
            white-space: nowrap;
        }

        .invite-item:hover {
            background-color: #e0e0e0;
            transform: translateX(5px);
        }
        
        .invite-item.partial {
            background-color: #fff3e0;
            border-left: 3px solid #ff9800;
        }
        
        .invite-item.sub-item {
            background-color: #e3f2fd;
            border-left: 3px solid #2196F3;
            font-size: 0.9rem;
        }
        
        .invite-item.sub-item:hover {
            background-color: #bbdefb;
        }
        
        .invite-item .badge {
            background-color: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .invite-item .people-count {
            background-color: var(--secondary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 5px;
            white-space: nowrap;
            display: inline-block;
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
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: #aaa;
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
        
        .guest-list-table {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .guest-table-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .guest-table-item:last-child {
            border-bottom: none;
        }
        
        .guest-info {
            flex: 1;
        }
        
        .guest-info .name {
            font-weight: bold;
        }
        
        .guest-info .details {
            font-size: 0.9rem;
            color: #666;
        }
        
        .dragging {
            opacity: 0.5;
        }
        
        .drag-over {
            background-color: rgba(141, 177, 168, 0.2);
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .plan-container {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Plan de table</h1>
        <div class="nav-buttons">
            <a href="admin.php" class="nav-btn">Tableau de bord</a>
            <a href="admin-manage-guests.php" class="nav-btn">Gestion invités</a>
            <a href="?logout=1" class="nav-btn">Déconnexion</a>
        </div>
    </header>
    
    <div class="container">
        <div class="dashboard">
            <div class="card">
                <h3>Tables</h3>
                <div class="stats-number"><?= count($tables) ?></div>
                <p>tables créées</p>
            </div>
            
            <div class="card">
                <h3>Places totales</h3>
                <div class="stats-number"><?= $total_places ?></div>
                <p>places disponibles</p>
            </div>
            
            <div class="card">
                <h3>Places occupées</h3>
                <div class="stats-number"><?= $places_occupees ?></div>
                <p>personnes placées</p>
                <div class="stats-detail">
                    <?= $places_occupees_adultes ?> adultes, <?= $places_occupees_enfants ?> enfants
                </div>
            </div>
            
            <div class="card">
                <h3>Sans table</h3>
                <div class="stats-number"><?= $invites_sans_table ?></div>
                <p>personnes à placer</p>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <button class="btn btn-primary" onclick="openModal('tableModal')">
                <i class="fas fa-plus"></i> Ajouter une table
            </button>
            <button class="btn btn-secondary" onclick="sauvegarderPositions()">
                <i class="fas fa-save"></i> Sauvegarder les positions
            </button>
            <a href="?export=pdf" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Exporter le plan
            </a>
        </div>
        
        <div class="main-content">
            <div class="sidebar">
                <h3>Invités à placer</h3>
                <input type="text" id="searchInvite" placeholder="Rechercher un invité..." 
                       style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid var(--border); border-radius: 5px;">
                
                <div class="invite-list" id="inviteList">
                    <?php foreach ($invites as $invite): ?>
                        <?php 
                        $nb_adultes = $invite['nombre_adultes'];
                        $nb_enfants = $invite['nombre_enfants'];
                        $nb_total = $nb_adultes + $nb_enfants;
                        
                        // Vérifier les affectations partielles
                        $adultes_places = 0;
                        $enfants_places = 0;
                        $tables_adultes = [];
                        $tables_enfants = [];
                        
                        if (isset($affectations_details[$invite['id']])) {
                            if (isset($affectations_details[$invite['id']]['adulte'])) {
                                $adultes_places = $affectations_details[$invite['id']]['adulte']['nombre'];
                                $table_id = $affectations_details[$invite['id']]['adulte']['table_id'];
                                foreach ($tables as $t) {
                                    if ($t['id'] == $table_id) {
                                        $tables_adultes[] = 'T' . $t['numero'];
                                        break;
                                    }
                                }
                            }
                            if (isset($affectations_details[$invite['id']]['enfant'])) {
                                $enfants_places = $affectations_details[$invite['id']]['enfant']['nombre'];
                                $table_id = $affectations_details[$invite['id']]['enfant']['table_id'];
                                foreach ($tables as $t) {
                                    if ($t['id'] == $table_id) {
                                        $tables_enfants[] = 'T' . $t['numero'];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $adultes_restants = $nb_adultes - $adultes_places;
                        $enfants_restants = $nb_enfants - $enfants_places;
                        $tous_places = $adultes_restants == 0 && $enfants_restants == 0;
                        ?>
                        
                        <?php if (!$tous_places): ?>
                        <!-- Groupe famille complet si pas tous placés -->
                        <div class="invite-item <?= ($adultes_places > 0 || $enfants_places > 0) ? 'partial' : '' ?>" 
                             data-invite-id="<?= $invite['id'] ?>"
                             data-nb-personnes="<?= $nb_total ?>"
                             data-nb-adultes="<?= $nb_adultes ?>"
                             data-nb-enfants="<?= $nb_enfants ?>"
                             data-type="tous"
                             draggable="true">
                            <div>
                                <div><strong><?= htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']) ?></strong></div>
                                <?php if ($invite['groupe']): ?>
                                    <small style="color: #666;"><?= htmlspecialchars($invite['groupe']) ?></small>
                                <?php endif; ?>
                                <?php if ($adultes_places > 0 || $enfants_places > 0): ?>
                                    <small style="color: #ff9800;">
                                        Partiellement placé
                                        <?php 
                                        $placements = [];
                                        if ($adultes_places > 0) $placements[] = $adultes_places . ' adulte(s) ' . implode(',', $tables_adultes);
                                        if ($enfants_places > 0) $placements[] = $enfants_places . ' enfant(s) ' . implode(',', $tables_enfants);
                                        echo '(' . implode(', ', $placements) . ')';
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="people-count">
                                    <?= $nb_adultes ?> <i class="fas fa-user"></i>
                                    <?php if ($nb_enfants > 0): ?>
                                        + <?= $nb_enfants ?> <i class="fas fa-child"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($nb_adultes > 0 && $adultes_restants > 0): ?>
                        <!-- Adultes séparés -->
                        <div class="invite-item sub-item" 
                             data-invite-id="<?= $invite['id'] ?>"
                             data-nb-personnes="<?= $adultes_restants ?>"
                             data-nb-adultes="<?= $adultes_restants ?>"
                             data-nb-enfants="0"
                             data-type="adultes"
                             draggable="true">
                            <div style="padding-left: 20px;">
                                <div>
                                    <i class="fas fa-user"></i> 
                                    <?= $adultes_restants ?> adulte<?= $adultes_restants > 1 ? 's' : '' ?> seulement
                                </div>
                                <small style="color: #999;">de <?= htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']) ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($nb_enfants > 0 && $enfants_restants > 0): ?>
                        <!-- Enfants séparés -->
                        <div class="invite-item sub-item" 
                             data-invite-id="<?= $invite['id'] ?>"
                             data-nb-personnes="<?= $enfants_restants ?>"
                             data-nb-adultes="0"
                             data-nb-enfants="<?= $enfants_restants ?>"
                             data-type="enfants"
                             draggable="true">
                            <div style="padding-left: 20px;">
                                <div>
                                    <i class="fas fa-child"></i> 
                                    <?= $enfants_restants ?> enfant<?= $enfants_restants > 1 ? 's' : '' ?>
                                </div>
                                <small style="color: #999;">de <?= htmlspecialchars($invite['prenom'] . ' ' . $invite['nom']) ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="plan-area">
                <h3>Disposition des tables</h3>
                <p style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Glissez-déposez les tables pour les repositionner. 
                    Glissez les invités sur les tables pour les affecter. Cliquez sur une table pour voir les détails.
                </p>
                
                <div class="plan-container" id="planContainer">
                    <?php foreach ($tables as $table): ?>
                        <?php
                        // Calculer l'occupation de la table
                        $stmt = $db->prepare("
                            SELECT 
                                SUM(CASE WHEN type = 'adulte' THEN nombre ELSE 0 END) as adultes,
                                SUM(CASE WHEN type = 'enfant' THEN nombre ELSE 0 END) as enfants
                            FROM affectations_tables
                            WHERE table_id = ?
                        ");
                        $stmt->execute([$table['id']]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $occupation_adultes = $result['adultes'] ?: 0;
                        $occupation_enfants = $result['enfants'] ?: 0;
                        $occupation_totale = $occupation_adultes + $occupation_enfants;
                        
                        $class = 'empty';
                        if ($occupation_totale >= $table['capacite']) {
                            $class = 'full';
                        } elseif ($occupation_totale > 0) {
                            $class = 'partial';
                        }
                        ?>
                        <div class="table-box <?= $class ?> <?= $table['forme'] ?>" 
                             id="table-<?= $table['id'] ?>"
                             data-table-id="<?= $table['id'] ?>"
                             data-capacite="<?= $table['capacite'] ?>"
                             style="left: <?= $table['position_x'] ?>px; top: <?= $table['position_y'] ?>px;"
                             onclick="showTableDetails(<?= $table['id'] ?>)">
                            <h4>Table <?= $table['numero'] ?></h4>
                            <div class="table-name"><?= htmlspecialchars($table['nom']) ?></div>
                            <div class="occupancy">
                                <?= $occupation_adultes ?> <i class="fas fa-user"></i>
                                <?php if ($occupation_enfants > 0): ?>
                                    + <?= $occupation_enfants ?> <i class="fas fa-child"></i>
                                <?php endif; ?>
                            </div>
                            <div class="capacity"><?= $occupation_totale ?>/<?= $table['capacite'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajout/Modification Table -->
    <div id="tableModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('tableModal')">&times;</span>
            <h2>Ajouter une table</h2>
            <form id="tableForm" onsubmit="return submitTableForm(event)">
                <input type="hidden" name="action" value="ajouter_table">
                
                <div class="form-group">
                    <label>Numéro de table *</label>
                    <input type="number" name="numero" id="numero" required min="1">
                </div>
                
                <div class="form-group">
                    <label>Nom de la table</label>
                    <input type="text" name="nom" id="nom" placeholder="Ex: Table d'honneur, Table famille...">
                </div>
                
                <div class="form-group">
                    <label>Capacité *</label>
                    <input type="number" name="capacite" id="capacite" value="10" required min="1">
                </div>
                
                <div class="form-group">
                    <label>Forme</label>
                    <select name="forme" id="forme">
                        <option value="ronde">Ronde</option>
                        <option value="rectangulaire">Rectangulaire</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Créer la table</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Détails Table -->
    <div id="tableDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('tableDetailsModal')">&times;</span>
            <h2 id="tableDetailsTitle">Détails de la table</h2>
            
            <div id="tableDetailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
    
    <script>
        let selectedTable = null;
        let draggedElement = null;
        let draggedInvite = null;
        let offset = { x: 0, y: 0 };
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Rendre les tables déplaçables
            document.querySelectorAll('.table-box').forEach(table => {
                table.addEventListener('mousedown', startDragTable);
                table.addEventListener('dragover', allowDrop);
                table.addEventListener('drop', dropInvite);
                table.addEventListener('dragenter', dragEnter);
                table.addEventListener('dragleave', dragLeave);
            });
            
            // Rendre les invités déplaçables
            document.querySelectorAll('.invite-item').forEach(invite => {
                invite.addEventListener('dragstart', dragStart);
                invite.addEventListener('dragend', dragEnd);
            });
            
            // Recherche d'invités
            document.getElementById('searchInvite').addEventListener('input', filterInvites);
        });
        
        // Fonctions de modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fermer les modales en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Soumission du formulaire de table
        function submitTableForm(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            fetch('admin-table-map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
            
            return false;
        }
        
        // Drag & Drop des tables
        function startDragTable(e) {
            if (e.target.classList.contains('table-box')) {
                draggedElement = e.target;
                const rect = draggedElement.getBoundingClientRect();
                const parentRect = draggedElement.parentElement.getBoundingClientRect();
                offset.x = e.clientX - rect.left;
                offset.y = e.clientY - rect.top;
                
                draggedElement.style.cursor = 'grabbing';
                draggedElement.style.zIndex = '1000';
                
                document.addEventListener('mousemove', dragTable);
                document.addEventListener('mouseup', stopDragTable);
                
                e.preventDefault();
            }
        }
        
        function dragTable(e) {
            if (draggedElement) {
                const parentRect = draggedElement.parentElement.getBoundingClientRect();
                let x = e.clientX - parentRect.left - offset.x;
                let y = e.clientY - parentRect.top - offset.y;
                
                // Limites
                x = Math.max(0, Math.min(x, parentRect.width - draggedElement.offsetWidth));
                y = Math.max(0, Math.min(y, parentRect.height - draggedElement.offsetHeight));
                
                draggedElement.style.left = x + 'px';
                draggedElement.style.top = y + 'px';
            }
        }
        
        function stopDragTable() {
            if (draggedElement) {
                draggedElement.style.cursor = 'move';
                draggedElement.style.zIndex = '';
                draggedElement = null;
            }
            document.removeEventListener('mousemove', dragTable);
            document.removeEventListener('mouseup', stopDragTable);
        }
        
        // Drag & Drop des invités
        function dragStart(e) {
            draggedInvite = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
            this.classList.add('dragging');
        }
        
        function dragEnd(e) {
            this.classList.remove('dragging');
            draggedInvite = null;
            
            // Retirer toutes les classes drag-over
            document.querySelectorAll('.drag-over').forEach(el => {
                el.classList.remove('drag-over');
            });
        }
        
        function allowDrop(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }
        
        function dragEnter(e) {
            if (e.target.classList.contains('table-box')) {
                e.target.classList.add('drag-over');
            }
        }
        
        function dragLeave(e) {
            if (e.target.classList.contains('table-box')) {
                e.target.classList.remove('drag-over');
            }
        }
        
        function dropInvite(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const tableBox = e.target.closest('.table-box');
            if (!tableBox || !draggedInvite) return;
            
            tableBox.classList.remove('drag-over');
            
            const inviteId = draggedInvite.dataset.inviteId;
            const type = draggedInvite.dataset.type || 'tous';
            const nbPersonnes = parseInt(draggedInvite.dataset.nbPersonnes);
            const newTableId = tableBox.dataset.tableId;
            const tableCapacite = parseInt(tableBox.dataset.capacite);
            
            // Vérifier la capacité
            const capacityText = tableBox.querySelector('.capacity').textContent;
            const [current, capacity] = capacityText.split('/').map(n => parseInt(n));
            
            if (current + nbPersonnes > tableCapacite) {
                alert('Cette table n\'a pas assez de places disponibles ! Il manque ' + 
                      (current + nbPersonnes - tableCapacite) + ' place(s).');
                return;
            }
            
            affecterInvite(inviteId, newTableId, type);
        }
        
        // Affectation d'un invité à une table
        function affecterInvite(inviteId, tableId, type = 'tous') {
            const formData = new FormData();
            formData.append('action', 'affecter_invite');
            formData.append('invite_id', inviteId);
            formData.append('table_id', tableId);
            formData.append('type', type);
            
            fetch('admin-table-map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Afficher les détails d'une table
        function showTableDetails(tableId) {
            const formData = new FormData();
            formData.append('action', 'get_table_details');
            formData.append('table_id', tableId);
            
            fetch('admin-table-map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayTableDetails(data.table, data.invites);
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        function displayTableDetails(table, invites) {
            document.getElementById('tableDetailsTitle').textContent = 
                `Table ${table.numero} - ${table.nom}`;
            
            let content = '<div>';
            content += `<p><strong>Capacité :</strong> ${table.capacite} places</p>`;
            content += `<p><strong>Forme :</strong> ${table.forme === 'ronde' ? 'Ronde' : 'Rectangulaire'}</p>`;
            
            if (table.notes) {
                content += `<p><strong>Notes :</strong> ${table.notes}</p>`;
            }
            
            content += '<h3>Invités à cette table :</h3>';
            content += '<div class="guest-list-table">';
            
            let totalAdultes = 0;
            let totalEnfants = 0;
            
            if (invites.length === 0) {
                content += '<p style="color: #999; text-align: center;">Aucun invité affecté à cette table</p>';
            } else {
                invites.forEach(invite => {
                    const adultes_table = parseInt(invite.adultes_table) || 0;
                    const enfants_table = parseInt(invite.enfants_table) || 0;
                    
                    totalAdultes += adultes_table;
                    totalEnfants += enfants_table;
                    
                    content += '<div class="guest-table-item">';
                    content += '<div class="guest-info">';
                    content += `<div class="name">${invite.prenom} ${invite.nom}</div>`;
                    content += '<div class="details">';
                    
                    let details = [];
                    if (adultes_table > 0) {
                        details.push(`${adultes_table} adulte${adultes_table > 1 ? 's' : ''}`);
                    }
                    if (enfants_table > 0) {
                        details.push(`${enfants_table} enfant${enfants_table > 1 ? 's' : ''}`);
                    }
                    
                    content += details.join(' + ');
                    
                    // Afficher si c'est une affectation partielle
                    if (adultes_table < invite.nombre_adultes || enfants_table < invite.nombre_enfants) {
                        content += ' <span style="color: #ff9800;">(partiel)</span>';
                    }
                    
                    if (invite.groupe) {
                        content += ` • ${invite.groupe}`;
                    }
                    content += '</div>';
                    content += '</div>';
                    content += '<div>';
                    content += `<button class="btn btn-danger btn-small" onclick="retirerInvite(${invite.id}, ${table.id})">`;
                    content += '<i class="fas fa-times"></i> Retirer</button>';
                    content += '</div>';
                    content += '</div>';
                });
            }
            
            content += '</div>';
            content += '<div style="margin-top: 15px; padding: 10px; background-color: #f5f5f5; border-radius: 5px;">';
            content += `<strong>Total : ${totalAdultes} adulte${totalAdultes > 1 ? 's' : ''}`;
            if (totalEnfants > 0) {
                content += ` et ${totalEnfants} enfant${totalEnfants > 1 ? 's' : ''}`;
            }
            content += ` = ${totalAdultes + totalEnfants}/${table.capacite} places</strong>`;
            content += '</div>';
            
            content += '<div style="margin-top: 20px; text-align: center;">';
            content += `<button class="btn btn-primary btn-small" onclick="modifierTable(${table.id})">`;
            content += '<i class="fas fa-edit"></i> Modifier la table</button>';
            content += `<button class="btn btn-danger btn-small" onclick="supprimerTable(${table.id}, '${table.nom}')">`;
            content += '<i class="fas fa-trash"></i> Supprimer la table</button>';
            content += '</div>';
            content += '</div>';
            
            document.getElementById('tableDetailsContent').innerHTML = content;
            openModal('tableDetailsModal');
        }
        
        // Retirer un invité d'une table
        function retirerInvite(inviteId, tableId) {
            if (confirm('Retirer cet invité de la table ?')) {
                const formData = new FormData();
                formData.append('action', 'retirer_invite');
                formData.append('invite_id', inviteId);
                formData.append('table_id', tableId);
                
                fetch('admin-table-map.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('tableDetailsModal');
                        location.reload();
                    } else {
                        alert('Erreur : ' + data.message);
                    }
                });
            }
        }
        
        // Modifier une table
        function modifierTable(tableId) {
            alert('Fonction en cours de développement');
        }
        
        // Supprimer une table
        function supprimerTable(tableId, tableName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la table "${tableName}" ?\n\nTous les invités de cette table seront remis dans la liste des invités à placer.`)) {
                const formData = new FormData();
                formData.append('action', 'supprimer_table');
                formData.append('table_id', tableId);
                
                fetch('admin-table-map.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('tableDetailsModal');
                        location.reload();
                    } else {
                        alert('Erreur : ' + data.message);
                    }
                });
            }
        }
        
        // Filtrer les invités
        function filterInvites() {
            const searchValue = this.value.toLowerCase();
            const invites = document.querySelectorAll('.invite-item');
            
            invites.forEach(invite => {
                const text = invite.textContent.toLowerCase();
                invite.style.display = text.includes(searchValue) ? '' : 'none';
            });
        }
        
        // Sauvegarder les positions des tables
        function sauvegarderPositions() {
            const positions = {};
            document.querySelectorAll('.table-box').forEach(table => {
                const tableId = table.dataset.tableId;
                positions[tableId] = {
                    x: parseInt(table.style.left) || 0,
                    y: parseInt(table.style.top) || 0
                };
            });
            
            const formData = new FormData();
            formData.append('action', 'sauvegarder_positions');
            formData.append('positions', JSON.stringify(positions));
            
            fetch('admin-table-map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher un message de succès temporaire
                    const message = document.createElement('div');
                    message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 15px; border-radius: 5px; z-index: 1000;';
                    message.innerHTML = '<i class="fas fa-check"></i> Positions sauvegardées !';
                    document.body.appendChild(message);
                    setTimeout(() => message.remove(), 3000);
                } else {
                    alert('Erreur : ' + data.message);
                }
            });
        }
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
            
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                sauvegarderPositions();
            }
        });
    </script>
    <script src="../config/theme-config.js"></script>
</body>
</html>