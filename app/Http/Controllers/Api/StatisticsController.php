<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * GET /api/v1/statistics/daily?date=YYYY-MM-DD
     * Statistiques du jour (ou d'une date donnée).
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->get('date', today()->toDateString());

        $stats = SaleItem::whereHas('sale', fn($q) => $q->whereDate('date', $date))
            ->selectRaw('
                COUNT(DISTINCT sale_id)              AS sales_count,
                SUM(quantity)                        AS items_sold,
                SUM(quantity * unit_price)           AS revenue,
                SUM(benefit)                         AS benefit
            ')
            ->first();

        $purchasesTotal = \App\Models\PurchaseItem::whereHas(
            'purchase', fn($q) => $q->whereDate('date', $date)
        )->selectRaw('SUM(quantity * unit_price) AS total')->first()->total ?? 0;

        return response()->json([
            'date'            => $date,
            'sales_count'     => (int)   ($stats->sales_count  ?? 0),
            'items_sold'      => (float) ($stats->items_sold   ?? 0),
            'revenue'         => round((float) ($stats->revenue     ?? 0), 2),
            'benefit'         => round((float) ($stats->benefit     ?? 0), 2),
            'purchases_total' => round((float) $purchasesTotal, 2),
        ]);
    }

    /**
     * GET /api/v1/statistics/monthly?year=YYYY&month=MM
     * Statistiques d'un mois donné.
     */
    public function monthly(Request $request): JsonResponse
    {
        $year  = $request->get('year',  now()->year);
        $month = $request->get('month', now()->month);

        $stats = SaleItem::whereHas('sale', fn($q) =>
            $q->whereYear('date', $year)->whereMonth('date', $month)
        )
        ->selectRaw('
            COUNT(DISTINCT sale_id)    AS sales_count,
            SUM(quantity)              AS items_sold,
            SUM(quantity * unit_price) AS revenue,
            SUM(benefit)               AS benefit
        ')
        ->first();

        // Top produit du mois
        $topProduct = SaleItem::whereHas('sale', fn($q) =>
            $q->whereYear('date', $year)->whereMonth('date', $month)
        )
        ->selectRaw('product_id, SUM(quantity * unit_price) AS ca')
        ->groupBy('product_id')
        ->orderByDesc('ca')
        ->with('product:id,name,unit')
        ->first();

        return response()->json([
            'year'         => (int) $year,
            'month'        => (int) $month,
            'sales_count'  => (int)   ($stats->sales_count ?? 0),
            'items_sold'   => (float) ($stats->items_sold  ?? 0),
            'revenue'      => round((float) ($stats->revenue    ?? 0), 2),
            'benefit'      => round((float) ($stats->benefit    ?? 0), 2),
            'top_product'  => $topProduct ? [
                'id'   => $topProduct->product->id,
                'name' => $topProduct->product->name,
                'ca'   => round((float) $topProduct->ca, 2),
            ] : null,
        ]);
    }

    /**
     * GET /api/v1/statistics/total
     * Totaux depuis l'origine.
     */
    public function total(): JsonResponse
    {
        $sales = SaleItem::selectRaw('
            COUNT(DISTINCT sale_id)    AS sales_count,
            SUM(quantity)              AS items_sold,
            SUM(quantity * unit_price) AS revenue,
            SUM(benefit)               AS benefit
        ')->first();

        $purchasesTotal = \App\Models\PurchaseItem::selectRaw(
            'SUM(quantity * unit_price) AS total'
        )->first()->total ?? 0;

        $stockValue = Product::where('is_active', true)->get()
            ->sum(fn($p) => $p->stock_value);

        return response()->json([
            'sales_count'     => (int)   ($sales->sales_count ?? 0),
            'items_sold'      => (float) ($sales->items_sold  ?? 0),
            'revenue'         => round((float) ($sales->revenue ?? 0), 2),
            'benefit'         => round((float) ($sales->benefit ?? 0), 2),
            'purchases_total' => round((float) $purchasesTotal, 2),
            'stock_value'     => round((float) $stockValue, 2),
        ]);
    }

    /**
     * GET /api/v1/statistics/chart/daily
     * Données du graphique : bénéfices des 30 derniers jours.
     */
    public function chartDaily(): JsonResponse
    {
        $rows = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('
                sales.date                 AS day,
                SUM(sale_items.quantity * sale_items.unit_price) AS revenue,
                SUM(sale_items.benefit)    AS benefit
            ')
            ->groupBy('sales.date')
            ->orderBy('sales.date')
            ->get()
            ->keyBy('day');

        // Remplir les jours sans vente avec des zéros
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $data[] = [
                'date'    => $day,
                'revenue' => round((float) ($rows[$day]->revenue ?? 0), 2),
                'benefit' => round((float) ($rows[$day]->benefit ?? 0), 2),
            ];
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/statistics/chart/monthly
     * Données du graphique : bénéfices des 12 derniers mois.
     */
    public function chartMonthly(): JsonResponse
    {
        $rows = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->selectRaw('
                YEAR(sales.date)  AS year,
                MONTH(sales.date) AS month,
                SUM(sale_items.quantity * sale_items.unit_price) AS revenue,
                SUM(sale_items.benefit)    AS benefit
            ')
            ->groupByRaw('YEAR(sales.date), MONTH(sales.date)')
            ->orderByRaw('YEAR(sales.date), MONTH(sales.date)')
            ->get()
            ->keyBy(fn($r) => "{$r->year}-{$r->month}");

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $key   = "{$date->year}-{$date->month}";
            $data[] = [
                'year'    => (int) $date->year,
                'month'   => (int) $date->month,
                'label'   => $date->translatedFormat('M Y'),
                'revenue' => round((float) ($rows[$key]->revenue ?? 0), 2),
                'benefit' => round((float) ($rows[$key]->benefit ?? 0), 2),
            ];
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/statistics/top-products?from=&to=&limit=10
     * Classement des produits les plus vendus.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 10), 50);

        $query = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('
                sale_items.product_id,
                products.name        AS product_name,
                products.unit        AS product_unit,
                SUM(sale_items.quantity)                          AS qty_sold,
                SUM(sale_items.quantity * sale_items.unit_price)  AS revenue,
                SUM(sale_items.benefit)                           AS benefit
            ')
            ->groupBy('sale_items.product_id', 'products.name', 'products.unit')
            ->orderByDesc('revenue');

        if ($request->filled('from')) {
            $query->where('sales.date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('sales.date', '<=', $request->to);
        }

        $results = $query->limit($limit)->get();

        return response()->json($results->map(fn($r) => [
            'product_id'   => $r->product_id,
            'product_name' => $r->product_name,
            'product_unit' => $r->product_unit,
            'qty_sold'     => round((float) $r->qty_sold, 4),
            'revenue'      => round((float) $r->revenue, 2),
            'benefit'      => round((float) $r->benefit, 2),
        ]));
    }

    /**
     * GET /api/v1/dashboard
     * Agrégat complet pour le tableau de bord.
     */
    public function dashboard(): JsonResponse
    {
        $today     = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        // Stats du jour
        $todayStats = SaleItem::whereHas('sale', fn($q) => $q->whereDate('date', $today))
            ->selectRaw('
                COUNT(DISTINCT sale_id)              AS sales_count,
                SUM(quantity * unit_price)           AS revenue,
                SUM(benefit)                         AS benefit
            ')->first();

        // Stats d'hier (pour calcul de variation)
        $yesterdayStats = SaleItem::whereHas('sale', fn($q) => $q->whereDate('date', $yesterday))
            ->selectRaw('SUM(benefit) AS benefit')->first();

        // Alertes stock
        $lowStockProducts = Product::lowStock()->orderBy('current_stock')->limit(5)->get();

        // Valeur totale du stock
        $stockValue = Product::where('is_active', true)->get()->sum(fn($p) => $p->stock_value);

        // Dernières ventes
        $recentSales = Sale::with('items.product')
            ->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->limit(5)->get();

        // Derniers achats
        $recentPurchases = Purchase::with('items.product')
            ->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->limit(5)->get();

        $todayBenefit     = round((float) ($todayStats->benefit     ?? 0), 2);
        $yesterdayBenefit = round((float) ($yesterdayStats->benefit ?? 0), 2);

        return response()->json([
            'today' => [
                'date'        => $today,
                'sales_count' => (int)   ($todayStats->sales_count ?? 0),
                'revenue'     => round((float) ($todayStats->revenue ?? 0), 2),
                'benefit'     => $todayBenefit,
                'benefit_vs_yesterday' => $yesterdayBenefit != 0
                    ? round((($todayBenefit - $yesterdayBenefit) / abs($yesterdayBenefit)) * 100, 1)
                    : null,
            ],
            'stock' => [
                'total_value'     => round($stockValue, 2),
                'low_stock_count' => Product::lowStock()->count(),
                'low_stock_items' => $lowStockProducts->map(fn($p) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'current_stock'   => (float) $p->current_stock,
                    'alert_threshold' => (float) $p->alert_threshold,
                    'unit'            => $p->unit,
                ]),
            ],
            'recent_sales' => $recentSales->map(fn($s) => [
                'id'            => $s->id,
                'date'          => $s->date->format('Y-m-d'),
                'items_count'   => $s->items->count(),
                'total_revenue' => $s->total_revenue,
                'total_benefit' => $s->total_benefit,
            ]),
            'recent_purchases' => $recentPurchases->map(fn($p) => [
                'id'         => $p->id,
                'date'       => $p->date->format('Y-m-d'),
                'items_count'=> $p->items->count(),
                'total_cost' => $p->total_cost,
            ]),
        ]);
    }
}
