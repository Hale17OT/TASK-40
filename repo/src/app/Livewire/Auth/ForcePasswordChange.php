<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ForcePasswordChange extends Component
{
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';
    public string $new_manager_pin = '';

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_manager_pin' => 'nullable|string|digits:4',
        ]);

        $user = auth()->user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        $updateData = [
            'password' => Hash::make($this->new_password),
            'force_password_change' => false,
            'updated_at' => now(),
        ];

        if ($this->new_manager_pin !== '' && $user->manager_pin !== null) {
            $updateData['manager_pin'] = Hash::make($this->new_manager_pin);
        }

        DB::table('users')->where('id', $user->id)->update($updateData);

        $redirect = match ($user->role) {
            'administrator' => '/admin/dashboard',
            default => '/staff/orders',
        };

        $this->redirect($redirect, navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.force-password-change')
            ->layout('components.layouts.kiosk', ['title' => 'Change Password']);
    }
}
