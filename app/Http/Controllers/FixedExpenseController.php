<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedExpenseRequest;
use App\Http\Requests\UpdateFixedExpenseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $fixedExpenses = $request->user()
            ->fixedExpenses()
            ->with('category.group')
            ->orderBy('id')
            ->get();

        return response()->json($fixedExpenses);
    }

    public function store(StoreFixedExpenseRequest $request): JsonResponse
    {
        $fixedExpense = $request->user()
            ->fixedExpenses()
            ->create($request->validated());

        return response()->json(
            $fixedExpense->load('category.group'),
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $fixedExpense = $request->user()
            ->fixedExpenses()
            ->with('category.group')
            ->findOrFail($id);

        return response()->json($fixedExpense);
    }

    public function update(
        UpdateFixedExpenseRequest $request,
        int $id
    ): JsonResponse {
        $fixedExpense = $request->user()
            ->fixedExpenses()
            ->findOrFail($id);

        $fixedExpense->update($request->validated());

        return response()->json(
            $fixedExpense->load('category.group')
        );
    }
}
