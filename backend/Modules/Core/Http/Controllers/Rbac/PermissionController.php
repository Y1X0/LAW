<?php

namespace Modules\Core\Http\Controllers\Rbac;

use Illuminate\Http\JsonResponse;
use Modules\Core\Models\Permission;

/**
 * عرض كتالوج الصلاحيات المتاحة (RBAC — Issue #12). محميّ بصلاحية roles.manage.
 */
class PermissionController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'module', 'description']),
            'meta' => null,
            'errors' => null,
        ]);
    }
}
