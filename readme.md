# 💰 BudgetMaster CA

BudgetMaster CA est une application web légère de gestion de finances personnelles, optimisée pour l'import de relevés bancaires (format OFX, spécifiquement Crédit Agricole) et la gestion multi-comptes (Perso / Joint).

Développée en PHP 8.2+ sans framework lourd ("Zero-Dependency"), elle est conçue pour être performante sur un hébergement mutualisé standard ou en local sur macOS (Apple Silicon).

## ✨ Fonctionnalités Principales

### 1. Importation & Gestion des Comptes

- **Import OFX Robuste** : Support des fichiers OFX contenant plusieurs comptes bancaires.
- **Déduplication** : Gestion intelligente des doublons via l'ID unique (FITID) des transactions.
- **Gestion Multi-Utilisateurs** : Distinction entre les comptes personnels ("Moi") et les comptes communs ("Compte-Joint").
- **Correction des Comptes** : Réparation automatique de l'attribution des comptes lors de la réimportation.

### 2. Catégorisation Intelligente ("Le Cerveau")

**Règles Avancées :**
- **Pattern** : Recherche de mots-clés ("CARREFOUR").
- **Exclusion** : "Sauf si contient..." (ex : Ignorer "VIREMENT" dans une règle "AUCHAN").
- **Montant Exact** : Cibler une transaction spécifique (ex : "NETFLIX" à 13.49€).
- **Alias (Custom Label)** : Renommage automatique (ex : "PAYPAL *SPOTIFY" devient "Spotify").

**Apprentissage & Automatisation :**
- **Apprentissage Historique** : Si aucune règle ne correspond, recherche d'une transaction similaire dans le passé (15 premiers caractères).
- **Propagation Rétroactive** : Toute création ou modification de règle met à jour instantanément l'historique concerné.

### 3. Tableau de Bord (Dashboard)

- **Vues Filtrables** : Bascule entre la vue "Famille" (Tout) et la vue "Moi" (Mes comptes).
- **Filtres Puissants** : Recherche multi-mots, filtre par date, montant (min/max) et catégorie.
- **Actions en Masse (Bulk)** : Modification de catégorie sur plusieurs transactions en un clic.
- **Visualisation** : Graphique interactif (Donut) des dépenses, dynamique selon les filtres.
- **Pagination** : Navigation fluide même avec des milliers de transactions.

### 4. Module Récurrences (Abonnements)

- **Détection Automatique** : Algorithme statistique pour paiements réguliers (Mensuels, Annuels).
- **Mode Déterministe** : Marquage manuel d'une règle comme "Récurrente".
- **Projection** : Calcul des charges fixes mensuelles et annuelles.
- **Workflow** : Validation ou exclusion manuelle des récurrences détectées.

### 5. Module Budgets

- **Objectifs** : Définition de budgets mensuels par catégorie.
- **Suivi Temps Réel** : Barres de progression avec codes couleurs.
- **Reste à Dépenser** : Calcul automatique du solde disponible.

## 🛠 Prérequis Techniques

- **Serveur Web** : Apache, Nginx ou PHP Built-in Server.
- **PHP** : Version 8.2 ou supérieure.
- **Extensions requises** : pdo, pdo_mysql, mbstring, intl.
- **Base de Données** : MySQL 5.7+ ou MariaDB 10.3+.

## 🚀 Installation

### 1. Base de Données

```bash
mysql -u root -p -e "CREATE DATABASE budget_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p budget_master < database.sql
```

### 2. Configuration

Modifiez le fichier `src/Config.php` :

```php
class Config {
    const DB_HOST = 'localhost';
    const DB_NAME = 'budget_master';
    const DB_USER = 'root';
    const DB_PASS = 'votre_mot_de_passe';
}
```

### 3. Lancement (En Local)

```bash
php -S localhost:8000 -t public
```

Puis ouvrez http://localhost:8000 dans votre navigateur.

## 📂 Structure du Projet

```
/
├── public/
│   ├── index.php
│   └── assets/
├── src/
│   ├── Config.php
│   ├── Database.php
│   ├── OfxParser.php
│   ├── Categorizer.php
│   ├── [Names]Controller.php
│   └── [Names]Service.php
├── templates/
│   ├── dashboard.php
│   ├── settings.php
│   ├── recurrence.php
│   └── budget.php
├── database.sql
└── README.md
```

## 💡 Astuces d'Utilisation

- **Premier Import** : Importez un OFX sur une large période pour enrichir l'historique.
- **Nettoyage Initial** : Créez des règles pour vos commerçants majeurs et cochez "Récurrent" pour les abonnements.
- **Virements Internes** : Classez correctement épargne et transferts pour des statistiques propres.
- **Recherche** : Utilisez la sélection globale pour catégoriser en masse.

## 🛡 Sécurité

- Usage prévu : personnel, local ou accès restreint.
- Pas d'authentification intégrée par défaut (prévue Phase 6).
- Ne pas exposer publiquement sans couche d'authentification.


---

## Licence 📜

Projet open source librement modifiable et redistribuable.  