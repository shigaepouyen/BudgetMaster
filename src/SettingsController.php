<?php
// src/SettingsController.php

require_once 'Database.php';
require_once 'Categorizer.php';

class SettingsController {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function index() {
        // Récupération des catégories triées par nom
        $categories = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
        
        // Récupération des règles avec jointure pour afficher le nom de la catégorie
        $rules = $this->pdo->query("
            SELECT r.*, c.name as category_name 
            FROM category_rules r 
            JOIN categories c ON r.category_id = c.id 
            ORDER BY r.id DESC
        ")->fetchAll();
        
        require __DIR__ . '/../templates/settings.php';
    }

    // --- GESTION DES RÈGLES ---

    public function addRule() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['pattern']) || empty($input['category_id'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO category_rules (pattern, category_id, exclusion, amount_match, custom_label, is_recurring) VALUES (?, ?, ?, ?, ?, ?)");
            
            // Préparation des valeurs nullables ou typées
            $exclusion = !empty($input['exclusion']) ? $input['exclusion'] : null;
            $amountMatch = (isset($input['amount_match']) && $input['amount_match'] !== '') ? (float)$input['amount_match'] : null;
            $customLabel = !empty($input['custom_label']) ? $input['custom_label'] : null;
            $isRecurring = !empty($input['is_recurring']) ? 1 : 0;

            // Insertion en base
            $stmt->execute([$input['pattern'], $input['category_id'], $exclusion, $amountMatch, $customLabel, $isRecurring]);
            
            // Propagation Immédiate : On applique la nouvelle règle à tout l'historique
            $categorizer = new Categorizer();
            $count = $categorizer->applyRuleToAll(
                $input['pattern'], 
                $exclusion, 
                $amountMatch, 
                (int)$input['category_id'], 
                $customLabel, 
                $isRecurring
            );

            echo json_encode(['success' => true, 'updated_count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updateRule() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id']) || empty($input['pattern']) || empty($input['category_id'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE category_rules SET pattern = ?, exclusion = ?, amount_match = ?, custom_label = ?, category_id = ?, is_recurring = ? WHERE id = ?");
            
            $exclusion = !empty($input['exclusion']) ? $input['exclusion'] : null;
            $amountMatch = (isset($input['amount_match']) && $input['amount_match'] !== '') ? (float)$input['amount_match'] : null;
            $customLabel = !empty($input['custom_label']) ? $input['custom_label'] : null;
            $isRecurring = !empty($input['is_recurring']) ? 1 : 0;

            $stmt->execute([$input['pattern'], $exclusion, $amountMatch, $customLabel, $input['category_id'], $isRecurring, $input['id']]);
            
            // Propagation Immédiate lors de la modification aussi
            $categorizer = new Categorizer();
            $count = $categorizer->applyRuleToAll(
                $input['pattern'], 
                $exclusion, 
                $amountMatch, 
                (int)$input['category_id'], 
                $customLabel, 
                $isRecurring
            );

            echo json_encode(['success' => true, 'updated_count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteRule() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID manquant']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM category_rules WHERE id = ?");
            $stmt->execute([$input['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // --- GESTION DES CATÉGORIES ---

    public function addCategory() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name'])) {
            echo json_encode(['success' => false, 'message' => 'Nom vide']);
            return;
        }

        try {
            // Vérification doublon
            $stmtCheck = $this->pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmtCheck->execute([$input['name']]);
            if ($stmtCheck->fetchColumn()) {
                throw new Exception("Cette catégorie existe déjà.");
            }

            // Récupération du type (EXPENSE par défaut)
            $type = !empty($input['type']) && in_array($input['type'], ['EXPENSE', 'INCOME', 'TRANSFER']) ? $input['type'] : 'EXPENSE';

            $stmt = $this->pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
            $stmt->execute([$input['name'], $type]);
            
            echo json_encode([
                'success' => true, 
                'category' => ['id' => $this->pdo->lastInsertId(), 'name' => $input['name']]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updateCategory() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id']) || empty($input['name'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            return;
        }

        try {
            // Mise à jour Nom et Type
            $sql = "UPDATE categories SET name = ?";
            $params = [$input['name']];

            if (!empty($input['type']) && in_array($input['type'], ['EXPENSE', 'INCOME', 'TRANSFER'])) {
                $sql .= ", type = ?";
                $params[] = $input['type'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $input['id'];

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}