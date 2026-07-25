<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'memo' => ['nullable', 'string'],
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
            'category_id.required' => 'カテゴリは必須です。',
            'category_id.exists' => '選択したカテゴリが存在しません。',
        ];
    }
}
