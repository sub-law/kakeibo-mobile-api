<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginHistoryReadController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $history = $request->user()
            ->loginHistories()
            ->findOrFail($id);

        if ($history->read_at === null) {
            $history->forceFill([
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'message' => 'ログイン履歴を既読にしました。',
        ]);
    }
}
