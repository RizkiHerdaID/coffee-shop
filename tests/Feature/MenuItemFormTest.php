<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_create_form_stores_category_and_available(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Matcha Latte',
                'price' => '30.000',
                'category' => 'coffee',
                'available' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Matcha Latte',
            'category' => 'coffee',
            'available' => true,
        ]);
    }

    public function test_edit_form_prefills_stored_photo_path(): void
    {
        Storage::disk('public')->put('menu/cappuccino.jpg', 'fake image');

        $item = MenuItem::create([
            'name' => 'Cappuccino',
            'price' => 25000,
            'photo' => 'menu/cappuccino.jpg',
        ]);

        Livewire::test(EditMenuItem::class, ['record' => $item->getKey()])
            ->assertFormSet(['photo' => 'menu/cappuccino.jpg']);
    }

    public function test_create_form_shows_localized_media_field_labels(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->assertSee(__('menu-items.fields.photo'))
            ->assertSee(__('menu-items.fields.category'))
            ->assertSee(__('menu-items.fields.available'))
            ->assertSee(__('menu.categories.coffee'))
            ->assertSee(__('menu.categories.non-coffee'))
            ->assertSee(__('menu.categories.snack'))
            ->assertSee(__('menu.categories.food'));
    }
}
