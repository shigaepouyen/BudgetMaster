<?php
// src/RecurrenceService.php

require_once 'Database.php';

class RecurrenceService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->initTable();
        $this->ensureRecurringColumnExists(); // Ajout de la vérification ici
    }

    private function initTable() {
        $sql = "CREATE TABLE IF NOT EXISTS recurrences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            signature VARCHAR(255) NOT NULL, 
            amount DECIMAL(10, 2) NOT NULL,
            frequency ENUM('Mensuel', 'Annuel', 'Trimestriel') NOT NULL,
            status ENUM('ACTIVE', 'IGNORED') NOT NULL DEFAULT 'ACTIVE',
            category_id INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rec (signature, amount)
        )";
        try { $this->pdo->exec($sql); } catch (Exception $e) {}
    }

    // NOUVEAU : Vérifie et ajoute la colonne is_recurring si manquante
    private function ensureRecurringColumnExists() {
        try {
            // On vérifie si la colonne existe dans la table category_rules
            $stmt = $this->pdo->query("SHOW COLUMNS FROM category_rules LIKE 'is_recurring'");
            if ($stmt->rowCount() === 0) {
                // Elle n'existe pas, on l'ajoute
                $this->pdo->exec("ALTER TABLE category_rules ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Exception $e) {
            // Ignore l'erreur si la table n'existe pas encore
        }
    }

    public function getRecurrencesReport() {
        // 1. Synchronisation des règles
        $this->syncRulesToRecurrences();

        // 2. Récupération du suivi
        $stmt = $this->pdo->query("SELECT * FROM recurrences");
        $savedRecurrences = $stmt->fetchAll();

        // 3. Analyse
        $stats = $this->analyzeTransactions();

        $finalList = [];

        foreach ($savedRecurrences as $saved) {
            $foundStat = null;
            foreach ($stats as $stat) {
                // Match exact sur la signature (Alias ou Pattern)
                if ($stat['signature'] === $saved['signature']) {
                    $foundStat = $stat;
                    break;
                }
            }

            $item = [
                'signature' => $saved['signature'],
                'amount' => (float)$saved['amount'],
                'frequency' => $saved['frequency'],
                'status' => $saved['status'],
                'db_id' => $saved['id']
            ];

            if ($foundStat) {
                $item['last_date'] = $foundStat['last_date'];
                $item['next_date'] = $foundStat['next_date'];
                $item['category_name'] = $foundStat['category_name'];
                if ($item['amount'] == 0) $item['amount'] = $foundStat['amount'];
            } else {
                $item['last_date'] = '-';
                $item['next_date'] = '-';
                $item['category_name'] = 'Réglage Manuel';
            }

            $finalList[] = $item;
        }
        
        usort($finalList, function($a, $b) { return $b['amount'] <=> $a['amount']; });
        return $finalList;
    }

    private function syncRulesToRecurrences() {
        try {
            // Récupère les règles récurrentes (la colonne existe forcément grâce au constructeur)
            $rules = $this->pdo->query("SELECT * FROM category_rules WHERE is_recurring = 1")->fetchAll();

            foreach ($rules as $rule) {
                $sig = !empty($rule['custom_label']) ? $rule['custom_label'] : $rule['pattern'];
                $amt = !empty($rule['amount_match']) ? $rule['amount_match'] : 0;
                
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO recurrences (signature, amount, frequency, status)
                    VALUES (?, ?, 'Mensuel', 'ACTIVE')
                ");
                $stmt->execute([$sig, $amt]);
            }
        } catch (Exception $e) {
            // Sécurité supplémentaire
        }
    }

    private function analyzeTransactions() {
        $sql = "
            SELECT t.date_booked, t.raw_label, ts.amount, c.name as category_name, ts.comment
            FROM transactions t
            JOIN transaction_splits ts ON t.id = ts.transaction_id
            JOIN categories c ON ts.category_id = c.id
            WHERE t.date_booked >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              AND c.type = 'EXPENSE'
            ORDER BY t.date_booked ASC
        ";
        $rawRows = $this->pdo->query($sql)->fetchAll();
        
        $groups = [];
        foreach ($rawRows as $row) {
            $sig = !empty($row['comment']) ? $row['comment'] : $this->normalizeLabel($row['raw_label']);
            if (!isset($groups[$sig])) {
                $groups[$sig] = ['dates' => [], 'amount_sum' => 0, 'count' => 0, 'cat' => $row['category_name']];
            }
            $groups[$sig]['dates'][] = strtotime($row['date_booked']);
            $groups[$sig]['amount_sum'] += (float)$row['amount'];
            $groups[$sig]['count']++;
        }

        $results = [];
        foreach ($groups as $sig => $data) {
            $count = $data['count'];
            $avgAmount = $count > 0 ? $data['amount_sum'] / $count : 0;
            // Cast int pour PHP 8.1
            $lastTs = (int)end($data['dates']);
            $nextTs = $lastTs + (30 * 86400);

            $results[] = [
                'signature' => $sig,
                'amount' => $avgAmount,
                'category_name' => $data['cat'],
                'last_date' => date('Y-m-d', $lastTs),
                'next_date' => date('Y-m-d', $nextTs)
            ];
        }
        return $results;
    }

    public function toggleRecurrenceStatus($s, $a, $f, $st) {
        $stmt = $this->pdo->prepare("INSERT INTO recurrences (signature, amount, frequency, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), frequency = VALUES(frequency)");
        $stmt->execute([$s, $a, $f, $st]);
    }

    // NOUVEAU : Supprimer une récurrence
    public function deleteRecurrence($signature, $amount) {
        // On supprime la ligne correspondante
        // Attention aux montants flottants, on utilise une petite marge
        $stmt = $this->pdo->prepare("DELETE FROM recurrences WHERE signature = ? AND ABS(amount - ?) < 0.01");
        $stmt->execute([$signature, $amount]);
        return $stmt->rowCount() > 0;
    }

    public function getStatsForGraph() {
        $stmt = $this->pdo->query("SELECT SUM(amount) as total, frequency FROM recurrences WHERE status = 'ACTIVE' GROUP BY frequency");
        $rows = $stmt->fetchAll(); $monthly = 0;
        foreach($rows as $r) { if($r['frequency']==='Mensuel')$monthly+=abs($r['total']); if($r['frequency']==='Annuel')$monthly+=abs($r['total'])/12; }
        return ['monthly_fixed' => $monthly];
    }

    private function normalizeLabel($l) { return preg_replace('/[^A-Z0-9 ]/', ' ', strtoupper($l)); }
}