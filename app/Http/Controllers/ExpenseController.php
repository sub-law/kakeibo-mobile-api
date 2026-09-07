<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;

class ExpenseController extends Controller
{
    // 一覧
    public function index(Request $request)
    {
        $user = $request->user();

        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        $expenses = $user->expenses()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with(['category.group'])
            ->orderBy('date', 'asc') // ← 古い順
            ->get();

        return response()->json($expenses, 200);
    }


    // 登録
    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json($expense, 201);
    }

    // 詳細
    public function show(Request $request, $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)
            ->with('category.group')
            ->findOrFail($id);

        return response()->json($expense, 200);
    }


    // 更新
    public function update(UpdateExpenseRequest $request, $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($id);

        $expense->update($request->validated());

        return response()->json($expense,200);
    }


    // 削除
    public function destroy(Request $request, $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($id);

        $expense->delete();

        return response()->json(['message' => 'Deleted'],200);
    }
}
