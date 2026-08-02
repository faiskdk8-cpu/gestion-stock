# 🛒 Backend — Gestion de Stock Boutique
### Laravel 12 · API REST · MySQL · Sanctum

---

## 📋 Prérequis

| Outil | Version minimale |
|-------|-----------------|
| PHP | 8.2+ |
| Composer | 2.x |
| MySQL | 8.0+ |
| Laragon (ou équivalent) | Dernière version |

---

## 🚀 Installation pas à pas

### 1. Créer le projet Laravel

```bash
composer create-project laravel/laravel gestion-stock
cd gestion-stock
```

### 2. Installer les dépendances

```bash
composer require laravel/sanctum
```

### 3. Copier les fichiers du projet

Copier tous les fichiers fournis dans les dossiers correspondants :

```
gestion-stock/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── PurchaseController.php
│   │   ├── SaleController.php
│   │   ├── StockController.php
│   │   └── StatisticsController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Purchase.php
│   │   ├── PurchaseItem.php
│   │   ├── Sale.php
│   │   ├── SaleItem.php
│   │   └── StockMovement.php
│   └── Services/
│       └── StockService.php
├── config/
│   ├── cors.php           ← remplace l'existant
│   └── sanctum.php        ← remplace l'existant
├── database/
│   ├── migrations/        ← remplace les migrations par défaut
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── AdminUserSeeder.php
└── routes/
    └── api.php            ← remplace l'existant
```

### 4. Configurer le fichier .env

Créer le fichier `.env` à la racine du projet et copier le contenu de `.env.example` :

```bash
cp .env.example .env
php artisan key:generate
```

Modifier les valeurs de connexion à la base de données :

```env
DB_DATABASE=gestion_stock
DB_USERNAME=root
DB_PASSWORD=           # vide sur Laragon par défaut
```

### 5. Créer la base de données

Dans phpMyAdmin (Laragon) ou en ligne de commande :

```sql
CREATE DATABASE gestion_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Lancer les migrations

```bash
php artisan migrate
```

Résultat attendu :
```
  ✓  create_users_table
  ✓  create_products_table
  ✓  create_purchases_table
  ✓  create_purchase_items_table
  ✓  create_sales_table
  ✓  create_sale_items_table
  ✓  create_stock_movements_table
```

### 7. Insérer le compte administrateur

```bash
php artisan db:seed
```

Résultat attendu :
```
✅ Compte administrateur créé :
   Email    : admin@boutique.local
   Password : password123
   ⚠️  Changez le mot de passe après la première connexion !
```

### 8. Lancer le serveur de développement

```bash
php artisan serve
```

L'API sera disponible sur : **http://127.0.0.1:8000/api/v1/**

---

## ✅ Test rapide de l'API

### Test de connexion (avec curl ou Postman) :

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@boutique.local","password":"password123"}'
```

Réponse attendue :
```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "Administrateur",
    "email": "admin@boutique.local"
  }
}
```

### Test du dashboard (avec le token) :

```bash
curl http://127.0.0.1:8000/api/v1/dashboard \
  -H "Authorization: Bearer 1|xxxxxxxx"
```

---

## 🔗 Récapitulatif des endpoints

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | /api/v1/auth/login | Connexion |
| POST | /api/v1/auth/logout | Déconnexion |
| GET | /api/v1/auth/me | Profil |
| PUT | /api/v1/auth/password | Changer mot de passe |
| GET | /api/v1/products | Liste produits |
| POST | /api/v1/products | Créer produit |
| GET | /api/v1/products/{id} | Détail produit |
| PUT | /api/v1/products/{id} | Modifier produit |
| DELETE | /api/v1/products/{id} | Archiver produit |
| GET | /api/v1/products/{id}/movements | Mouvements produit |
| GET | /api/v1/products/low-stock | Produits en alerte |
| GET | /api/v1/purchases | Liste achats |
| POST | /api/v1/purchases | Créer achat (+ CMP) |
| GET | /api/v1/purchases/{id} | Détail achat |
| PUT | /api/v1/purchases/{id} | Modifier achat |
| DELETE | /api/v1/purchases/{id} | Supprimer achat |
| GET | /api/v1/sales | Liste ventes |
| POST | /api/v1/sales | Créer vente (- stock) |
| GET | /api/v1/sales/{id} | Détail vente |
| DELETE | /api/v1/sales/{id} | Annuler vente |
| GET | /api/v1/stock | État du stock |
| GET | /api/v1/statistics/daily | Stats du jour |
| GET | /api/v1/statistics/monthly | Stats du mois |
| GET | /api/v1/statistics/total | Stats globales |
| GET | /api/v1/statistics/chart/daily | Graphique 30j |
| GET | /api/v1/statistics/chart/monthly | Graphique 12 mois |
| GET | /api/v1/statistics/top-products | Top produits |
| GET | /api/v1/dashboard | Tableau de bord |

---

## ⚠️ Points importants

- **CMP** : Recalculé automatiquement à chaque achat dans `StockService::createPurchase()`
- **Transactions ACID** : Tous les achats et ventes utilisent `DB::transaction()`
- **Lock pessimiste** : `lockForUpdate()` sur les produits pour éviter les race conditions
- **Bénéfice** : Calculé et figé lors de la vente — `(prix_vente - CMP) × quantité`
- **Annulation achat** : Bloquée si des ventes postérieures existent (RG-A06)

---

*Backend généré pour le projet Gestion de Stock Boutique — Laravel 12*
