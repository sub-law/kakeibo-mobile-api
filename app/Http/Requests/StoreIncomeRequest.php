<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '日付を入力してください。',
            'date.date' => '日付の形式が正しくありません。',
            'amount.required' => '金額を入力してください。',
            'amount.integer' => '金額は整数で入力してください。',
            'amount.min' => '金額は1円以上で入力してください。',
            'amount.max' => '金額は2,147,483,647円以下で入力してください。',
            'memo.string' => 'メモは文字列で入力してください。',
            'memo.max' => 'メモは255文字以内で入力してください。',
        ];
    }
}
