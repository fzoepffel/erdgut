@php
    $hasFilters = filled(trim((string) request('q')));
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.users_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif

            <section class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="space-y-4 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.users.create_admin_title') }}</h3>
                    <form method="post" action="{{ route('app.admin.users.store-admin') }}" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                        @csrf
                        <div class="sm:col-span-1">
                            <label for="create-admin-name" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Name') }}</label>
                            <input id="create-admin-name" name="name" value="{{ old('name') }}" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label for="create-admin-email" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Email') }}</label>
                            <input id="create-admin-email" type="email" name="email" value="{{ old('email') }}" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label for="create-admin-password" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Password') }}</label>
                            <input id="create-admin-password" type="password" name="password" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                                {{ __('admin.users.create_admin_cta') }}
                            </button>
                        </div>
                    </form>
                    <p class="text-xs text-gray-500">{{ __('admin.users.create_admin_hint') }}</p>
                </div>
            </section>

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.users.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="min-w-0 flex-1 sm:min-w-[12rem]">
                            <label for="admin-users-q" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.filter_email') }}</label>
                            <input
                                id="admin-users-q"
                                type="search"
                                name="q"
                                value="{{ old('q', request('q')) }}"
                                maxlength="255"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                {{ __('admin.catalog.apply_filters') }}
                            </button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($users->isEmpty())
                        @if ($hasFilters)
                            <p class="text-sm text-gray-600">{{ __('admin.operations.users_no_match') }}</p>
                        @else
                            <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                                <p class="text-sm font-medium text-gray-900">{{ __('admin.operations.users_empty') }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ __('admin.operations.users_empty_hint') }}</p>
                            </div>
                        @endif
                    @else
                        <div
                            class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"
                            x-data="{ deleteOpen: false, deleteUserId: null, deleteEmail: '' }"
                        >
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.users_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.users.col_role') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Email') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.email_verified') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.created_at') }}</th>
                                        <th scope="col" class="py-2 ps-4 text-right">{{ __('admin.users.col_actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($users as $user)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $user->id }}</td>
                                            <td class="py-3 pe-4">
                                                @if ($user->is_admin)
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">{{ __('admin.users.role_admin') }}</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ __('admin.users.role_user') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4">
                                                <div class="font-medium text-gray-900">{{ $user->email }}</div>
                                                <div class="text-xs text-gray-500">{{ $user->name }}</div>
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                {{ $user->email_verified_at ? $user->email_verified_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}
                                            </td>
                                            <td class="py-3 pe-0 text-gray-600">{{ $user->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                            <td class="py-3 ps-4 text-right">
                                                @php
                                                    $isSelf = auth()->id() === $user->id;
                                                    $isLastAdmin = $user->is_admin && (($adminCount ?? 0) <= 1);
                                                    $canDelete = ! $isSelf && ! $isLastAdmin;
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold shadow-sm {{ $canDelete ? 'bg-rose-600 text-white hover:bg-rose-700' : 'bg-gray-100 text-gray-400' }}"
                                                    @if (! $canDelete) disabled @endif
                                                    @click="deleteUserId={{ $user->id }}; deleteEmail='{{ e($user->email) }}'; deleteOpen=true"
                                                >
                                                    {{ __('admin.users.delete_cta') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div
                                x-show="deleteOpen"
                                x-transition.opacity
                                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
                                @click.self="deleteOpen=false"
                                style="display:none;"
                            >
                                <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                                    <div class="border-b border-gray-100 p-4">
                                        <h4 class="text-sm font-semibold text-gray-900">{{ __('admin.users.delete_modal_title') }}</h4>
                                        <p class="mt-1 text-sm text-gray-600">{{ __('admin.users.delete_modal_body') }}</p>
                                        <p class="mt-1 text-sm font-medium text-gray-900" x-text="deleteEmail"></p>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 p-4">
                                        <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="deleteOpen=false">
                                            {{ __('Cancel') }}
                                        </button>
                                        <form method="post" :action="'{{ url('/app/admin/users') }}/'+deleteUserId">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                                                {{ __('admin.users.delete_confirm_cta') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-2">{{ $users->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
