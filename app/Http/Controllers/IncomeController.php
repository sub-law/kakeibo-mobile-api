<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Income;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        $incomes = $user->incomes()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc') // ← 古い順
            ->get();

        return response()->json($incomes, 200);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $income = Income::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($income, 200);
    }

    public function store(StoreIncomeRequest $request)
    {
        $income = $request->user()->incomes()->create($request->validated());

        return response()->json($income, 201);
    }

    public function update(UpdateIncomeRequest $request, int $id)
    {
        $user = $request->user();

        $income = Income::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $income->update($request->validated());

        return response()->json($income, 200);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $income = Income::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $income->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }
};
