<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription overview') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($subscription === null)
                        <p class="text-sm text-gray-600">No active subscription found yet.</p>
                    @else
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Plan</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($subscription->plan->value) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Box size</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($subscription->box_size) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <x-status-badge :value="$subscription->status" type="subscription" />
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Preferences</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if (! empty($subscription->preferences))
                                        {{ json_encode($subscription->preferences, JSON_UNESCAPED_SLASHES) }}
                                    @else
                                        No preferences saved.
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Allergies</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if (! empty($subscription->allergies))
                                        {{ implode(', ', $subscription->allergies) }}
                                    @else
                                        No allergies listed.
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Upcoming billing date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $billingDatePlaceholder }}</dd>
                            </div>
                        </dl>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
