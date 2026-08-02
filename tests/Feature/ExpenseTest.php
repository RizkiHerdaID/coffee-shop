<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Models\Admin;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_stores_amount_as_raw_integer_from_indonesian_formatted_input(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Ingredients,
                'description' => 'Kopi robusta 1kg',
                'amount' => '25.000',
                'spent_at' => '2026-08-02',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Kopi robusta 1kg',
            'amount' => 25000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Ingredients,
                'description' => 'Susu UHT 10 karton',
                'amount' => '1.500.000',
                'spent_at' => '2026-08-02',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Susu UHT 10 karton',
            'amount' => 1500000,
        ]);
    }

    public function test_edit_form_displays_amount_with_indonesian_separators(): void
    {
        $admin = Admin::factory()->create();
        $expense = Expense::create([
            'category' => ExpenseCategory::Equipment,
            'description' => 'Mesin grinder',
            'amount' => 1500000,
            'spent_at' => '2026-08-02',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditExpense::class, ['record' => $expense->getRouteKey()])
            ->assertFormSet(['amount' => '1.500.000']);
    }

    public function test_create_form_requires_category_description_and_amount(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateExpense::class)
            ->fillForm(['spent_at' => '2026-08-02'])
            ->call('create')
            ->assertHasFormErrors(['category', 'description', 'amount']);
    }

    public function test_expense_category_enum_get_label_returns_localized_indonesian(): void
    {
        $this->assertSame('Bahan Baku', ExpenseCategory::Ingredients->getLabel());
        $this->assertSame('Peralatan', ExpenseCategory::Equipment->getLabel());
    }
}
