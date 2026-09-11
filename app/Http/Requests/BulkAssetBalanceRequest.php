<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssetBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:1900-01-01',
                'before_or_equal:2100-12-01',
                'regex:/^\d{4}-(0[1-9]|1[0-2])-01$/',
            ],
            'balances' => ['required', 'array', 'min:1'],
            'balances.*.account_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $this->user()->id),
            ],
            'balances.*.amount' => [
                'nullable',
                'integer',
                'min:0',
                'max:2147483647',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '日付は必須です。',
            'date.date_format' => '日付はYYYY-MM-DD形式で入力してください。',
            'date.after_or_equal' => '日付は1900年1月以降で入力してください。',
            'date.before_or_equal' => '日付は2100年12月以前で入力してください。',
            'date.regex' => '月次残高の日付は月初で指定してください。',
            'balances.required' => '残高データがありません。',
            'balances.min' => '残高データを1件以上入力してください。',
            'balances.*.account_id.required' => '口座IDは必須です。',
            'balances.*.account_id.integer' => '口座IDは整数で入力してください。',
            'balances.*.account_id.distinct' => '同じ口座を重複して指定できません。',
            'balances.*.account_id.exists' => '指定された口座が存在しません。',
            'balances.*.amount.integer' => '金額は整数で入力してください。',
            'balances.*.amount.min' => '金額は0以上で入力してください。',
            'balances.*.amount.max' => '金額は2,147,483,647円以下で入力してください。',
        ];
    }
}
