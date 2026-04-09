<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UserManager extends Component
{
    public array $users = [];
    public string $name = '';
    public string $username = '';
    public string $password = '';
    public string $role = 'cashier';
    public string $managerPin = '';
    public bool $isActive = true;
    public ?int $editingUserId = null;

    public ?string $message = null;
    public ?string $error = null;

    public array $availableRoles = ['cashier', 'kitchen', 'manager', 'administrator'];

    public function mount(): void
    {
        $this->loadUsers();
    }

    public function saveUser(): void
    {
        $this->error = null;
        $this->message = null;

        $name = trim($this->name);
        $username = trim($this->username);

        if ($name === '' || $username === '') {
            $this->error = 'Name and Username are required.';
            return;
        }

        if (!$this->editingUserId && $this->password === '') {
            $this->error = 'Password is required for new users.';
            return;
        }

        if (!in_array($this->role, $this->availableRoles, true)) {
            $this->error = 'Invalid role selected.';
            return;
        }

        // Uniqueness check
        $existing = DB::table('users')->where('username', $username);
        if ($this->editingUserId) {
            $existing->where('id', '!=', $this->editingUserId);
        }
        if ($existing->exists()) {
            $this->error = 'Username already taken.';
            return;
        }

        $data = [
            'name' => $name,
            'username' => $username,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'updated_at' => now(),
        ];

        if ($this->managerPin !== '') {
            // Only manager/administrator roles may have a manager PIN
            if (!in_array($this->role, ['manager', 'administrator'], true)) {
                $this->error = 'Manager PIN can only be assigned to manager or administrator roles.';
                return;
            }
            $data['manager_pin'] = Hash::make($this->managerPin);
        } elseif (!$this->editingUserId) {
            $data['manager_pin'] = null;
        }

        if ($this->password !== '') {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingUserId) {
            DB::table('users')->where('id', $this->editingUserId)->update($data);
            $this->message = 'User updated.';
        } else {
            $data['created_at'] = now();
            DB::table('users')->insert($data);
            $this->message = 'User created.';
        }

        $this->resetForm();
        $this->loadUsers();
    }

    public function editUser(int $id): void
    {
        $user = DB::table('users')->find($id);
        if (!$user) return;

        $this->editingUserId = $id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->password = '';
        $this->role = $user->role;
        $this->managerPin = '';  // Never expose the hash; leave blank to keep existing PIN
        $this->isActive = (bool) $user->is_active;
    }

    public function toggleActive(int $id): void
    {
        $user = DB::table('users')->find($id);
        if (!$user) return;

        // Prevent self-deactivation
        if ($id === (int) auth()->id()) {
            $this->error = 'You cannot deactivate your own account.';
            return;
        }

        DB::table('users')->where('id', $id)->update([
            'is_active' => !$user->is_active,
            'updated_at' => now(),
        ]);

        $this->message = $user->is_active ? 'User deactivated.' : 'User activated.';
        $this->loadUsers();
    }

    public function resetForm(): void
    {
        $this->editingUserId = null;
        $this->name = '';
        $this->username = '';
        $this->password = '';
        $this->role = 'cashier';
        $this->managerPin = '';
        $this->isActive = true;
    }

    private function loadUsers(): void
    {
        $this->users = DB::table('users')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'role', 'is_active', 'created_at'])
            ->map(fn ($u) => (array) $u)
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.user-manager');
    }
}
