<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use App\Models\User;
use App\Models\Shop;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

layout('components.layouts.app');
title('Utilisateurs');

state(['showModal' => false]);
state(['showPasswordModal' => false]);
state(['editingUser' => null]);
state(['passwordUser' => null]);
state(['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => '', 'shop_id' => '', 'roles' => []]);

$users = computed(fn () => User::query()->with(['roles', 'shop'])->paginate(10));

$create = function () {
    $this->reset(['name', 'email', 'password', 'password_confirmation', 'shop_id', 'roles', 'editingUser', 'passwordUser']);
    $this->showModal = true;
};

$edit = function (User $user) {
    $this->editingUser = $user;
    $this->name = $user->name;
    $this->email = $user->email;
    $this->password = '';
    $this->password_confirmation = '';
    $this->shop_id = $user->shop_id;
    $this->roles = $user->roles()->pluck('name')->toArray();
    $this->showModal = true;
};

$openPasswordModal = function (User $user) {
    $this->passwordUser = $user;
    $this->password = '';
    $this->password_confirmation = '';
    $this->showPasswordModal = true;
};

$savePassword = function () {
    $this->validate([
        'password' => 'required|string|min:6|confirmed',
    ]);

    if ($this->passwordUser) {
        $this->passwordUser->update([
            'password' => bcrypt($this->password),
        ]);
    }

    $this->showPasswordModal = false;
    $this->reset(['password', 'password_confirmation', 'passwordUser']);
};

$save = function () {
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email' . ($this->editingUser ? ',' . $this->editingUser->id : ''),
        'shop_id' => 'nullable|exists:shops,id',
        'roles' => 'array',
    ];

    $validated = $this->validate($rules);

    if ($this->editingUser) {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'shop_id' => $this->shop_id ?: null,
        ];
        $this->editingUser->update($data);

        if (class_exists(Role::class)) {
            $this->editingUser->syncRoles($this->roles ?? []);
        }
    } else {
        $user = User::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'shop_id' => $this->shop_id ?: null,
            // assign a random temporary password (to be changed via password action)
            'password' => bcrypt(Str::random(24)),
        ]);

        if (class_exists(Role::class)) {
            $user->syncRoles($this->roles ?? []);
        }
    }

    $this->showModal = false;
    $this->reset(['name', 'email', 'password', 'password_confirmation', 'shop_id', 'roles', 'editingUser', 'passwordUser']);
};

$delete = function (User $user) {
    if (auth()->id() !== $user->id) {
        $user->delete();
    }
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Utilisateurs</h1>
        <flux:button wire:click="create">Ajouter un utilisateur</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nom</flux:table.column>
            <flux:table.column>Nom d'utilisateur</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Rôles</flux:table.column>
            <flux:table.column>Boutique</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->username ?? '' }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->roles->pluck('name')->join(', ') }}</flux:table.cell>
                    <flux:table.cell>{{ $user->shop?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="space-x-1">
                        <flux:button variant="ghost" size="sm" wire:click="edit({{ $user->id }})">Modifier</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="openPasswordModal({{ $user->id }})">Définir le mot de passe</flux:button>

                                <flux:button variant="ghost" size="sm" wire:click="delete({{ $user->id }})">Supprimer</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->users->links() }}
    </div>

    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save" class="space-y-4">
            <h3>{{ $editingUser ? 'Modifier' : 'Créer' }} un utilisateur</h3>

            <div>
                <flux:label>Nom</flux:label>
                <flux:input wire:model="name" required />
                @error('name') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div>
                <flux:label>E-mail</flux:label>
                <flux:input wire:model="email" type="email" required />
                @error('email') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div>
                <flux:label>Boutique</flux:label>
                <flux:select wire:model="shop_id">
                    <option value="">-- Aucune boutique assignée --</option>
                    @foreach(App\Models\Shop::all() as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                    @endforeach
                </flux:select>
                @error('shop_id') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div>
                <flux:label>Rôles</flux:label>
                <flux:select wire:model="roles" multiple>
                    @foreach(Spatie\Permission\Models\Role::all() as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </flux:select>
                @error('roles') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showPasswordModal">
        <form wire:submit.prevent="savePassword" class="space-y-4">
            <h3>Définir le mot de passe de l'utilisateur</h3>

            <div>
                <flux:label>Mot de passe</flux:label>
                <flux:input wire:model="password" type="password" required placeholder="Saisissez un mot de passe" autocomplete="new-password" />
                @error('password') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div>
                <flux:label>Confirmer le mot de passe</flux:label>
                <flux:input wire:model="password_confirmation" type="password" required placeholder="Confirmez le mot de passe" autocomplete="new-password" />
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showPasswordModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
