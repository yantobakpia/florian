<x-filament::section>
    <x-slot name="heading">
        User Activity
    </x-slot>

    <x-slot name="description">
        Recent activities and login information
    </x-slot>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
                <p class="text-sm font-medium text-gray-500">Last Login</p>
                <p class="text-lg font-semibold">{{ $this->getActivityData()['last_login'] }}</p>
            </div>
            <div class="space-y-1">
                <p class="text-sm font-medium text-gray-500">Total Logins</p>
                <p class="text-lg font-semibold">{{ $this->getActivityData()['login_count'] }}</p>
            </div>
        </div>

        @if(count($this->getActivityData()['activities']) > 0)
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-500">Recent Activities</p>
                <div class="space-y-2">
                    @foreach($this->getActivityData()['activities'] as $activity)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium">{{ $activity->description }}</p>
                                <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                {{ $activity->log_name }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <p>No recent activities found</p>
            </div>
        @endif
    </div>
</x-filament::section>