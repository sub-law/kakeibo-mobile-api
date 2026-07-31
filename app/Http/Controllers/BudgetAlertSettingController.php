<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetAlertSettingRequest;
use App\Http\Requests\UpdateBudgetAlertSettingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetAlertSettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = $request->user()
            ->budgetAlertSettings()
            ->with('category.group')
            ->orderBy('category_id')
            ->get();

        return response()->json($settings);
    }

    public function store(StoreBudgetAlertSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $setting = $request->user()
            ->budgetAlertSettings()
            ->create($validated);

        return response()->json(
            $setting->load('category.group'),
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $setting = $request->user()
            ->budgetAlertSettings()
            ->with('category.group')
            ->findOrFail($id);

        return response()->json($setting);
    }

    public function update(
        UpdateBudgetAlertSettingRequest $request,
        int $id
    ): JsonResponse {
        $setting = $request->user()
            ->budgetAlertSettings()
            ->findOrFail($id);
        $validated = $request->validated();

        $setting->update($validated);
        $setting->reads()->delete();

        return response()->json(
            $setting->load('category.group')
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $setting = $request->user()
            ->budgetAlertSettings()
            ->findOrFail($id);
        $setting->delete();

        return response()->json([
            'message' => 'アラート設定を削除しました。',
        ]);
    }
}
