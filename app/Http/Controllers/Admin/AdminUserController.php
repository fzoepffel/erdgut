<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUsersIndexRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;

class AdminUserController extends Controller
{
    public function index(AdminUsersIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = User::query()->select(['id', 'email', 'email_verified_at', 'created_at']);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where('email', 'like', '%'.$escaped.'%');
        }

        $users = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.operations.users.index', [
            'users' => $users,
        ]);
    }
}
