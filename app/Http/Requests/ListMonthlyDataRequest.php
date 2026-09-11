<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMonthlyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.integer' => '年は数値で入力してください。',
            'year.min' => '年は1900〜2100の範囲で入力してください。',
            'year.max' => '年は1900〜2100の範囲で入力してください。',
            'month.integer' => '月は数値で入力してください。',
            'month.min' => '月は1〜12の範囲で入力してください。',
            'month.max' => '月は1〜12の範囲で入力してください。',
        ];
    }
}
