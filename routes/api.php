<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StatisticsController;

/*
|--------------------------------------------------------------------------
| API Routes — Application Gestion de Stock Boutique
|--------------------------------------------------------------------------
| Préfixe global : /api  (défini dans bootstrap/app.php)
| Versioning     : /api/v1/...
| Auth           : Laravel Sanctum (Bearer token)
*/

Route::prefix('v1')->group(function () {

    // ─── Routes publiques (pas de token requis) ─────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    // ─── Routes protégées (token Sanctum obligatoire) ───────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('password',         [AuthController::class, 'updatePassword']);
        });

        // Produits
        Route::prefix('products')->group(function () {
            Route::get('/',                [ProductController::class, 'index']);
            Route::post('/',               [ProductController::class, 'store']);
            Route::get('low-stock',        [ProductController::class, 'lowStock']);
            Route::get('{product}',        [ProductController::class, 'show']);
            Route::put('{product}',        [ProductController::class, 'update']);
            Route::delete('{product}',     [ProductController::class, 'destroy']);
            Route::get('{product}/movements', [ProductController::class, 'movements']);
        });

        // Achats
        Route::prefix('purchases')->group(function () {
            Route::get('/',                [PurchaseController::class, 'index']);
            Route::post('/',               [PurchaseController::class, 'store']);
            Route::get('{purchase}',       [PurchaseController::class, 'show']);
            Route::put('{purchase}',       [PurchaseController::class, 'update']);
            Route::delete('{purchase}',    [PurchaseController::class, 'destroy']);
        });

        // Ventes
        Route::prefix('sales')->group(function () {
            Route::get('/',                [SaleController::class, 'index']);
            Route::post('/',               [SaleController::class, 'store']);
            Route::get('{sale}',           [SaleController::class, 'show']);
            Route::delete('{sale}',        [SaleController::class, 'destroy']);
        });

        // Stock
        Route::prefix('stock')->group(function () {
            Route::get('/',                [StockController::class, 'index']);
        });

        // Statistiques & Tableau de bord
        Route::prefix('statistics')->group(function () {
            Route::get('daily',            [StatisticsController::class, 'daily']);
            Route::get('monthly',          [StatisticsController::class, 'monthly']);
            Route::get('total',            [StatisticsController::class, 'total']);
            Route::get('chart/daily',      [StatisticsController::class, 'chartDaily']);
            Route::get('chart/monthly',    [StatisticsController::class, 'chartMonthly']);
            Route::get('top-products',     [StatisticsController::class, 'topProducts']);
        });

        Route::get('dashboard',            [StatisticsController::class, 'dashboard']);
    });
});
