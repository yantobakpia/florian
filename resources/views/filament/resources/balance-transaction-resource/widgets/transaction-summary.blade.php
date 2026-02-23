{{-- resources/views/filament/resources/balance-transaction-resource/widgets/transaction-summary.blade.php --}}

<div class="fi-wi-stats grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <div class="fi-wi-stat overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-x-4">
            <div class="fi-wi-stat-icon-ctn rounded-lg bg-success-50 p-3 dark:bg-success-500/10">
                <x-filament::icon
                    icon="heroicon-m-arrow-down-circle"
                    class="fi-wi-stat-icon h-6 w-6 text-success-600 dark:text-success-400"
                />
            </div>
            <div class="flex-1">
                <p class="fi-wi-stat-value text-2xl font-semibold text-gray-950 dark:text-white">
                    Rp {{ number_format($today_income, 0, ',', '.') }}
                </p>
                <p class="fi-wi-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Pemasukan {{ $transaction_date }}
                </p>
            </div>
        </div>
    </div>

    <div class="fi-wi-stat overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-x-4">
            <div class="fi-wi-stat-icon-ctn rounded-lg bg-danger-50 p-3 dark:bg-danger-500/10">
                <x-filament::icon
                    icon="heroicon-m-arrow-up-circle"
                    class="fi-wi-stat-icon h-6 w-6 text-danger-600 dark:text-danger-400"
                />
            </div>
            <div class="flex-1">
                <p class="fi-wi-stat-value text-2xl font-semibold text-gray-950 dark:text-white">
                    Rp {{ number_format($today_expense, 0, ',', '.') }}
                </p>
                <p class="fi-wi-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Pengeluaran {{ $transaction_date }}
                </p>
            </div>
        </div>
    </div>

    <div class="fi-wi-stat overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-x-4">
            <div class="fi-wi-stat-icon-ctn rounded-lg bg-primary-50 p-3 dark:bg-primary-500/10">
                <x-filament::icon
                    icon="heroicon-m-banknotes"
                    class="fi-wi-stat-icon h-6 w-6 text-primary-600 dark:text-primary-400"
                />
            </div>
            <div class="flex-1">
                <p class="fi-wi-stat-value text-2xl font-semibold text-gray-950 dark:text-white">
                    Rp {{ number_format($today_net, 0, ',', '.') }}
                </p>
                <p class="fi-wi-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Net Income {{ $transaction_date }}
                </p>
            </div>
        </div>
    </div>

    <div class="fi-wi-stat overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center gap-x-4">
            <div class="fi-wi-stat-icon-ctn rounded-lg bg-gray-50 p-3 dark:bg-gray-500/10">
                <x-filament::icon
                    icon="heroicon-m-document-text"
                    class="fi-wi-stat-icon h-6 w-6 text-gray-600 dark:text-gray-400"
                />
            </div>
            <div class="flex-1">
                <p class="fi-wi-stat-value text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $today_count }}
                </p>
                <p class="fi-wi-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Transaksi {{ $transaction_date }}
                </p>
            </div>
        </div>
    </div>
</div>