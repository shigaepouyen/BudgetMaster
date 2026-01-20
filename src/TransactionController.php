<?php
// src/TransactionController.php

require_once 'Database.php';
require_once 'Categorizer.php';

class TransactionController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    // ... (updateCategory, addCategory, updateAccount, resetData inchangés) ...
    public function updateCategory() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['transaction_id']) || !isset($input['category_id'])) { echo json_encode(['success' => false, 'message' => 'Données manquantes']); return; }
        try {
            $stmtCheck = $this->pdo->prepare("SELECT id FROM transaction_splits WHERE transaction_id = ?");
            $stmtCheck->execute([$input['transaction_id']]);
            if ($stmtCheck->fetchColumn()) {
                $stmt = $this->pdo->prepare("UPDATE transaction_splits SET category_id = ? WHERE transaction_id = ?"); // Correction WHERE transaction_id
                $stmt->execute([$input['category_id'], $input['transaction_id']]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO transaction_splits (transaction_id, category_id, user_id, amount) VALUES (?, ?, 1, 0)");
                $stmt->execute([$input['transaction_id'], $input['category_id']]);
            }
            $categorizer = new Categorizer();
            $updatedCount = $categorizer->applyToSimilar((int)$input['transaction_id'], (int)$input['category_id']);
            echo json_encode(['success' => true, 'message' => 'Catégorie mise à jour', 'propagated_count' => $updatedCount]);
        } catch (Exception $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    }

    public function addCategory() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['name'])) { echo json_encode(['success' => false, 'message' => 'Nom vide']); return; }
        try {
            $stmtCheck = $this->pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmtCheck->execute([$input['name']]);
            if ($stmtCheck->fetchColumn()) throw new Exception("Cette catégorie existe déjà.");
            $stmt = $this->pdo->prepare("INSERT INTO categories (name, type) VALUES (?, 'EXPENSE')");
            $stmt->execute([$input['name']]);
            echo json_encode(['success' => true, 'category' => ['id' => $this->pdo->lastInsertId(), 'name' => $input['name']]]);
        } catch (Exception $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    }

    public function updateAccount() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['id'])) { echo json_encode(['success' => false, 'message' => 'ID manquant']); return; }
        try {
            $sql = "UPDATE accounts SET name = ?, type = ?, owner_id = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$input['name'], $input['type'], $input['owner_id'], $input['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    }

    public function resetData() {
        header('Content-Type: application/json');
        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("TRUNCATE TABLE transaction_splits");
            $this->pdo->exec("TRUNCATE TABLE transactions");
            $this->pdo->exec("TRUNCATE TABLE accounts");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    }

    /**
     * Mise à jour en masse HYBRIDE (Sélection manuelle OU Filtres globaux)
     */
    public function bulkUpdateCategory() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['category_id'])) {
            echo json_encode(['success' => false, 'message' => 'Catégorie manquante']); return;
        }

        try {
            $this->pdo->beginTransaction();

            // CAS 1 : Sélection globale via Filtres ("Tout sélectionner correspondant à la recherche")
            if (isset($input['apply_to_all_filters']) && $input['apply_to_all_filters'] === true) {
                
                $filters = $input['filters'] ?? [];
                
                // Reconstruction de la clause WHERE (similaire à ImportController)
                $whereClause = "WHERE 1=1";
                $queryParams = [$input['category_id']]; // Le premier paramètre est la nouvelle catégorie pour le SET

                // Filtre Vue (Moi / Famille)
                if (!empty($filters['view']) && $filters['view'] === 'mine') {
                    $whereClause .= " AND a.owner_id = 1"; // ID 1 = Moi
                }
                // Recherche
                if (!empty($filters['search'])) {
                    $whereClause .= " AND t.raw_label LIKE ?";
                    $queryParams[] = '%' . $filters['search'] . '%';
                }
                // Catégorie actuelle
                if (!empty($filters['category'])) {
                    $whereClause .= " AND ts.category_id = ?";
                    $queryParams[] = $filters['category'];
                }
                // Dates
                if (!empty($filters['date_start'])) {
                    $whereClause .= " AND t.date_booked >= ?";
                    $queryParams[] = $filters['date_start'];
                }
                if (!empty($filters['date_end'])) {
                    $whereClause .= " AND t.date_booked <= ?";
                    $queryParams[] = $filters['date_end'];
                }
                // Montants
                if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
                    $whereClause .= " AND t.amount >= ?";
                    $queryParams[] = $filters['amount_min'];
                }
                if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
                    $whereClause .= " AND t.amount <= ?";
                    $queryParams[] = $filters['amount_max'];
                }

                // Requête UPDATE massive avec Jointures
                // Note: On met à jour les splits. S'ils n'existent pas, c'est plus complexe en une requête.
                // Pour simplifier ici, on suppose que ImportController crée toujours un split (même inconnu) à l'import.
                // Si ce n'est pas le cas, il faudrait faire un INSERT SELECT avant.
                $sql = "
                    UPDATE transaction_splits ts
                    JOIN transactions t ON ts.transaction_id = t.id
                    JOIN accounts a ON t.account_id = a.id
                    SET ts.category_id = ?
                    $whereClause
                ";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($queryParams);
                
            } 
            // CAS 2 : Sélection manuelle d'IDs (Page en cours)
            else {
                if (empty($input['transaction_ids']) || !is_array($input['transaction_ids'])) {
                    throw new Exception("Aucune transaction sélectionnée");
                }

                $stmtCheck = $this->pdo->prepare("SELECT id FROM transaction_splits WHERE transaction_id = ?");
                $stmtUpdate = $this->pdo->prepare("UPDATE transaction_splits SET category_id = ? WHERE transaction_id = ?");
                $stmtInsert = $this->pdo->prepare("INSERT INTO transaction_splits (transaction_id, category_id, user_id, amount) VALUES (?, ?, 1, 0)");

                foreach ($input['transaction_ids'] as $txId) {
                    $stmtCheck->execute([$txId]);
                    if ($stmtCheck->fetchColumn()) {
                        $stmtUpdate->execute([$input['category_id'], $txId]);
                    } else {
                        $stmtInsert->execute([$txId, $input['category_id']]);
                    }
                }
            }

            $this->pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}