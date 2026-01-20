<?php
// src/Categorizer.php

require_once 'Database.php';

class Categorizer {
    private $pdo;
    private $rules = [];

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->loadRules();
    }

    /**
     * Charge les règles en mémoire.
     * AMÉLIORATION : Tri par longueur décroissante (CHAR_LENGTH DESC).
     * Cela garantit que les règles les plus spécifiques (ex: "AMAZON PRIME")
     * sont testées AVANT les règles génériques (ex: "AMAZON").
     */
    private function loadRules() {
        try {
            // On récupère 'custom_label' pour les alias
            $stmt = $this->pdo->query("
                SELECT pattern, category_id, exclusion, amount_match, custom_label 
                FROM category_rules 
                ORDER BY CHAR_LENGTH(pattern) DESC
            ");
            $this->rules = $stmt->fetchAll();
        } catch (Exception $e) {
            $this->rules = [];
        }
    }

    /**
     * Nettoie le libellé pour enlever le bruit (X1234, Dates, Paiement par carte...)
     * C'est la clé pour une catégorisation fiable !
     */
    private function normalizeLabel(string $rawLabel): string {
        $label = mb_strtoupper($rawLabel);

        // 1. Supprimer "PAIEMENT PAR CARTE" et "PRLV"
        $label = str_replace(['PAIEMENT PAR CARTE', 'PRELEVEMENT'], '', $label);

        // 2. Supprimer les motifs type "X1234" ou "X 1234" (Référence carte)
        // Regex : Lettre X suivie de 2 à 5 chiffres, avec ou sans espace avant
        $label = preg_replace('/\bX\s?\d{2,5}\b/', '', $label);

        // 3. Supprimer les dates format JJ/MM ou JJ/MM/AA
        $label = preg_replace('/\d{2}\/\d{2}(\/\d{2,4})?/', '', $label);

        // 4. Supprimer les espaces multiples et trim
        return trim(preg_replace('/\s+/', ' ', $label));
    }

    /**
     * Analyse et catégorise une transaction (Processus principal à l'import)
     */
    public function process(int $transactionId, string $label, float $amount, int $accountId) {
        $categoryId = 8; // Inconnu
        $cleanLabel = $this->normalizeLabel($label); 
        $comment = null; // Pour le libellé personnalisé

        // 1. Règles strictes
        foreach ($this->rules as $rule) {
            // On cherche le pattern dans le libellé nettoyé
            if (strpos($cleanLabel, mb_strtoupper($rule['pattern'])) !== false) {
                
                // Vérification Exclusion
                if (!empty($rule['exclusion']) && strpos($cleanLabel, mb_strtoupper($rule['exclusion'])) !== false) {
                    continue; // Le mot exclu est présent, on saute cette règle !
                }

                // Vérification Montant (Si défini dans la règle)
                // On compare les valeurs absolues pour gérer les débits (-) et crédits (+) sans souci
                if ($rule['amount_match'] !== null) {
                    // On accepte une petite marge d'erreur flottante (0.001)
                    if (abs(abs($amount) - abs((float)$rule['amount_match'])) > 0.001) {
                        continue; // Le montant ne correspond pas, règle suivante !
                    }
                }
                
                // Si on arrive ici, c'est que tout colle
                $categoryId = $rule['category_id'];
                $comment = $rule['custom_label']; // On capture l'alias (ex: "Spotify")
                break;
            }
        }

        // 2. Apprentissage Historique (Si aucune règle n'a matché)
        if ($categoryId === 8) {
            $historyCatId = $this->guessCategoryFromHistory($cleanLabel);
            if ($historyCatId) {
                $categoryId = $historyCatId;
            }
        }

        $userId = 1; // Par défaut "Moi"

        // Insertion du split avec le commentaire éventuel
        $stmt = $this->pdo->prepare("
            INSERT INTO transaction_splits (transaction_id, user_id, category_id, amount, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$transactionId, $userId, $categoryId, $amount, $comment]);
    }

    /**
     * Applique une règle spécifique à TOUT l'historique des transactions.
     * Cette méthode est appelée juste après la création/modification d'une règle.
     * Elle écrase les catégorisations existantes (car la règle est prioritaire).
     */
    public function applyRuleToAll(string $pattern, ?string $exclusion, ?float $amountMatch, int $categoryId, ?string $customLabel): int {
        // Mise à jour massive incluant le commentaire
        $sql = "
            UPDATE transaction_splits ts
            JOIN transactions t ON ts.transaction_id = t.id
            SET ts.category_id = ?, ts.comment = ?
            WHERE t.raw_label LIKE ?
        ";
        
        $params = [$categoryId, $customLabel, '%' . $pattern . '%'];

        // Gestion de l'exclusion
        if (!empty($exclusion)) {
            $sql .= " AND t.raw_label NOT LIKE ?";
            $params[] = '%' . $exclusion . '%';
        }

        // Critère Montant
        if ($amountMatch !== null) {
            // On cherche le montant exact (positif ou négatif) ou son opposé
            $sql .= " AND (ABS(t.amount) = ABS(?))";
            $params[] = $amountMatch;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Propage la mise à jour aux transactions similaires (méthode manuelle).
     * Utilise aussi le nettoyage pour trouver les correspondances même si le X1234 change.
     */
    public function applyToSimilar(int $sourceTransactionId, int $newCategoryId): int {
        // 1. Récupérer le libellé source
        $stmt = $this->pdo->prepare("SELECT raw_label FROM transactions WHERE id = ?");
        $stmt->execute([$sourceTransactionId]);
        $label = $stmt->fetchColumn();

        if (!$label) return 0;

        // 2. On nettoie le libellé source pour avoir la "racine" du commerçant
        $cleanLabel = $this->normalizeLabel($label);
        
        // On prend les 10 premiers caractères significatifs du nom nettoyé
        $prefix = substr($cleanLabel, 0, 10);
        
        if (strlen($prefix) < 3) return 0; // Trop court, dangereux

        // 3. Mise à jour de masse SÉCURISÉE (Uniquement les ID 8)
        $sql = "
            UPDATE transaction_splits ts
            JOIN transactions t ON ts.transaction_id = t.id
            SET ts.category_id = ?
            WHERE t.raw_label LIKE ? 
              AND ts.category_id = 8 -- <--- LA SÉCURITÉ EST ICI
              AND t.id != ?          -- On ne compte pas la source
        ";

        try {
            $stmtUpd = $this->pdo->prepare($sql);
            $stmtUpd->execute([$newCategoryId, '%' . $prefix . '%', $sourceTransactionId]);
            return $stmtUpd->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Cherche la catégorie la plus probable basée sur l'historique des transactions passées.
     */
    private function guessCategoryFromHistory(string $cleanLabel): ?int {
        // On utilise le libellé déjà nettoyé par process()
        $prefix = substr($cleanLabel, 0, 15); 
        
        if (strlen($prefix) < 4) return null;

        // On cherche dans l'historique des transactions qui contiennent ce préfixe propre
        $sql = "
            SELECT ts.category_id, COUNT(*) as frequency
            FROM transactions t
            JOIN transaction_splits ts ON t.id = ts.transaction_id
            WHERE t.raw_label LIKE ? 
              AND ts.category_id != 8 
            GROUP BY ts.category_id
            ORDER BY frequency DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['%' . $prefix . '%']); // On cherche le mot clé n'importe où
            $result = $stmt->fetchColumn();
            return $result ? (int)$result : null;
        } catch (Exception $e) {
            return null;
        }
    }
}