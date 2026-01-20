<?php
// src/BudgetService.php

require_once 'Database.php';

class BudgetService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    /**
     * Récupère le tableau de bord budgétaire pour un mois et un utilisateur donnés.
     */
    public function getBudgetStatus(int $userId, int $month, int $year) {
        // On récupère toutes les catégories de type EXPENSE
        // On joint le budget défini (si existe)
        // On joint la somme des dépenses réelles (transaction_splits)
        
        $sql = "
            SELECT 
                c.id as category_id, 
                c.name as category_name,
                COALESCE(b.amount, 0) as budget_goal,
                COALESCE(SUM(ts.amount), 0) as spent_amount
            FROM categories c
            -- Jointure Budget (pour l'utilisateur ciblé)
            LEFT JOIN budgets b ON c.id = b.category_id AND b.user_id = ?
            -- Jointure Dépenses Réelles (pour le mois/année et utilisateur ciblés)
            LEFT JOIN transaction_splits ts ON c.id = ts.category_id 
                AND ts.user_id = ?
                AND ts.transaction_id IN (
                    SELECT id FROM transactions 
                    WHERE MONTH(date_booked) = ? AND YEAR(date_booked) = ?
                )
            WHERE c.type = 'EXPENSE'
            GROUP BY c.id, c.name, b.amount
            ORDER BY spent_amount ASC -- Les plus grosses dépenses en premier (négatif)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $month, $year]);
        $rows = $stmt->fetchAll();

        $budgets = [];
        $totals = ['goal' => 0, 'spent' => 0];

        foreach ($rows as $row) {
            $spent = abs($row['spent_amount']); // On travaille en positif pour l'affichage
            $goal = (float)$row['budget_goal'];
            
            // Calcul pourcentage
            $percent = 0;
            if ($goal > 0) {
                $percent = ($spent / $goal) * 100;
            } elseif ($spent > 0) {
                $percent = 100; // Pas de budget mais dépense = 100% (alerte)
            }

            // Détermination couleur
            $color = 'is-success';
            if ($percent >= 80) $color = 'is-warning';
            if ($percent >= 100) $color = 'is-danger';
            if ($goal == 0 && $spent > 0) $color = 'is-danger'; // Dépense hors budget

            $budgets[] = [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'goal' => $goal,
                'spent' => $spent,
                'remaining' => max(0, $goal - $spent),
                'percent' => min(100, $percent), // Cap pour la barre CSS
                'raw_percent' => $percent,
                'color' => $color
            ];

            $totals['goal'] += $goal;
            $totals['spent'] += $spent;
        }

        return ['items' => $budgets, 'totals' => $totals];
    }

    /**
     * Sauvegarde ou met à jour un budget
     */
    public function setBudget(int $userId, int $categoryId, float $amount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO budgets (user_id, category_id, amount) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount)
        ");
        $stmt->execute([$userId, $categoryId, $amount]);
    }
}