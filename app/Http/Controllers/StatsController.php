<?php

namespace App\Http\Controllers;

use App\Services\MonthlyStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StatsController extends Controller
{
    public function __construct(
        private MonthlyStatsService $monthlyStatsService
    ) {}

    public function monthlySummary(Request $request, string $year): JsonResponse
    {
        $validator = Validator::make(
            ['year' => $year],
            ['year' => ['required', 'integer', 'min:1900', 'max:2100']],
            [
                'year.integer' => '年は数値で指定してください。',
                'year.min' => '年は1900年以上で指定してください。',
                'year.max' => '年は2100以下で指定してください。',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力内容に誤りがあります。',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json(
            $this->monthlyStatsService->build($request->user(), (int) $year)
        );
    }
}
