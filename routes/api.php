<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetBalanceController;
use App\Http\Controllers\BudgetAlertReadController;
use App\Http\Controllers\BudgetAlertSettingController;
use App\Http\Controllers\BudgetAlertStatusController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\FixedExpenseProcessController;
use App\Http\Controllers\StatsController;


Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {

    // 認証ユーザー取得
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ログアウト
    Route::post('/logout', LogoutController::class);

    // パスワード変更
    Route::put('/user/password', [PasswordController::class, 'update']);

    // 入金機能
    Route::prefix('incomes')->group(function () {
        Route::get('/', [IncomeController::class, 'index']);
        Route::post('/', [IncomeController::class, 'store']);
        Route::get('/{id}', [IncomeController::class, 'show']);
        Route::put('/{id}', [IncomeController::class, 'update']);
        Route::delete('/{id}', [IncomeController::class, 'destroy']);
    });

    // カテゴリ一覧（大分類 → 小分類）
    Route::get('/categories', [CategoryController::class, 'index']);

    // 支出機能
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/{id}', [ExpenseController::class, 'show']);
        Route::put('/{id}', [ExpenseController::class, 'update']);
        Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    });

    // 口座一覧（セレクトボックス用）
    Route::get('/accounts', [AccountController::class, 'index']);

    // 月次資産残高（asset_balances）
    Route::prefix('asset-balances')->group(function () {
        Route::post('/bulk', [AssetBalanceController::class, 'bulk']);   // 一括登録
        Route::get('/', [AssetBalanceController::class, 'index']);       // 月次一覧
    });

    // 管理画面向け年次集計
    Route::get('/stats/{year}/monthly-summary', [StatsController::class, 'monthlySummary']);

    // 予算アラート設定
    Route::get('/budget-alert-settings', [BudgetAlertSettingController::class, 'index']);
    Route::post('/budget-alert-settings', [BudgetAlertSettingController::class, 'store']);
    Route::get('/budget-alert-settings/{id}', [BudgetAlertSettingController::class, 'show']);
    Route::put('/budget-alert-settings/{id}', [BudgetAlertSettingController::class, 'update']);
    Route::delete('/budget-alert-settings/{id}', [BudgetAlertSettingController::class, 'destroy']);
    Route::post('/budget-alert-settings/{id}/read', [BudgetAlertReadController::class, 'store']);

    // トップ画面向け予算アラート判定
    Route::get('/budget-alert-status', [BudgetAlertStatusController::class, 'show']);

    // 固定費設定・月次出金処理
    Route::get('/fixed-expenses/process-preview', [FixedExpenseProcessController::class, 'preview']);
    Route::post('/fixed-expenses/process', [FixedExpenseProcessController::class, 'store']);
    Route::get('/fixed-expenses', [FixedExpenseController::class, 'index']);
    Route::post('/fixed-expenses', [FixedExpenseController::class, 'store']);
    Route::get('/fixed-expenses/{id}', [FixedExpenseController::class, 'show']);
    Route::put('/fixed-expenses/{id}', [FixedExpenseController::class, 'update']);
});
