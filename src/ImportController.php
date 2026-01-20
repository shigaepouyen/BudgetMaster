<?php
// src/ImportController.php

require_once 'Database.php';
require_once 'OfxParser.php';
require_once 'Categorizer.php';

class ImportController {
    
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
        // Auto-Migration pour renommer l'utilisateur par défaut
        try { 
            $this->pdo->exec("UPDATE users SET name = 'Compte-Joint' WHERE name = 'Conjoint'"); 
        } catch (Exception $e) { }
    }

    public function handleUpload() {
        $message = '';
        
        // --- 1. LOGIQUE D'UPLOAD ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ofx_file'])) {
            try {
                $file = $_FILES['ofx_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Erreur d'upload (Code " . $file['error'] . ")");
                }

                $parser = new OfxParser();
                $transactions = $parser->parse($file['tmp_name']);
                $categorizer = new Categorizer();

                $this->pdo->beginTransaction();

                // Préparation des requêtes SQL
                $stmtAcc = $this->pdo->prepare("SELECT id FROM accounts WHERE account_number = ?");
                $stmtInsAcc = $this->pdo->prepare("INSERT INTO accounts (account_number, name, type, owner_id) VALUES (?, 'Nouveau Compte', 'PERSONAL', 1)");
                
                // Insertion avec mise à jour du compte en cas de doublon (ON DUPLICATE KEY UPDATE)
                $stmtTx = $this->pdo->prepare("
                    INSERT INTO transactions (account_id, fitid, date_booked, raw_label, amount) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE account_id = VALUES(account_id)
                ");
                
                $stmtGetTxId = $this->pdo->prepare("SELECT id FROM transactions WHERE fitid = ?");
                $stmtCheckSplit = $this->pdo->prepare("SELECT COUNT(*) FROM transaction_splits WHERE transaction_id = ?");

                $countNew = 0; 
                $countUpdated = 0;

                foreach ($transactions as $tx) {
                    // A. Gestion du Compte
                    $stmtAcc->execute([$tx['account_number']]);
                    $accId = $stmtAcc->fetchColumn();

                    if (!$accId) {
                        $stmtInsAcc->execute([$tx['account_number']]);
                        $accId = $this->pdo->lastInsertId();
                    }

                    // B. Insertion / Mise à jour Transaction
                    $stmtTx->execute([
                        $accId,
                        $tx['fitid'],
                        $tx['date'],
                        $tx['label'],
                        $tx['amount']
                    ]);
                    
                    // Récupération ID transaction
                    $stmtGetTxId->execute([$tx['fitid']]); 
                    $txId = $stmtGetTxId->fetchColumn();

                    // C. Catégorisation (Rattrapage si manquant)
                    $stmtCheckSplit->execute([$txId]);
                    if ($stmtCheckSplit->fetchColumn() == 0) {
                        $categorizer->process($txId, $tx['label'], $tx['amount'], $accId);
                        $countNew++; 
                    } else { 
                        $countUpdated++; 
                    }
                }

                $this->pdo->commit();
                $message = "✅ Succès ! $countNew nouvelles, $countUpdated vérifiées.";

            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $message = "❌ Erreur : " . $e->getMessage();
            }
        }

        // --- 2. GESTION VUES & FILTRES ---
        $currentView = $_GET['view'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $categoryFilter = $_GET['category'] ?? '';
        $dateStart = $_GET['date_start'] ?? '';
        $dateEnd = $_GET['date_end'] ?? '';
        $amountMin = $_GET['amount_min'] ?? '';
        $amountMax = $_GET['amount_max'] ?? '';
        
        $userId = 1; // ID "Moi" par défaut

        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // --- 3. RÉCUPÉRATION DONNÉES AUXILIAIRES ---
        $accounts = $this->pdo->query("SELECT * FROM accounts")->fetchAll();
        $categories = []; try { $categories = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}
        $users = []; try { $users = $this->pdo->query("SELECT * FROM users")->fetchAll(); } catch (Exception $e) {}

        // --- 4. CONSTRUCTION DE LA REQUÊTE (Filtres Intelligents) ---
        $whereClause = "WHERE 1=1";
        $queryParams = [];

        // Filtre Vue (Moi / Famille)
        if ($currentView === 'mine') {
            $whereClause .= " AND a.owner_id = ?";
            $queryParams[] = $userId;
        }

        // Filtre Recherche (Multi-Mots)
        if (!empty($search)) {
            // Découpage par espaces pour chercher chaque mot indépendamment
            $keywords = array_filter(explode(' ', trim($search)));
            foreach ($keywords as $word) {
                $whereClause .= " AND t.raw_label LIKE ?";
                $queryParams[] = '%' . $word . '%';
            }
        }

        // Filtre Catégorie
        if (!empty($categoryFilter)) {
            $whereClause .= " AND c.id = ?";
            $queryParams[] = $categoryFilter;
        }

        // Filtre Date Début
        if (!empty($dateStart)) {
            $whereClause .= " AND t.date_booked >= ?";
            $queryParams[] = $dateStart;
        }

        // Filtre Date Fin
        if (!empty($dateEnd)) {
            $whereClause .= " AND t.date_booked <= ?";
            $queryParams[] = $dateEnd;
        }

        // Filtre Montant Min
        if ($amountMin !== '') {
            $whereClause .= " AND t.amount >= ?";
            $queryParams[] = $amountMin;
        }

        // Filtre Montant Max
        if ($amountMax !== '') {
            $whereClause .= " AND t.amount <= ?";
            $queryParams[] = $amountMax;
        }

        // --- 5. EXÉCUTION DES REQUÊTES ---

        // A. Compter le total (pour la pagination)
        $sqlCount = "
            SELECT COUNT(*) 
            FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            LEFT JOIN transaction_splits ts ON t.id = ts.transaction_id
            LEFT JOIN categories c ON ts.category_id = c.id
            $whereClause
        ";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($queryParams);
        $totalTransactions = $stmtCount->fetchColumn();
        $totalPages = ceil($totalTransactions / $perPage);

        // B. Récupérer la liste paginée
        $sqlList = "
            SELECT 
                t.id, t.date_booked, t.raw_label, t.amount,
                a.name as account_name, a.type as account_type,
                c.name as category_name, c.id as category_id,
                ts.comment as custom_comment
            FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            LEFT JOIN transaction_splits ts ON t.id = ts.transaction_id
            LEFT JOIN categories c ON ts.category_id = c.id
            $whereClause
            ORDER BY t.date_booked DESC, t.id DESC 
            LIMIT $perPage OFFSET $offset
        ";

        try {
            $stmtList = $this->pdo->prepare($sqlList);
            $stmtList->execute($queryParams);
            $recentTransactions = $stmtList->fetchAll();
        } catch (Exception $e) {
            $recentTransactions = [];
            $message = "❌ Erreur d'affichage : " . $e->getMessage();
        }

        // --- 6. STATISTIQUES (Adaptatives selon filtres) ---
        $stats = []; 
        $totalExpenses = 0; 
        $monthLabel = '';

        try {
            // Base de la requête stats
            $sqlStats = "
                SELECT c.name, c.id, SUM(ts.amount) as total
                FROM transaction_splits ts
                JOIN transactions t ON ts.transaction_id = t.id
                JOIN accounts a ON t.account_id = a.id
                JOIN categories c ON ts.category_id = c.id
                WHERE c.type = 'EXPENSE'
            ";
            
            // On clone les paramètres car on va peut-être en ajouter pour les stats
            $paramsStats = [];

            // Est-ce qu'on a des filtres actifs ? (Recherche, Catégorie, Dates...)
            $hasFilters = !empty($search) || !empty($categoryFilter) || !empty($dateStart) || !empty($dateEnd) || $amountMin !== '' || $amountMax !== '';

            if ($hasFilters) {
                // Mode "Filtre Actif" : On réutilise la clause WHERE construite plus haut
                // Attention : $whereClause commence par "WHERE 1=1", on doit l'adapter pour la requête stats qui a déjà un WHERE
                // On remplace "WHERE" par "AND" pour l'ajouter à la suite
                $filterConditions = str_replace('WHERE', 'AND', $whereClause);
                
                $sqlStats .= $filterConditions;
                $paramsStats = $queryParams; // On reprend les mêmes paramètres

                $monthLabel = "Sélection personnalisée";
            } else {
                // Mode "Défaut" : On prend le dernier mois actif en base
                $stmtLastDate = $this->pdo->query("SELECT MAX(date_booked) FROM transactions");
                $lastDate = $stmtLastDate->fetchColumn();
                
                if ($lastDate) {
                    $dt = new DateTime($lastDate);
                    $sqlStats .= " AND MONTH(t.date_booked) = ? AND YEAR(t.date_booked) = ? ";
                    $paramsStats[] = $dt->format('m');
                    $paramsStats[] = $dt->format('Y');
                    
                    // On applique quand même le filtre de vue (Moi/Famille) si présent
                    if ($currentView === 'mine') {
                        $sqlStats .= " AND a.owner_id = ? ";
                        $paramsStats[] = $userId;
                    }

                    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE);
                    $formatter->setPattern('MMMM yyyy');
                    $monthLabel = ucfirst($formatter->format($dt));
                }
            }

            $sqlStats .= " GROUP BY c.id HAVING total < 0 ORDER BY total ASC";

            $stmtStats = $this->pdo->prepare($sqlStats);
            $stmtStats->execute($paramsStats);
            $stats = $stmtStats->fetchAll();
            
            foreach ($stats as $row) {
                $totalExpenses += $row['total'];
            }
        } catch (Exception $e) {
            // Stats vides si erreur
        }

        // Chargement de la vue
        require __DIR__ . '/../templates/dashboard.php';
    }
}