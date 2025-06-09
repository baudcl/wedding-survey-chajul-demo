<?php
session_start();

// Simple authentication
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === 'admin123') { // À remplacer par un mot de passe sécurisé
        $_SESSION['admin_logged_in'] = true;
    } else {
        echo '<form method="POST" style="text-align: center; margin-top: 100px;">
                <h2 style="color: #8DB1A8;">Administration - Mariage de Charlotte & Julien</h2>
                <input type="password" name="password" placeholder="Entrez le mot de passe" style="padding: 10px; font-size: 1rem; border: 1px solid #8DB1A8; border-radius: 5px;">
                <button type="submit" style="padding: 10px 20px; font-size: 1rem; background-color: #8DB1A8; color: white; border: none; border-radius: 5px; cursor: pointer;">Connexion</button>
              </form>';
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Configuration des chemins
$db_file = __DIR__ . '/../server/db/responses.db';

// Vérifier que le fichier de base de données existe
if (!file_exists($db_file)) {
    $error_message = "Base de données non trouvée à l'emplacement : $db_file";
    $responses = [];
} else {
    try {
        $db = new PDO('sqlite:' . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Récupérer les réponses
        $responses = $db->query("SELECT * FROM responses ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

        // ... reste du code de traitement des statistiques
        
    } catch (PDOException $e) {
        $error_message = "Erreur de base de données: " . $e->getMessage();
        $responses = [];
    }
}

// Statistiques
$total_adultes = 0;
$total_enfants = 0;
$total_invites = 0;
$repartition_hebergement = [
    'perso' => 0,
    'reco' => 0,
    'autre' => 0
];

// Liste des enfants avec leur famille
$enfants_liste = [];

// Database connection
try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les réponses
    $responses = $db->query("SELECT * FROM responses ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques
    foreach ($responses as $key => $response) {
        // Informations de la famille
        $famille_nom = $response['prenom'] . ' ' . $response['nom'];
        $famille_email = $response['email'];
        
        // Compter les adultes
        $adultes = isset($response['adultes']) ? (int)$response['adultes'] : 0;
        $total_adultes += $adultes;
        
        // Compter les enfants
        $enfants_count = 0;
        if (isset($response['enfants']) && !empty($response['enfants'])) {
            // Décoder le JSON des enfants - Vérifier d'abord que c'est une chaîne JSON
            if (is_string($response['enfants'])) {
                $enfants_decoded = json_decode($response['enfants'], true);
                
                // Vérifier si le décodage a fonctionné et que le résultat est un tableau
                if (is_array($enfants_decoded)) {
                    $enfants_count = count($enfants_decoded);
                    $total_enfants += $enfants_count;
                    
                    // Ajouter les informations des enfants dans un format lisible
                    $enfants_text = '';
                    foreach ($enfants_decoded as $enfant) {
                        if (is_array($enfant) && isset($enfant['prenom']) && isset($enfant['age'])) {
                            $enfants_text .= htmlspecialchars($enfant['prenom']) . ' (' . htmlspecialchars($enfant['age']) . ' ans)<br>';
                            
                            // Ajouter à la liste des enfants
                            $enfants_liste[] = [
                                'prenom' => $enfant['prenom'],
                                'age' => $enfant['age'],
                                'famille' => $famille_nom,
                                'email' => $famille_email
                            ];
                        }
                    }
                    $responses[$key]['enfants_formatted'] = $enfants_text;
                } else {
                    // Si le JSON est invalide, afficher tel quel
                    $responses[$key]['enfants_formatted'] = htmlspecialchars($response['enfants']);
                }
            } else {
                // Si ce n'est pas une chaîne, afficher un message d'erreur
                $responses[$key]['enfants_formatted'] = 'Format non valide';
            }
        } else {
            $responses[$key]['enfants_formatted'] = 'Aucun';
        }
        
        // Total des invités par réponse
        $responses[$key]['total_invites'] = $adultes + $enfants_count;
        $total_invites += $responses[$key]['total_invites'];
        
        // Répartition hébergement
        if (isset($response['hebergement'])) {
            $hebergement = $response['hebergement'];
            if ($hebergement === 'perso') {
                $repartition_hebergement['perso']++;
            } elseif ($hebergement === 'reco') {
                $repartition_hebergement['reco']++;
            } else {
                $repartition_hebergement['autre']++;
            }
        }
    }
    
    // Trier la liste des enfants par âge
    usort($enfants_liste, function($a, $b) {
        return (int)$a['age'] - (int)$b['age'];
    });

} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
    $responses = [];
    $enfants_liste = [];
}

// Préparer les données pour export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reponses_mariage_' . date('Y-m-d') . '.csv"');
    
    // Créer un flux de sortie
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Entêtes CSV - avec les paramètres complets pour fputcsv
    fputcsv($output, [
        'Date', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Adresse', 'Code Postal', 
        'Ville', 'Pays', "Nombre d'adultes", 'Enfants', 'Hébergement', 
        'Précisions allergies'
    ], ',', '"', "\\", "\n");
    
    // Données
    foreach ($responses as $response) {
        fputcsv($output, [
            $response['date'],
            $response['prenom'],
            $response['nom'],
            $response['email'],
            $response['telephone'],
            $response['adresse'],
            $response['code_postal'],
            $response['ville'],
            $response['pays'],
            $response['adultes'],
            isset($response['enfants_formatted']) ? strip_tags($response['enfants_formatted']) : 'Aucun',
            $response['hebergement'],
            $response['precisions_allergies'] ?: 'Aucune'
        ], ',', '"', "\\", "\n");
    }
    
    fclose($output);
    exit;
}

// Export de la liste des enfants en CSV
if (isset($_GET['export_enfants']) && $_GET['export_enfants'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="liste_enfants_mariage_' . date('Y-m-d') . '.csv"');
    
    // Créer un flux de sortie
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Entêtes CSV - avec les paramètres complets pour fputcsv
    fputcsv($output, ['Prénom', 'Âge', 'Famille', 'Email'], ',', '"', "\\", "\n");
    
    // Données
    foreach ($enfants_liste as $enfant) {
        fputcsv($output, [
            $enfant['prenom'],
            $enfant['age'],
            $enfant['famille'],
            $enfant['email']
        ], ',', '"', "\\", "\n");
    }
    
    fclose($output);
    exit;
}

// Définir les couleurs pour le tableau de bord
$colors = [
    'primary' => '#8DB1A8',
    'secondary' => '#EFA8B4',
    'background' => '#FFF8F1',
    'text' => '#333333',
    'border' => '#dddddd'
];

// Définir le chemin absolu vers la racine du site
$root_path = dirname(__DIR__); // remonte d'un niveau depuis le dossier admin/

// Fonction pour vérifier si un fichier existe et l'obtenir
function get_file_contents($path) {
    if (file_exists($path)) {
        return file_get_contents($path);
    }
    return false;
}

// Fonction pour tester différents chemins possibles
function find_file($filenames, $base_paths) {
    foreach ($base_paths as $base) {
        foreach ($filenames as $file) {
            $path = $base . '/' . $file;
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    return false;
}

// Chemins possibles pour les polices
$font_paths = [
    $root_path . '/ressources/fonts/rtl-adam-script',
    __DIR__ . '/ressources/fonts/rtl-adam-script',
    __DIR__ . '/../ressources/fonts/rtl-adam-script',
    __DIR__ . '/../../ressources/fonts/rtl-adam-script',
    // Ajouter d'autres chemins possibles ici
];

// Noms possibles pour les fichiers de police
$font_files = [
    'RTL-AdamScript.woff2',
    'RTL-AdamScript.woff',
    'RTL-AdamScript.ttf'
];

// Trouver la police
$woff2_path = find_file(['RTL-AdamScript.woff2'], $font_paths);
$woff_path = find_file(['RTL-AdamScript.woff'], $font_paths);
$ttf_path = find_file(['RTL-AdamScript.ttf'], $font_paths);

// Journaliser le résultat de la recherche
$log_file = __DIR__ . '/font-search.log';
file_put_contents($log_file, "Recherche de polices (" . date('Y-m-d H:i:s') . "):\n", FILE_APPEND);
file_put_contents($log_file, "WOFF2: " . ($woff2_path ? $woff2_path : "Non trouvé") . "\n", FILE_APPEND);
file_put_contents($log_file, "WOFF: " . ($woff_path ? $woff_path : "Non trouvé") . "\n", FILE_APPEND);
file_put_contents($log_file, "TTF: " . ($ttf_path ? $ttf_path : "Non trouvé") . "\n", FILE_APPEND);

// Base64 encode les polices trouvées
$woff2_data = $woff2_path ? 'data:font/woff2;base64,' . base64_encode(file_get_contents($woff2_path)) : '';
$woff_data = $woff_path ? 'data:font/woff;base64,' . base64_encode(file_get_contents($woff_path)) : '';
$ttf_data = $ttf_path ? 'data:font/ttf;base64,' . base64_encode(file_get_contents($ttf_path)) : '';

// Créer une déclaration de police intégrée
$embedded_font_css = '';
if ($woff2_data || $woff_data || $ttf_data) {
    $embedded_font_css = "@font-face {\n";
    $embedded_font_css .= "    font-family: 'RTL-Adam Script';\n";
    $embedded_font_css .= "    font-style: normal;\n";
    $embedded_font_css .= "    font-weight: normal;\n";
    $embedded_font_css .= "    font-display: swap;\n";
    $embedded_font_css .= "    src: ";
    
    $src_parts = [];
    if ($woff2_data) {
        $src_parts[] = "url('" . $woff2_data . "') format('woff2')";
    }
    if ($woff_data) {
        $src_parts[] = "url('" . $woff_data . "') format('woff')";
    }
    if ($ttf_data) {
        $src_parts[] = "url('" . $ttf_data . "') format('truetype')";
    }
    
    $embedded_font_css .= implode(",\n         ", $src_parts) . ";\n";
    $embedded_font_css .= "}\n";
} else {
    // Police de secours
    $embedded_font_css = "/* Police RTL-Adam Script non trouvée - utilisation d'une police de secours */\n";
    $embedded_font_css .= "@font-face {\n";
    $embedded_font_css .= "    font-family: 'RTL-Adam Script';\n";
    $embedded_font_css .= "    src: local('Brush Script MT'), local('Comic Sans MS'), local('cursive');\n";
    $embedded_font_css .= "}\n";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Mariage de Charlotte & Julien</title>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: <?= $colors['background'] ?>;
            color: <?= $colors['text'] ?>;
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
            background-color: <?= $colors['primary'] ?>;
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        h1 {
            font-family: 'RTL-Adam Script', serif;
            font-size: 3rem;
            margin: 0;
        }
        
        h2 {
            font-family: 'Montserrat', sans-serif;
            color: <?= $colors['secondary'] ?>;
            margin-top: 40px;
            border-bottom: 2px solid <?= $colors['secondary'] ?>;
            padding-bottom: 10px;
        }
        
        .dashboard {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            flex: 1;
            min-width: 200px;
        }
        
        .card h3 {
            color: <?= $colors['primary'] ?>;
            margin-top: 0;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: <?= $colors['secondary'] ?>;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
        }
        
        th, td {
            border: 1px solid <?= $colors['border'] ?>;
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: <?= $colors['primary'] ?>;
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
        
        .table-container {
            overflow-x: auto;
            max-width: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            margin-top: 30px;
        }
        
        .export-button {
            display: inline-block;
            background-color: <?= $colors['primary'] ?>;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .export-button:hover {
            background-color: <?= $colors['secondary'] ?>;
        }
        
        .logout-button {
            display: inline-block;
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-left: 10px;
            font-weight: bold;
        }
        
        .logout-button:hover {
            background-color: #d32f2f;
        }
        
        .actions {
            margin: 20px 0;
            display: flex;
            align-items: center;
        }
        
        .search-box {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid <?= $colors['border'] ?>;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .tabs {
            display: flex;
            margin-top: 30px;
            border-bottom: 1px solid <?= $colors['border'] ?>;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f5f5f5;
            border: 1px solid <?= $colors['border'] ?>;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background-color: white;
            border-bottom: 2px solid white;
            margin-bottom: -1px;
            font-weight: bold;
            color: <?= $colors['primary'] ?>;
        }
        
        .tab:hover:not(.active) {
            background-color: #e9e9e9;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        footer {
            margin-top: 50px;
            text-align: center;
            padding: 20px;
            background-color: <?= $colors['primary'] ?>;
            color: white;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .card {
                min-width: 100%;
            }
            
            .actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .export-button, .logout-button {
                width: 100%;
                text-align: center;
                margin: 5px 0;
            }
            
            .search-box {
                width: 100%;
            }
            .export-button i {
                font-size: 1.2rem;
            }

            .export-button:hover i {
                transform: scale(1.1);
                transition: transform 0.2s;
            }
        }
    </style>
    <link rel="stylesheet" href="../ressources/css/style.css">
    <link rel="stylesheet" href="../ressources/fonts/rtl-adam-script/stylesheet-rtl-adamscript.css">   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
</head>
<body>
    <header>
        <h1>Mariage de Charlotte & Julien</h1>
        <p>Tableau de bord d'administration</p>
    </header>
    
    <div class="container">
        <div class="actions">
            <a href="?export=csv" class="export-button">Exporter toutes les réponses</a>
            <a href="?export_enfants=csv" class="export-button">Exporter liste des enfants</a>
            <input type="text" id="searchBox" class="search-box" placeholder="Rechercher..." onkeyup="filterTable()">
            <a href="?logout=1" class="logout-button">Déconnexion</a>
        </div>
        
        <!-- Ajouter après la section des statistiques du tableau de bord (après la div.dashboard) : -->

        <div style="margin: 30px 0;">
            <h2 style="margin-bottom: 20px;">Gestion du mariage</h2>
            <div style="display: flex; justify-content: left; gap: 20px; flex-wrap: wrap;">
                <a href="admin-manage-guests.php" class="export-button" style="min-width: 200px;">
                    <i class="fas fa-users" style="margin-right: 10px;"></i>
                    Gérer la liste des invités
                </a>
                <a href="admin-table-map.php" class="export-button" style="min-width: 200px; background-color: #EFA8B4;">
                    <i class="fas fa-chair" style="margin-right: 10px;"></i>
                    Créer le plan de table
                </a>
            </div>
        </div>

        <h2>Tableau de bord des réponses</h2>
        
        <div class="dashboard">
            <div class="card">
                <h3>Total des invités</h3>
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
                <h3>Réponses reçues</h3>
                <div class="stats-number"><?= count($responses) ?></div>
                <p>familles</p>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="card">
                <h3>Hébergement</h3>
                <p><strong>Personnels :</strong> <?= $repartition_hebergement['perso'] ?></p>
                <p><strong>Recommandations souhaitées :</strong> <?= $repartition_hebergement['reco'] ?></p>
                <p><strong>Autres :</strong> <?= $repartition_hebergement['autre'] ?></p>
            </div>
        </div>
        
        <div class="tabs">
            <div id="tab-reponses" class="tab active" onclick="showTab('reponses')">Toutes les réponses</div>
            <div id="tab-enfants" class="tab" onclick="showTab('enfants')">Liste des enfants</div>
            <div id="tab-chansons" class="tab" onclick="showTab('chansons')">Chansons</div>
            <div id="tab-details-magiques" class="tab" onclick="showTab('details-magiques')">Détails magiques</div>
            <div id="tab-messages" class="tab" onclick="showTab('messages')">Messages aux mariés</div>
        </div>
        
        <div id="reponses" class="tab-content active">
            <h2>Liste des réponses</h2>
            
            <?php if (isset($error_message)): ?>
                <div style="background-color: #f44336; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($responses)): ?>
                <p style="text-align: center; font-size: 1.2rem; color: #333;">
                    Aucune réponse n'a encore été reçue.
                </p>
            <?php else: ?>
                <div class="table-container">
                    <table id="responsesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Adultes</th>
                                <th>Enfants</th>
                                <th style="background-color: #EFA8B4; color: white;">Total</th>
                                <th>Hébergement</th>
                                <th>Allergies</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td><?= htmlspecialchars($response['date']) ?></td>
                                    <td><?= htmlspecialchars($response['prenom'] . ' ' . $response['nom']) ?></td>
                                    <td><?= htmlspecialchars($response['email']) ?>
                                        <?php
                                            // Vérifier si cet invité est dans la liste des invités
                                            $stmt_check = $db->prepare("SELECT statut FROM invites WHERE email = ?");
                                            $stmt_check->execute([$response['email']]);
                                            $invite_info = $stmt_check->fetch();

                                            if ($invite_info): ?>
                                                <br><span class="status-badge confirme">Dans la liste</span>
                                            <?php else: ?>
                                                <br><span class="status-badge en_attente">À ajouter</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($response['telephone']) ?></td>
                                    <td><?= htmlspecialchars($response['adultes']) ?></td>
                                    <td><?= $response['enfants_formatted'] ?></td>
                                    <td style="background-color: rgba(239, 168, 180, 0.2); font-weight: bold; color: #EFA8B4; text-align: center"><?= $response['total_invites'] ?></td>
                                    <td><?= htmlspecialchars($response['hebergement']) ?></td>
                                    <td><?= htmlspecialchars($response['precisions_allergies'] ?: 'Aucune') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="enfants" class="tab-content">
            <h2>Liste des enfants</h2>
            
            <?php if (empty($enfants_liste)): ?>
                <p style="text-align: center; font-size: 1.2rem; color: #333;">
                    Aucun enfant n'a été enregistré.
                </p>
            <?php else: ?>
                <div class="table-container">
                    <table id="enfantsTable">
                        <thead>
                            <tr>
                                <th>Prénom</th>
                                <th>Âge</th>
                                <th>Famille</th>
                                <th>Email de contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enfants_liste as $enfant): ?>
                                <tr>
                                    <td><?= htmlspecialchars($enfant['prenom']) ?></td>
                                    <td><?= htmlspecialchars($enfant['age']) ?> ans</td>
                                    <td><?= htmlspecialchars($enfant['famille']) ?></td>
                                    <td><?= htmlspecialchars($enfant['email']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <!-- Nouveau contenu pour l'onglet "Chansons" -->
        <div id="chansons" class="tab-content">
            <h2>Liste des chansons suggérées</h2>
    
            <?php 
            // Filtrer les réponses pour ne garder que celles avec des chansons
            $chansons_liste = [];
            foreach ($responses as $response) {
                if (!empty($response['chanson'])) {
                    $chansons_liste[] = [
                        'chanson' => $response['chanson'],
                        'invite' => $response['prenom'] . ' ' . $response['nom'],
                        'email' => $response['email']
                    ];
                }
            }
            ?>
    
            <?php if (empty($chansons_liste)): ?>
                <p style="text-align: center; font-size: 1.2rem; color: #333;">
                    Aucune chanson n'a été suggérée.
                </p>
            <?php else: ?>
                <div class="table-container">
                    <table id="chansonsTable">
                        <thead>
                            <tr>
                                <th>Chanson</th>
                                <th>Suggérée par</th>
                                <th>Email de contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chansons_liste as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['chanson']) ?></td>
                                    <td><?= htmlspecialchars($item['invite']) ?></td>
                                    <td><?= htmlspecialchars($item['email']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <div style="margin-top: 20px;">
            <a href="?export_chansons=csv" class="export-button">Exporter la liste des chansons</a>
        </div>
    <?php endif; ?>
</div>

<!-- Nouveau contenu pour l'onglet "Détails magiques" -->
<div id="details-magiques" class="tab-content">
    <h2>Détails magiques suggérés</h2>
    
    <?php 
    // Filtrer les réponses pour ne garder que celles avec des détails magiques
    $details_liste = [];
    foreach ($responses as $response) {
        if (!empty($response['suggestion_magique'])) {
            $details_liste[] = [
                'detail' => $response['suggestion_magique'],
                'invite' => $response['prenom'] . ' ' . $response['nom'],
                'email' => $response['email']
            ];
        }
    }
    ?>
    
    <?php if (empty($details_liste)): ?>
        <p style="text-align: center; font-size: 1.2rem; color: #333;">
            Aucun détail magique n'a été suggéré.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table id="detailsMagiquesTable">
                <thead>
                    <tr>
                        <th>Détail magique</th>
                        <th>Suggéré par</th>
                        <th>Email de contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details_liste as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['detail']) ?></td>
                            <td><?= htmlspecialchars($item['invite']) ?></td>
                            <td><?= htmlspecialchars($item['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 20px;">
            <a href="?export_details=csv" class="export-button">Exporter la liste des détails magiques</a>
        </div>
    <?php endif; ?>
</div>

<!-- Nouveau contenu pour l'onglet "Messages aux mariés" -->
<div id="messages" class="tab-content">
    <h2>Messages des invités</h2>
    
    <?php 
    // Filtrer les réponses pour ne garder que celles avec des messages
    $messages_liste = [];
    foreach ($responses as $response) {
        if (!empty($response['mot_maries'])) {
            $messages_liste[] = [
                'message' => $response['mot_maries'],
                'invite' => $response['prenom'] . ' ' . $response['nom'],
                'email' => $response['email']
            ];
        }
    }
    ?>
    
    <?php if (empty($messages_liste)): ?>
        <p style="text-align: center; font-size: 1.2rem; color: #333;">
            Aucun message n'a été laissé pour les mariés.
        </p>
    <?php else: ?>
        <div class="card-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($messages_liste as $message): ?>
                <div class="message-card" style="background-color: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; display: flex; flex-direction: column;">
                    <div style="flex-grow: 1;">
                        <p style="font-style: italic; white-space: pre-line;"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                    </div>
                    <div style="margin-top: 15px; text-align: right; border-top: 1px solid #eee; padding-top: 10px;">
                        <p style="margin: 0; font-weight: bold;"><?= htmlspecialchars($message['invite']) ?></p>
                        <p style="margin: 0; font-size: 0.9em; color: #666;"><?= htmlspecialchars($message['email']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 20px;">
            <a href="?export_messages=csv" class="export-button">Exporter les messages</a>
        </div>
    <?php endif; ?>
</div>
    </div>
    
    <footer>
        <p>"Le bonheur n'est réel que lorsqu'il est partagé."</p>
        <p style="font-family: 'RTL-Adam Script', serif; font-size: 1.5rem;">Charlotte & Julien</p>
    </footer>
    
    <script>
        // Fonction pour filtrer les tableaux en fonction de la recherche
        function filterTable() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toLowerCase();
            
            // Filtrer le tableau des réponses
            filterTableById('responsesTable', filter);
            
            // Filtrer le tableau des enfants
            filterTableById('enfantsTable', filter);
        }
        
        // Fonction générique pour filtrer un tableau par ID
        function filterTableById(tableId, filter) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                let found = false;
                const cells = rows[i].getElementsByTagName('td');
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    
                    if (cellText.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
        
        // Fonction pour changer d'onglet
        function showTab(tabName) {
            // Cacher tous les contenus d'onglet
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Désactiver tous les onglets
            const tabs = document.getElementsByClassName('tab');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Activer l'onglet et le contenu sélectionnés
            document.getElementById(tabName).classList.add('active');
            
            // Activer l'onglet correspondant - maintenant avec des ID spécifiques
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>

<script>
    // Script pour vérifier si la police RTL Adam Script est chargée correctement
    document.addEventListener('DOMContentLoaded', function() {
        // Fonction pour vérifier si une police est chargée
        function isFontLoaded(fontFamily) {
            // Créer des éléments de test
            const testElement = document.createElement('span');
            testElement.style.fontFamily = fontFamily + ', monospace';
            testElement.style.visibility = 'hidden';
            testElement.style.position = 'absolute';
            testElement.style.top = '-10px';
            testElement.style.left = '-10px';
            testElement.textContent = 'Test Font Loading';
            
            // Ajouter à la page
            document.body.appendChild(testElement);
            
            // Mesurer la largeur
            const width = testElement.offsetWidth;
            
            // Changer la police et mesurer à nouveau
            testElement.style.fontFamily = 'monospace';
            const fallbackWidth = testElement.offsetWidth;
            
            // Nettoyer
            document.body.removeChild(testElement);
            
            // Si les largeurs sont différentes, la police est chargée
            return width !== fallbackWidth;
        }
        
        // Vérifier la police après un délai pour lui donner le temps de charger
        setTimeout(function() {
            const fontLoaded = isFontLoaded('RTL-Adam Script');
            
            if (!fontLoaded) {
                console.warn('La police RTL-Adam Script ne semble pas être chargée correctement. Tentative de correction...');
                
                // Vérifier les chemins d'accès
                const cssLink = document.querySelector('link[href*="rtl-adam-script"]');
                if (!cssLink) {
                    console.warn('Lien CSS manquant pour RTL-Adam Script');
                }
                
                // Essayer de charger la police de manière dynamique
                const fontFace = new FontFace('RTL-Adam Script', 
                    'url("../ressources/fonts/rtl-adam-script/RTL-AdamScript.woff2")',
                    { style: 'normal', weight: 'normal' }
                );
                
                fontFace.load().then(function(loadedFace) {
                    document.fonts.add(loadedFace);
                    console.log('Police RTL-Adam Script chargée dynamiquement');
                    
                    // Appliquer directement
                    document.querySelectorAll('.rtl-adam-script').forEach(function(el) {
                        el.style.fontFamily = "'RTL-Adam Script', cursive";
                    });
                }).catch(function(error) {
                    console.error('Impossible de charger la police:', error);
                });
            } else {
                console.log('Police RTL-Adam Script chargée correctement');
            }
        }, 1000);
    });
</script>
</body>
</html>