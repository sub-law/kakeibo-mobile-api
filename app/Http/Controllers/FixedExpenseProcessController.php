<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessFixedExpensesRequest;
use App\Services\FixedExpenseProcessingService;
use Illuminate\Http\JsonResponse;

class FixedExpenseProcessController extends Controller
{
    public function preview(
        ProcessFixedExpensesRequest $request,
        FixedExpenseProcessingService $service
    ): JsonResponse {
        return response()->json(
            $service->preview(
                $request->user(),
                $request->validated('target_month')
            )
        );
    }

    public function store(
        ProcessFixedExpensesRequest $request,
        FixedExpenseProcessingService $service
    ): JsonResponse {
        return response()->json(
            $service->process(
                $request->user(),
                $request->validated('target_month')
            )
        );
    }
}
