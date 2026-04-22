<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUsersIndexRequest;
use App\Http\Requests\Admin\AdminUsersStoreAdminRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index(AdminUsersIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = User::query()->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at']);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where('email', 'like', '%'.$escaped.'%');
        }

        $users = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.operations.users.index', [
            'users' => $users,
            'adminCount' => (int) User::query()->where('is_admin', true)->count(),
        ]);
    }

    public function storeAdmin(AdminUsersStoreAdminRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();

        /** @var User $user */
        $user = DB::transaction(function () use ($validated): User {
            $u = User::create([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'password' => (string) $validated['password'],
                'is_test' => true,
            ]);
            $u->forceFill(['is_admin' => true])->save();

            return $u;
        });

        return redirect()
            ->route('app.admin.users.index')
            ->with('status', __('admin.users.flash_admin_created', ['email' => $user->email]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('admin');

        $current = request()->user();
        if ($current && (int) $current->id === (int) $user->id) {
            return back()->with('status', __('admin.users.flash_cannot_delete_self'));
        }

        if ((bool) $user->is_admin) {
            $adminCount = (int) User::query()->where('is_admin', true)->count();
            if ($adminCount <= 1) {
                return back()->with('status', __('admin.users.flash_cannot_delete_last_admin'));
            }
        }

        $user->delete();

        return redirect()
            ->route('app.admin.users.index')
            ->with('status', __('admin.users.flash_deleted'));
    }
}
