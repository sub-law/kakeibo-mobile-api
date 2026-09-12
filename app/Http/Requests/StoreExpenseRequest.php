<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sanctum が認証するので true
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'memo' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '日付は必須です。',
            'date.date' => '日付の形式が正しくありません。',
            'amount.required' => '金額は必須です。',
            'amount.integer' => '金額は整数で入力してください。',
            'amount.min' => '金額は1円以上で入力してください。',
            'amount.max' => '金額は2,147,483,647円以下で入力してください。',
            'memo.string' => 'メモは文字列で入力してください。',
            'memo.max' => 'メモは255文字以内で入力してください。',
            'category_id.required' => 'カテゴリは必須です。',
            'category_id.exists' => '選択したカテゴリが存在しません。',
        ];
    }
}
