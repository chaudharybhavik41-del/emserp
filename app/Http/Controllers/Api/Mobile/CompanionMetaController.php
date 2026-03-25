<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanionMetaController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'surface' => 'mobile-companion',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'surface' => 'mobile-companion',
                'version' => 'v1',
            ],
            'scope' => [
                'production_shop_floor',
                'store_requisition',
                'quality_control',
                'maintenance',
                'approvals',
            ],
            'notes' => [
                'This mobile app is a companion to the ERP, not a replacement.',
                'Desktop-heavy accounting, reporting, and admin screens remain web-first.',
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'employee_code' => $user?->employee_code,
            ],
            'permissions' => $user
                ? $user->getAllPermissions()->pluck('name')->values()->all()
                : [],
        ]);
    }
}
