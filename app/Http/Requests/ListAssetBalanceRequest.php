<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAssetBalanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
        ];
    }

    public function messages()
    {
        return [
            'year.integer' => '年は数値で入力してください。',
            'month.integer' => '月は数値で入力してください。',
            'month.min' => '月は1〜12の範囲で入力してください。',
            'month.max' => '月は1〜12の範囲で入力してください。',
        ];
    }
}
