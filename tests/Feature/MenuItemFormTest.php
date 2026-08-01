<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuItemFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_stores_masked_price_as_raw_integer(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Kopi Susu',
                'price' => '25.000',
                'note' => 'Manisnya pas',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Kopi Susu',
            'price' => 25000,
        ]);
    }

    public function test_edit_form_prefills_stored_price_with_mask_format(): void
    {
        $item = MenuItem::create(['name' => 'Cappuccino', 'price' => 25000]);

        Livewire::test(EditMenuItem::class, ['record' => $item->getKey()])
            ->assertFormSet(['price' => '25.000']);
    }

    public function test_create_form_shows_localized_field_labels(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->assertSee(__('menu-items.fields.name'))
            ->assertSee(__('menu-items.fields.price'))
            ->assertSee(__('menu-items.fields.note'))
            ->assertSee(__('menu-items.fields.sort_order'));
    }
}
