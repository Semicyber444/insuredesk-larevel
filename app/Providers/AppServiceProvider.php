<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Component updates re-run this too, so a deactivated user's open page stops working at once.
        Livewire::addPersistentMiddleware([\App\Http\Middleware\EnsureActiveUser::class]);

        // Every permission in config/insuredesk.php becomes a gate: @can('canReview'), ->can('canReview').
        $permissions = config('insuredesk.permissions');
        foreach (array_unique(array_merge(...array_values($permissions))) as $permission) {
            Gate::define($permission, fn (User $user) => collect($user->roleNames())
                ->contains(fn ($role) => in_array($permission, $permissions[$role] ?? [], true)));
        }
    }
}
