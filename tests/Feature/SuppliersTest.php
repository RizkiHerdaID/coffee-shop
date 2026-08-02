<?php

namespace Tests\Feature;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuppliersTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_stores_supplier(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->fillForm([
                'name' => 'PT Kopi Nusantara',
                'contact_person' => 'Budi',
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'address' => 'Jl. Merdeka 1',
                'note' => 'Supplier utama',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);
    }

    public function test_create_form_requires_name(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    public function test_edit_form_prefills_values(): void
    {
        $admin = Admin::factory()->create();
        $supplier = Supplier::create([
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditSupplier::class, ['record' => $supplier->getKey()])
            ->assertFormSet([
                'name' => 'PT Kopi Nusantara',
                'email' => 'budi@example.com',
            ]);
    }

    public function test_create_form_shows_localized_labels(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->assertSee(__('suppliers.fields.name'))
            ->assertSee(__('suppliers.fields.contact_person'))
            ->assertSee(__('suppliers.fields.phone'))
            ->assertSee(__('suppliers.fields.email'))
            ->assertSee(__('suppliers.fields.address'))
            ->assertSee(__('suppliers.fields.note'));
    }
}
