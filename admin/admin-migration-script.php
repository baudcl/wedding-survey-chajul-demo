<?php
// Script de migration pour ajouter la gestion séparée des enfants
// À exécuter une seule fois pour mettre à jour la structure de la base de données

$db_file = __DIR__ . '/../server/db/responses.db';

try {
    echo "=== Migration de la base de données ===\n\n";
    
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si les colonnes type et nombre existent
    $stmt = $db->query("PRAGMA table_info(affectations_tables)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_type = false;
    $has_nombre = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'type') $has_type = true;
        if ($column['name'] === 'nombre') $has_nombre = true;
    }
    
    if ($has_type && $has_nombre) {
        echo "✓ La base de données est déjà à jour.\n";
        exit(0);
    }
    
    echo "→ Migration nécessaire...\n";
    
    // Commencer une transaction
    $db->beginTransaction();
    
    try {
        // 1. Sauvegarder les données existantes
        echo "→ Sauvegarde des affectations existantes...\n";
        $existing_data = $db->query("SELECT * FROM affectations_tables")->fetchAll(PDO::FETCH_ASSOC);
        echo "  " . count($existing_data) . " affectations trouvées\n";
        
        // 2. Créer une table temporaire avec la nouvelle structure
        echo "→ Création de la nouvelle structure...\n";
        $db->exec("CREATE TABLE affectations_tables_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invite_id INTEGER,
            table_id INTEGER,
            position INTEGER,
            type TEXT DEFAULT 'adulte',
            nombre INTEGER DEFAULT 1,
            FOREIGN KEY (invite_id) REFERENCES invites(id),
            FOREIGN KEY (table_id) REFERENCES tables_mariage(id)
        )");
        
        // 3. Migrer les données
        echo "→ Migration des données...\n";
        $count_migrated = 0;
        
        foreach ($existing_data as $row) {
            // Récupérer les infos de l'invité
            $stmt = $db->prepare("SELECT nombre_adultes, nombre_enfants, prenom, nom FROM invites WHERE id = ?");
            $stmt->execute([$row['invite_id']]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($invite) {
                echo "  - " . $invite['prenom'] . " " . $invite['nom'] . " : ";
                
                // Insérer les adultes
                if ($invite['nombre_adultes'] > 0) {
                    $stmt = $db->prepare("INSERT INTO affectations_tables_new (invite_id, table_id, type, nombre, position) VALUES (?, ?, 'adulte', ?, ?)");
                    $stmt->execute([
                        $row['invite_id'], 
                        $row['table_id'], 
                        $invite['nombre_adultes'],
                        $row['position'] ?? null
                    ]);
                    echo $invite['nombre_adultes'] . " adulte(s) ";
                }
                
                // Insérer les enfants
                if ($invite['nombre_enfants'] > 0) {
                    $stmt = $db->prepare("INSERT INTO affectations_tables_new (invite_id, table_id, type, nombre, position) VALUES (?, ?, 'enfant', ?, ?)");
                    $stmt->execute([
                        $row['invite_id'], 
                        $row['table_id'], 
                        $invite['nombre_enfants'],
                        $row['position'] ?? null
                    ]);
                    echo "+ " . $invite['nombre_enfants'] . " enfant(s)";
                }
                
                echo "\n";
                $count_migrated++;
            }
        }
        
        echo "  " . $count_migrated . " affectations migrées\n";
        
        // 4. Remplacer l'ancienne table
        echo "→ Remplacement de l'ancienne table...\n";
        $db->exec("DROP TABLE affectations_tables");
        $db->exec("ALTER TABLE affectations_tables_new RENAME TO affectations_tables");
        
        // Valider la transaction
        $db->commit();
        
        echo "\n✓ Migration terminée avec succès !\n";
        echo "\nLa base de données permet maintenant de gérer les adultes et enfants séparément.\n";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\n✗ Erreur lors de la migration : " . $e->getMessage() . "\n";
    exit(1);
}
?>