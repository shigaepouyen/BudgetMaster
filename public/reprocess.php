<?php
// public/reprocess.php

// 1. Force l'affichage des erreurs au maximum
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>🚀 Démarrage du script de rattrapage...</h3>";

// 2. ASTUCE : On ajoute le dossier 'src' au chemin d'inclusion (include_path)
// Cela permet aux fichiers dans 'src/' de faire des 'require_once' entre eux sans erreur
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../src');

// 3. Chargement des dépendances
try {
    if (!file_exists(__DIR__ . '/../src/Database.php')) throw new Exception("Fichier Database.php introuvable");
    require_once __DIR__ . '/../src/Database.php';

    if (!file_exists(__DIR__ . '/../src/Categorizer.php')) throw new Exception("Fichier Categorizer.php introuvable");
    require_once __DIR__ . '/../src/Categorizer.php';
} catch (Exception $e) {
    die("<p style='color:red; font-weight:bold'>Erreur fatale de chargement : " . $e->getMessage() . "</p>");
}

echo "<p>✅ Fichiers chargés. Connexion DB...</p>";

$pdo = Database::getConnection();
$categorizer = new Categorizer();

echo "<h1>🛠 Opération de Catégorisation Rétroactive</h1>";

try {
    // 4. On cherche toutes les transactions qui n'ont PAS de ligne dans transaction_splits
    $sql = "
        SELECT t.id, t.raw_label, t.amount, t.account_id 
        FROM transactions t 
        LEFT JOIN transaction_splits ts ON t.id = ts.transaction_id 
        WHERE ts.id IS NULL
    ";
    
    $stmt = $pdo->query($sql);
    $transactions = $stmt->fetchAll();
    
    $count = 0;
    
    if (empty($transactions)) {
        echo "<p>✅ Tout est déjà catégorisé ! Rien à faire.</p>";
    } else {
        echo "<p>🔍 " . count($transactions) . " transactions non catégorisées trouvées.</p>";
        echo "<ul>";
        
        foreach ($transactions as $tx) {
            // On lance le moteur pour chaque transaction orpheline
            $categorizer->process($tx['id'], $tx['raw_label'], $tx['amount'], $tx['account_id']);
            
            // Petit affichage de log
            echo "<li>Traitement ID #{$tx['id']} : <em>" . htmlspecialchars($tx['raw_label']) . "</em></li>";
            $count++;
        }
        
        echo "</ul>";
        echo "<h3>🎉 Terminé ! $count transactions mises à jour.</h3>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Erreur SQL/Logique : " . $e->getMessage() . "</p>";
}

echo '<br><a href="/" style="background: #3298dc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Retour au Dashboard</a>';