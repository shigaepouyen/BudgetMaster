#!/bin/bash

# Arrêt sur erreur
set -e

echo "🚀 Passage à la Phase 2 : Catégorisation..."

# 1. Mise à jour SQL
echo "📦 Mise à jour de la base de données..."
# On crée le fichier SQL temporaire
cat << 'EOF' > update_phase2.sql
CREATE TABLE IF NOT EXISTS category_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaction_splits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    user_id INT NOT NULL,
    category_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (id, name, type) VALUES 
(1, 'Alimentation', 'EXPENSE'),
(2, 'Logement', 'EXPENSE'),
(3, 'Transport', 'EXPENSE'),
(4, 'Loisirs', 'EXPENSE'),
(5, 'Santé', 'EXPENSE'),
(6, 'Salaire', 'INCOME'),
(7, 'Virements Internes', 'TRANSFER'),
(8, 'Inconnu', 'EXPENSE');

INSERT IGNORE INTO category_rules (pattern, category_id) VALUES 
('CARREFOUR', 1), ('LECLERC', 1), ('AUCHAN', 1), ('INTERMARCHE', 1), ('LIDL', 1), ('MONOPRIX', 1),
('EDF', 2), ('ENGIE', 2), ('LOYER', 2),
('TOTAL', 3), ('SHELL', 3), ('SNCF', 3), ('PEAGE', 3),
('NETFLIX', 4), ('CINEMA', 4), ('SPOTIFY', 4),
('DOCTOLIB', 5), ('PHARMACIE', 5),
('VIR', 7);
EOF

# Exécution SQL (Version Homebrew)
mysql -u root budget_master < update_phase2.sql
rm update_phase2.sql

# 2. Création Categorizer.php
echo "📝 Création de src/Categorizer.php..."
cat << 'EOF' > src/Categorizer.php
<?php
require_once 'Database.php';

class Categorizer {
    private $pdo;
    private $rules = [];

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->loadRules();
    }

    private function loadRules() {
        try {
            $stmt = $this->pdo->query("SELECT pattern, category_id FROM category_rules");
            $this->rules = $stmt->fetchAll();
        } catch (Exception $e) { $this->rules = []; }
    }

    public function process(int $transactionId, string $label, float $amount, int $accountId) {
        // ID 8 = "Inconnu" par défaut
        $categoryId = 8;
        $cleanLabel = mb_strtoupper($label);

        foreach ($this->rules as $rule) {
            if (strpos($cleanLabel, mb_strtoupper($rule['pattern'])) !== false) {
                $categoryId = $rule['category_id'];
                break;
            }
        }

        // Par défaut User 1 ("Moi")
        $userId = 1;

        $stmt = $this->pdo->prepare("INSERT INTO transaction_splits (transaction_id, user_id, category_id, amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$transactionId, $userId, $categoryId, $amount]);
    }
}
EOF

# 3. Update ImportController.php
echo "📝 Mise à jour de src/ImportController.php..."
cat << 'EOF' > src/ImportController.php
<?php
require_once 'Database.php';
require_once 'OfxParser.php';
require_once 'Categorizer.php';

class ImportController {
    private $pdo;
    public function __construct() { $this->pdo = Database::getConnection(); }

    public function handleUpload() {
        $message = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ofx_file'])) {
            try {
                $file = $_FILES['ofx_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Erreur upload code " . $file['error']);

                $parser = new OfxParser();
                $transactions = $parser->parse($file['tmp_name']);
                $categorizer = new Categorizer();

                $this->pdo->beginTransaction();
                $stmtAcc = $this->pdo->prepare("SELECT id FROM accounts WHERE account_number = ?");
                $stmtInsAcc = $this->pdo->prepare("INSERT INTO accounts (account_number, name, type) VALUES (?, 'Nouveau Compte', 'PERSONAL')");
                $stmtTx = $this->pdo->prepare("INSERT IGNORE INTO transactions (account_id, fitid, date_booked, raw_label, amount) VALUES (?, ?, ?, ?, ?)");
                $stmtGetTxId = $this->pdo->prepare("SELECT id FROM transactions WHERE fitid = ?");

                $count = 0;
                foreach ($transactions as $tx) {
                    $stmtAcc->execute([$tx['account_number']]);
                    $accId = $stmtAcc->fetchColumn();
                    if (!$accId) {
                        $stmtInsAcc->execute([$tx['account_number']]);
                        $accId = $this->pdo->lastInsertId();
                    }

                    $stmtTx->execute([$accId, $tx['fitid'], $tx['date'], $tx['label'], $tx['amount']]);
                    $txId = $this->pdo->lastInsertId();
                    
                    if ($txId == 0) {
                        // Transaction existe déjà
                    } else {
                        // Nouvelle transaction -> Catégorisation
                        $categorizer->process($txId, $tx['label'], $tx['amount'], $accId);
                        $count++;
                    }
                }
                $this->pdo->commit();
                $message = "✅ Succès ! $count transactions importées et catégorisées.";
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $message = "❌ Erreur : " . $e->getMessage();
            }
        }

        // Récupération avec jointure catégories
        $sql = "SELECT t.*, a.name as account_name, c.name as category_name, c.id as category_id
                FROM transactions t 
                JOIN accounts a ON t.account_id = a.id 
                LEFT JOIN transaction_splits ts ON t.id = ts.transaction_id
                LEFT JOIN categories c ON ts.category_id = c.id
                GROUP BY t.id ORDER BY t.date_booked DESC LIMIT 50";
        
        try {
            $recentTransactions = $this->pdo->query($sql)->fetchAll();
        } catch (Exception $e) { $recentTransactions = []; }

        require __DIR__ . '/../templates/dashboard.php';
    }
}
EOF

# 4. Update Dashboard
echo "📝 Mise à jour de templates/dashboard.php..."
cat << 'EOF' > templates/dashboard.php
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BudgetMaster CA - Phase 2</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cat-badge-1 { background-color: #ffe08a; color: #000; } /* Alimentation */
        .cat-badge-2 { background-color: #b5e3ff; color: #000; } /* Logement */
        .cat-badge-3 { background-color: #ffcccc; color: #000; } /* Transport */
        .cat-badge-6 { background-color: #48c774; color: #fff; } /* Salaire */
        .cat-badge-8 { background-color: #e5e5e5; color: #7a7a7a; } /* Inconnu */
    </style>
</head>
<body class="has-background-light">
    <nav class="navbar is-info"><div class="navbar-brand"><a class="navbar-item has-text-weight-bold" href="/">BudgetMaster CA</a></div></nav>
    <div class="container mt-5">
        <?php if (!empty($message)): ?>
            <div class="notification <?php echo strpos($message, '❌') !== false ? 'is-danger' : 'is-success'; ?>">
                <button class="delete" onclick="this.parentElement.remove()"></button>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="columns">
            <div class="column is-one-quarter">
                <div class="box">
                    <h1 class="title is-5"><i class="fas fa-file-import"></i> Import OFX</h1>
                    <form action="/" method="POST" enctype="multipart/form-data">
                        <div class="file has-name is-info is-fullwidth mb-3">
                            <label class="file-label">
                                <input class="file-input" type="file" name="ofx_file" accept=".ofx">
                                <span class="file-cta"><i class="fas fa-upload"></i></span>
                                <span class="file-name">Fichier...</span>
                            </label>
                        </div>
                        <button type="submit" class="button is-primary is-fullwidth">Importer & Catégoriser</button>
                    </form>
                    <p class="mt-3 is-size-7">Catégorisation auto active.</p>
                </div>
            </div>
            <div class="column">
                <div class="box">
                    <h2 class="title is-5">Journal des Opérations</h2>
                    <table class="table is-striped is-fullwidth is-size-7">
                        <thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th class="has-text-right">Montant</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr><td colspan="4" class="has-text-centered">Aucune donnée.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d/m/y', strtotime($tx['date_booked'])); ?></td>
                                        <td><span class="tag cat-badge-<?php echo $tx['category_id'] ?? 8; ?> is-light"><?php echo htmlspecialchars($tx['category_name'] ?? 'Inconnu'); ?></span></td>
                                        <td title="<?php echo htmlspecialchars($tx['raw_label']); ?>">
                                            <?php echo htmlspecialchars(substr($tx['raw_label'], 0, 50)) . (strlen($tx['raw_label'])>50?'...':''); ?>
                                        </td>
                                        <td class="has-text-right has-text-weight-bold <?php echo $tx['amount'] < 0 ? 'has-text-danger' : 'has-text-success'; ?>">
                                            <?php echo number_format($tx['amount'], 2, ',', ' '); ?> €
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        const fileInput = document.querySelector('.file-input');
        if(fileInput) fileInput.onchange = () => { if(fileInput.files.length > 0) document.querySelector('.file-name').textContent = fileInput.files[0].name; }
    </script>
</body>
</html>
EOF

echo "✅ Phase 2 installée avec succès !"