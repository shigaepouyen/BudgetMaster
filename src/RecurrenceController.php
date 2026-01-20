<?php
// src/RecurrenceController.php

require_once 'RecurrenceService.php';

class RecurrenceController {
    
    public function index() {
        $service = new RecurrenceService();
        $recurrences = $service->getRecurrencesReport();
        $stats = $service->getStatsForGraph();
        
        $totalMonthly = $stats['monthly_fixed'];
        $totalYearly = $totalMonthly * 12;

        require __DIR__ . '/../templates/recurrence.php';
    }

    // Action AJAX pour Valider/Ignorer (Update Status)
    public function updateStatus() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['signature']) || empty($input['amount'])) {
            echo json_encode(['success' => false]); return;
        }

        $service = new RecurrenceService();
        $service->toggleRecurrenceStatus(
            $input['signature'], 
            $input['amount'], 
            $input['frequency'], 
            $input['status'] 
        );

        echo json_encode(['success' => true]);
    }

    // NOUVEAU : Action AJAX pour Supprimer définitivement
    public function delete() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['signature']) || empty($input['amount'])) {
            echo json_encode(['success' => false]); return;
        }

        $service = new RecurrenceService();
        $service->deleteRecurrence($input['signature'], $input['amount']);

        echo json_encode(['success' => true]);
    }
}