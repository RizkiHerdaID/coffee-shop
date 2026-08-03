<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\Admin;
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
                'category' => 'coffee',
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

    public function test_create_form_stores_badges_array(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Kopi Susu',
                'price' => '25.000',
                'category' => 'coffee',
                'badges' => ['vegan', 'spicy'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MenuItem::where('name', 'Kopi Susu')->firstOrFail();

        $this->assertSame(['vegan', 'spicy'], $item->badges);
    }

    public function test_edit_form_prefills_stored_badges(): void
    {
        $item = MenuItem::create(['name' => 'Cold Brew', 'price' => 38000, 'badges' => ['halal']]);

        Livewire::test(EditMenuItem::class, ['record' => $item->getKey()])
            ->assertFormSet(['badges' => ['halal']]);
    }

    public function test_create_form_shows_localized_badge_options(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->assertSee(__('menu.badges.vegan'))
            ->assertSee(__('menu.badges.spicy'))
            ->assertSee(__('menu.badges.gluten_free'))
            ->assertSee(__('menu.badges.halal'));
    }

    public function test_create_form_allows_creating_item_without_badges(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Espresso',
                'price' => '25.000',
                'category' => 'coffee',
                'badges' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MenuItem::where('name', 'Espresso')->firstOrFail();

        $this->assertEmpty($item->badges ?? []);
    }

    // ---------------------------------------------------------------------
    // Resource hardening (Vikunja 160): category is required, and every
    // menu-item table column shows its localized label.
    // ---------------------------------------------------------------------

    public function test_create_form_requires_category(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Teh Tarik',
                'price' => '15.000',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['category']);

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_menu_items_table_renders_localized_column_labels(): void
    {
        $admin = Admin::factory()->create();
        MenuItem::create(['name' => 'Cappuccino', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(ListMenuItems::class);

        $expected = [
            'name' => __('menu-items.fields.name'),
            'price' => __('menu-items.fields.price'),
            'note' => __('menu-items.fields.note'),
            'sort_order' => __('menu-items.fields.sort_order'),
            'created_at' => __('menu-items.fields.created_at'),
            'updated_at' => __('menu-items.fields.updated_at'),
        ];

        foreach ($expected as $column => $label) {
            $this->assertSame($label, $component->instance()->getTable()->getColumn($column)->getLabel());
        }
    }
}
