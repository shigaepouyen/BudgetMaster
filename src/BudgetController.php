<?php
// src/BudgetController.php

require_once 'BudgetService.php';

class BudgetController {
    
    public function index() {
        $userId = 1; // Par défaut "Moi". Amélioration future : dynamique selon la vue
        
        // Gestion de la date (Mois en cours par défaut)
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

        $service = new BudgetService();
        $data = $service->getBudgetStatus($userId, $month, $year);
        
        $budgets = $data['items'];
        $totals = $data['totals'];

        // Pour le sélecteur de date
        $currentDate = DateTime::createFromFormat('!m-Y', "$month-$year");
        $prevDate = clone $currentDate; $prevDate->modify('-1 month');
        $nextDate = clone $currentDate; $nextDate->modify('+1 month');

        require __DIR__ . '/../templates/budget.php';
    }

    public function update() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['category_id']) || !isset($input['amount'])) {
            echo json_encode(['success' => false]); return;
        }

        $userId = 1; // Par défaut "Moi"
        
        $service = new BudgetService();
        $service->setBudget($userId, (int)$input['category_id'], (float)$input['amount']);

        echo json_encode(['success' => true]);
    }
}