<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\User;
use App\Services\Admin\PermissionMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reads and writes the role → capability grid. All routes sit behind
 * can:manage_restaurant_config. Changes reach a role's sidebar on its next
 * sign-in, through the capability snapshot taken at login (F7).
 */
class PermissionMatrixController extends Controller
{
    public function index(Request $request, PermissionMatrix $matrix): JsonResponse
    {
        return response()->json($matrix->for($this->actor($request)));
    }

    public function update(
        UpdatePermissionRequest $request,
        PermissionMatrix $matrix,
    ): RedirectResponse {
        $changed = $matrix->set(
            $this->actor($request),
            $request->role(),
            $request->capability(),
            $request->granted(),
        );

        return back()->with(
            'success',
            $changed ? 'Permission updated.' : 'Nothing to change.',
        );
    }

    public function audits(Request $request, PermissionMatrix $matrix): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 25)));

        return response()->json(['audits' => $matrix->recentAudits($limit)]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
