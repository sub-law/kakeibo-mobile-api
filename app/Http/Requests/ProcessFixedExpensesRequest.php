<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessFixedExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_month' => [
                'required',
                'date_format:Y-m',
                Rule::in([now()->format('Y-m')]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'target_month.required' => '対象月は必須です。',
            'target_month.date_format' => '対象月の形式が正しくありません。',
            'target_month.in' => '出金処理できるのは今月分のみです。',
        ];
    }
}
