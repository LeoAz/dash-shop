<?php

use function Livewire\Volt\layout;
use function Livewire\Volt\title;
use function Livewire\Volt\state;
use function Livewire\Volt\computed;
use Spatie\Permission\Models\Role;

layout('components.layouts.app');
title('Rôles');

state(['showModal' => false]);
state(['editingRole' => null]);
state(['name' => '']);

$roles = computed(fn () => Role::query()->paginate(10));

$create = function () {
    $this->reset(['name', 'editingRole']);
    $this->showModal = true;
};

$edit = function (Role $role) {
    $this->editingRole = $role;
    $this->name = $role->name;
    $this->showModal = true;
};

$save = function () {
    $this->validate([
        'name' => 'required|string|max:255|unique:roles,name' . ($this->editingRole ? ',' . $this->editingRole->id : ''),
    ]);

    if ($this->editingRole) {
        $this->editingRole->update(['name' => $this->name]);
    } else {
        Role::create(['name' => $this->name]);
    }

    $this->showModal = false;
    $this->reset(['name', 'editingRole']);
};

$delete = function (Role $role) {
    // prevent deleting a role in use? keep simple for now
    $role->delete();
};

?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Rôles</h1>
        <flux:button wire:click="create">Ajouter un rôle</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nom</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->roles as $role)
                <flux:table.row wire:key="role-{{ $role->id }}">
                    <flux:table.cell>{{ $role->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button variant="ghost" size="sm" wire:click="edit({{ $role->id }})">Modifier</flux:button>
                        @if(auth()->user()->hasRole('admin'))
                            <flux:button variant="ghost" size="sm" wire:click="delete({{ $role->id }})">Supprimer</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->roles->links() }}
    </div>

    <flux:modal wire:model="showModal">
        <form wire:submit.prevent="save" class="space-y-4">
            <h3>{{ $editingRole ? 'Modifier' : 'Créer' }} un rôle</h3>

            <div>
                <flux:label>Nom</flux:label>
                <flux:input wire:model="name" required />
                @error('name') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
