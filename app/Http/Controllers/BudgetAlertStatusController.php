<?php

namespace App\Http\Controllers;

use App\Services\BudgetAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetAlertStatusController extends Controller
{
    public function __construct(
        private BudgetAlertService $budgetAlertService
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->budgetAlertService->build($request->user())
        );
    }
}
