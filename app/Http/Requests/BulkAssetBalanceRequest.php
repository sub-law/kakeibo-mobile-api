<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssetBalanceRequest extends FormRequest
{
    public function authorize()
    {
        // ログインユーザーならOK（必要なら細かく制御可能）
        return true;
    }

    public function rules()
    {
        return [
            'date' => 'required|date',
            'balances' => 'required|array',
            'balances.*.account_id' => 'required|exists:accounts,id',
            'balances.*.amount' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'date.required' => '日付は必須です。',
            'balances.required' => '残高データがありません。',
            'balances.*.account_id.required' => '口座IDは必須です。',
            'balances.*.account_id.exists' => '指定された口座が存在しません。',
            'balances.*.amount.integer' => '金額は整数で入力してください。',
            'balances.*.amount.min' => '金額は0以上で入力してください。',
        ];
    }
}
