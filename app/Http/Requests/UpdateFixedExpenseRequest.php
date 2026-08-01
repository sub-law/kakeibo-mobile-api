<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFixedExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'memo' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'カテゴリは必須です。',
            'category_id.integer' => 'カテゴリの形式が正しくありません。',
            'category_id.exists' => '選択したカテゴリが存在しません。',
            'amount.required' => '金額は必須です。',
            'amount.integer' => '金額は整数で入力してください。',
            'amount.min' => '金額は1円以上で入力してください。',
            'amount.max' => '金額は2,147,483,647円以下で入力してください。',
            'memo.required' => '用途は必須です。',
            'memo.string' => '用途は文字列で入力してください。',
            'memo.max' => '用途は255文字以内で入力してください。',
            'is_enabled.required' => '固定費の有効・無効を指定してください。',
            'is_enabled.boolean' => '固定費の有効・無効の形式が正しくありません。',
        ];
    }
}
