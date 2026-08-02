<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * GET /api/v1/stock
     * Vue en temps réel du stock de tous les produits actifs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true);

        // Filtre alertes uniquement
        if ($request->filter === 'low_stock') {
            $query->where('alert_threshold', '>', 0)
                  ->whereColumn('current_stock', '<=', 'alert_threshold');
        }

        // Recherche
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Tri
        $sort = match($request->sort) {
            'stock_asc'   => ['current_stock', 'asc'],
            'stock_desc'  => ['current_stock', 'desc'],
            'value_desc'  => ['current_stock', 'desc'], // approximatif sans colonne calculée
            'name_desc'   => ['name', 'desc'],
            default       => ['name', 'asc'],
        };
        $query->orderBy($sort[0], $sort[1]);

        $products = $query->get();

        // Valeur totale du stock
        $totalStockValue = $products->sum(fn($p) => $p->stock_value);
        $lowStockCount   = $products->filter(fn($p) => $p->is_low_stock)->count();

        return response()->json([
            'summary' => [
                'total_products'    => $products->count(),
                'total_stock_value' => round($totalStockValue, 2),
                'low_stock_count'   => $lowStockCount,
            ],
            'data' => $products->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'unit'            => $p->unit,
                'current_stock'   => (float) $p->current_stock,
                'current_cmp'     => (float) $p->current_cmp,
                'stock_value'     => $p->stock_value,
                'alert_threshold' => (float) $p->alert_threshold,
                'is_low_stock'    => $p->is_low_stock,
            ]),
        ]);
    }
}
