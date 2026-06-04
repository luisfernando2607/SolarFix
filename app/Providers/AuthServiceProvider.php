<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => in_array($user->role, ['super_admin', 'admin']));
        Gate::define('manage-catalogs', fn (User $user) => in_array($user->role, ['super_admin', 'admin']));
        Gate::define('manage-orders', fn (User $user) => in_array($user->role, ['super_admin', 'admin', 'technician', 'receptionist']));
        Gate::define('manage-payments', fn (User $user) => in_array($user->role, ['super_admin', 'admin']));
        Gate::define('view-dashboard', fn (User $user) => in_array($user->role, ['super_admin', 'admin', 'technician', 'receptionist']));
    }
}
