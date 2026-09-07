<?php

namespace App\Http\Controllers;

use App\Models\AssetBalance;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\BulkAssetBalanceRequest;
use App\Http\Requests\ListAssetBalanceRequest;
class AssetBalanceController extends Controller
{
    /**
     * 月次残高の一括登録
     * POST /api/asset-balances/bulk
     */
    public function bulk(BulkAssetBalanceRequest $request)
    {
        $userId = Auth::id();
        $date = $request->date;
        $balances = $request->balances;

        $results = [];

        foreach ($balances as $balance) {
            $accountId = $balance['account_id'];
            $amount = $balance['amount'] ?? 0;

            $record = AssetBalance::updateOrCreate(
                [
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'date' => $date,
                ],
                [
                    'amount' => $amount,
                ]
            );

            $results[] = $record;
        }

        return response()->json([
            'message' => '月次残高を登録しました（上書き含む）',
            'data' => $results,
        ], 200);
    }

    /**
     * 月次残高一覧
     * GET /api/asset-balances?year=2026&month=7
     */
    public function index(ListAssetBalanceRequest $request)
    {
        $userId = Auth::id();
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $balances = AssetBalance::with('account')
            ->where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('account_id')
            ->get();

        return response()->json([
            'data' => $balances,
        ], 200);
    }

}
