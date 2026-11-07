<?php

namespace App\Livewire\Manage;

use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('สมาชิก')]
class UsersPage extends Component
{
    public function mount(): void
    {
        $this->authorize('canManageUsers');
    }

    public function render()
    {
        return view('livewire.manage.users-page', [
            'users' => User::with('roles')->orderBy('created_at')->orderBy('id')->get(),
        ]);
    }
}
