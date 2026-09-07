<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => '現在のパスワードを入力してください。',
            'password.required' => '新しいパスワードを入力してください。',
            'password.min' => '新しいパスワードは8文字以上で入力してください。',
            'password.confirmed' => '新しいパスワード（確認）が一致しません。',
            'password.different' => '現在のパスワードとは異なるパスワードを入力してください。',
        ];
    }
}
