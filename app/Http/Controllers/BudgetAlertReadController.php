<?php

namespace App\Http\Controllers;

use App\Services\BudgetAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetAlertReadController extends Controller
{
    public function __construct(
        private BudgetAlertService $budgetAlertService
    ) {}

    public function store(Request $request, int $id): JsonResponse
    {
        $setting = $request->user()
            ->budgetAlertSettings()
            ->with('category.group')
            ->findOrFail($id);
        $alert = $this->budgetAlertService->currentAlert(
            $request->user(),
            $setting
        );

        if (! $alert) {
            return response()->json([
                'message' => '既読にできるアラートがありません。',
            ], 409);
        }

        $today = now();

        $setting->reads()->updateOrCreate(
            [
                'year' => $today->year,
                'month' => $today->month,
                'level' => $alert['level'],
            ],
            [
                'read_at' => $today,
            ]
        );

        return response()->json([
            'message' => 'アラートを既読にしました。',
        ]);
    }
}
