<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetAlertSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
                Rule::unique('budget_alert_settings', 'category_id')
                    ->where('user_id', $this->user()->id),
            ],
            'monthly_budget' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'warning_threshold_percent' => ['required', 'integer', 'between:1,99'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'カテゴリは必須です。',
            'category_id.integer' => 'カテゴリの形式が正しくありません。',
            'category_id.exists' => '選択したカテゴリが存在しません。',
            'category_id.unique' => '同じカテゴリのアラート設定はすでに登録されています。',
            'monthly_budget.required' => '月間予算は必須です。',
            'monthly_budget.integer' => '月間予算は整数で入力してください。',
            'monthly_budget.min' => '月間予算は1円以上で入力してください。',
            'monthly_budget.max' => '月間予算は4,294,967,295円以下で入力してください。',
            'warning_threshold_percent.required' => '警告割合は必須です。',
            'warning_threshold_percent.integer' => '警告割合は整数で入力してください。',
            'warning_threshold_percent.between' => '警告割合は1%から99%の間で入力してください。',
            'is_enabled.required' => 'アラートの有効・無効を指定してください。',
            'is_enabled.boolean' => 'アラートの有効・無効の形式が正しくありません。',
        ];
    }
}
