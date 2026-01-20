#!/bin/bash

# Arrêter le script si une commande échoue
set -e

echo "🚀 Initialisation du projet BudgetMaster CA..."

# Vérification de PHP
if ! command -v php &> /dev/null; then
    echo "❌ Erreur : PHP n'est pas installé ou n'est pas dans le PATH."
    exit 1
fi

# 1. Création de la structure de dossiers
echo "📂 Création de l'arborescence..."
mkdir -p public/assets
mkdir -p src
mkdir -p templates
mkdir -p uploads

# Droits d'écriture pour le dossier d'upload
chmod 777 uploads

# 2. Création du fichier SQL
echo "📝 Création de database.sql..."
cat << 'EOF' > database.sql
-- Création de la base de données
-- CREATE DATABASE IF NOT EXISTS budget_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#CCCCCC'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('PERSONAL', 'JOINT') NOT NULL DEFAULT 'PERSONAL',
    owner_id INT NULL,
    current_balance DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type ENUM('EXPENSE', 'INCOME', 'TRANSFER') NOT NULL,
    parent_id INT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    fitid VARCHAR(255) UNIQUE,
    date_booked DATE NOT NULL,
    raw_label TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO users (id, name, color) VALUES (1, 'Moi', '#3298dc'), (2, 'Conjoint', '#00d1b2');
EOF

# 3. Création de src/Config.php
echo "📝 Création de src/Config.php..."
cat << 'EOF' > src/Config.php
<?php
class Config {
    // Configuration par défaut pour Mac MAMP/M4
    // Si tu utilises le serveur PHP intégré sans MAMP, le mot de passe est souvent vide ou 'root'
    const DB_HOST = 'localhost';
    const DB_NAME = 'budget_master';
    const DB_USER = 'root'; 
    const DB_PASS = 'root'; 
}
EOF

# 4. Création de src/Database.php
echo "📝 Création de src/Database.php..."
cat << 'EOF' > src/Database.php
<?php
require_once 'Config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, Config::DB_USER, Config::DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die("<h3>Erreur de connexion DB</h3><p>" . $e->getMessage() . "</p><p>Vérifiez src/Config.php et assurez-vous que votre serveur MySQL est lancé.</p>");
            }
        }
        return self::$instance;
    }
}
EOF

# 5. Création de src/OfxParser.php
echo "📝 Création de src/OfxParser.php..."
cat << 'EOF' > src/OfxParser.php
<?php
class OfxParser {
    public function parse(string $filePath): array {
        if (!file_exists($filePath)) throw new Exception("Fichier introuvable");

        $content = file_get_contents($filePath);
        
        // Détection et conversion encodage
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'WINDOWS-1252');
        }

        // 1. Compte
        preg_match('/<ACCTID>(.*?)(\n|<)/', $content, $matchesAcct);
        $accountNumber = isset($matchesAcct[1]) ? trim($matchesAcct[1]) : 'UNKNOWN';

        // 2. Transactions
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches);
        $transactions = [];

        foreach ($matches[1] as $block) {
            $tx = [
                'account_number' => $accountNumber,
                'fitid' => $this->extractTag('FITID', $block),
                'amount' => $this->extractAmount($block),
                'date' => $this->extractDate($block),
                'label' => $this->extractLabel($block),
            ];
            if ($tx['fitid'] && $tx['date']) $transactions[] = $tx;
        }
        return $transactions;
    }

    private function extractTag(string $tag, string $block): ?string {
        if (preg_match('/<' . $tag . '>(.*?)(\n|<)/', $block, $match)) return trim($match[1]);
        return null;
    }

    private function extractAmount(string $block): float {
        $val = $this->extractTag('TRNAMT', $block);
        return $val ? (float) str_replace(',', '.', $val) : 0.0;
    }

    private function extractDate(string $block): ?string {
        $val = $this->extractTag('DTPOSTED', $block);
        if (!$val) return null;
        $date = DateTime::createFromFormat('Ymd', substr($val, 0, 8));
        return $date ? $date->format('Y-m-d') : null;
    }

    private function extractLabel(string $block): string {
        $name = $this->extractTag('NAME', $block) ?? '';
        $memo = $this->extractTag('MEMO', $block) ?? '';
        return trim($name . ' ' . $memo);
    }
}
EOF

# 6. Création de src/ImportController.php
echo "📝 Création de src/ImportController.php..."
cat << 'EOF' > src/ImportController.php
<?php
require_once 'Database.php';
require_once 'OfxParser.php';

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

                $this->pdo->beginTransaction();
                $stmtAcc = $this->pdo->prepare("SELECT id FROM accounts WHERE account_number = ?");
                $stmtInsAcc = $this->pdo->prepare("INSERT INTO accounts (account_number, name, type) VALUES (?, 'Nouveau Compte', 'PERSONAL')");
                $stmtTx = $this->pdo->prepare("INSERT IGNORE INTO transactions (account_id, fitid, date_booked, raw_label, amount) VALUES (?, ?, ?, ?, ?)");

                $count = 0;
                foreach ($transactions as $tx) {
                    $stmtAcc->execute([$tx['account_number']]);
                    $accId = $stmtAcc->fetchColumn();
                    if (!$accId) {
                        $stmtInsAcc->execute([$tx['account_number']]);
                        $accId = $this->pdo->lastInsertId();
                    }
                    $stmtTx->execute([$accId, $tx['fitid'], $tx['date'], $tx['label'], $tx['amount']]);
                    if ($stmtTx->rowCount() > 0) $count++;
                }
                $this->pdo->commit();
                $message = "✅ Succès ! $count transactions importées.";
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $message = "❌ Erreur : " . $e->getMessage();
            }
        }

        $recentTransactions = [];
        try {
            $recentTransactions = $this->pdo->query("SELECT t.*, a.name as account_name FROM transactions t JOIN accounts a ON t.account_id = a.id ORDER BY t.date_booked DESC LIMIT 50")->fetchAll();
        } catch (Exception $e) { /* Table vide ou inexistante */ }

        require __DIR__ . '/../templates/dashboard.php';
    }
}
EOF

# 7. Création de templates/dashboard.php
echo "📝 Création de templates/dashboard.php..."
cat << 'EOF' > templates/dashboard.php
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BudgetMaster CA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <div class="column is-one-third">
                <div class="box">
                    <h1 class="title is-5"><i class="fas fa-file-import"></i> Import OFX</h1>
                    <form action="/" method="POST" enctype="multipart/form-data">
                        <div class="file has-name is-info is-fullwidth mb-3">
                            <label class="file-label">
                                <input class="file-input" type="file" name="ofx_file" accept=".ofx">
                                <span class="file-cta"><i class="fas fa-upload"></i></span>
                                <span class="file-name">Choisir fichier...</span>
                            </label>
                        </div>
                        <button type="submit" class="button is-primary is-fullwidth">Importer</button>
                    </form>
                </div>
            </div>
            <div class="column">
                <div class="box">
                    <h2 class="title is-5">Dernières Transactions</h2>
                    <table class="table is-striped is-fullwidth is-size-7">
                        <thead><tr><th>Date</th><th>Compte</th><th>Libellé</th><th class="has-text-right">Montant</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr><td colspan="4" class="has-text-centered">Aucune donnée.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d/m/y', strtotime($tx['date_booked'])); ?></td>
                                        <td><?php echo htmlspecialchars($tx['account_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tx['raw_label']); ?></td>
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

# 8. Création de public/index.php
echo "📝 Création de public/index.php..."
cat << 'EOF' > public/index.php
<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/../src/ImportController.php';
(new ImportController())->handleUpload();
EOF

echo "✅ Installation terminée !"
echo "🚀 Démarrage du serveur PHP sur http://localhost:8000..."

# Ouvrir le navigateur (fonctionne sur Mac)
if command -v open &> /dev/null; then
    open http://localhost:8000
elif command -v xdg-open &> /dev/null; then
    xdg-open http://localhost:8000
fi

# Lancer le serveur (cette commande bloque le terminal tant que le serveur tourne)
php -S localhost:8000 -t public