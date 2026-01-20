<?php
// public/index.php

// Affichage des erreurs pour le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Chargement des contrôleurs
require_once __DIR__ . '/../src/ImportController.php';
require_once __DIR__ . '/../src/TransactionController.php';
require_once __DIR__ . '/../src/SettingsController.php';
require_once __DIR__ . '/../src/RecurrenceController.php';
require_once __DIR__ . '/../src/BudgetController.php'; // <--- LA LIGNE MANQUANTE ÉTAIT ICI

// Routeur simple basé sur le paramètre GET 'action'
$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    // --- ACTIONS TRANSACTIONS (AJAX) ---
    case 'update_category':
        (new TransactionController())->updateCategory();
        break;
    case 'add_category':
        (new TransactionController())->addCategory();
        break;
    case 'update_account':
        (new TransactionController())->updateAccount();
        break;
    case 'bulk_update':
        (new TransactionController())->bulkUpdateCategory();
        break;
    case 'reset_data':
        (new TransactionController())->resetData();
        break;

    // --- PARAMÈTRES (Règles & Catégories) ---
    case 'settings':
        (new SettingsController())->index();
        break;
    case 'add_rule':
        (new SettingsController())->addRule();
        break;
    case 'update_rule':
        (new SettingsController())->updateRule();
        break;
    case 'delete_rule':
        (new SettingsController())->deleteRule();
        break;
    case 'update_category_name':
        (new SettingsController())->updateCategory();
        break;

    // --- RÉCURRENCES ---
    case 'recurrence':
        (new RecurrenceController())->index();
        break;
    case 'recurrence_update': // Route AJAX pour valider/ignorer une récurrence
        (new RecurrenceController())->updateStatus();
        break;
    case 'recurrence_delete': // Route AJAX pour supprimer une récurrence
        (new RecurrenceController())->delete();
        break;

    // --- BUDGETS ---
    case 'budget':
        (new BudgetController())->index();
        break;
    case 'budget_update':
        (new BudgetController())->update();
        break;
        
    // --- DÉFAUT : DASHBOARD ---
    default:
        (new ImportController())->handleUpload();
        break;
}