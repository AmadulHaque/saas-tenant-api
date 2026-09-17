<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Users', description: 'Tenant user management (owner/admin).')]
class DestroyController extends Controller
{
    /**
     * Soft-delete a user of the authenticated user's company.
     */
    public function __invoke(Request $request, int $id): Response
    {
        $user = User::query()
            ->forCompany($request->user('api')->company_id)
            ->find($id);

        abort_if($user === null, 404);

        $this->authorize('delete', $user);

        $user->tokens()->update(['revoked' => true]);
        $user->delete();

        return response()->noContent();
    }
}
