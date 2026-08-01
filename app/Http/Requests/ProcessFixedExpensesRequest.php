<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProcessFixedExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_month' => ['required', 'date_format:Y-m'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    is_string($this->input('target_month'))
                    && $this->input('target_month') !== now()->format('Y-m')
                ) {
                    $validator->errors()->add(
                        'target_month',
                        '出金処理できるのは当月分のみです。'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'target_month.required' => '対象月は必須です。',
            'target_month.date_format' => '対象月の形式が正しくありません。',
        ];
    }
}
