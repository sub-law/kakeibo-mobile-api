<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessFixedExpensesRequest;
use App\Services\FixedExpenseProcessingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class FixedExpenseProcessController extends Controller
{
    public function preview(
        ProcessFixedExpensesRequest $request,
        FixedExpenseProcessingService $service
    ): JsonResponse {
        $targetMonth = CarbonImmutable::createFromFormat(
            '!Y-m',
            $request->validated('target_month')
        );

        return response()->json(
            $service->preview($request->user(), $targetMonth)
        );
    }

    public function store(
        ProcessFixedExpensesRequest $request,
        FixedExpenseProcessingService $service
    ): JsonResponse {
        $targetMonth = CarbonImmutable::createFromFormat(
            '!Y-m',
            $request->validated('target_month')
        );

        return response()->json(
            $service->process($request->user(), $targetMonth),
            201
        );
    }
}
