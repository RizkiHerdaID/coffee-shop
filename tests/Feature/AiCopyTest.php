<?php

namespace Tests\Feature;

use App\Exceptions\MissingAiKeyException;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Models\MenuItem;
use App\Services\AiCopyService;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
    }

    public function test_generate_description_calls_deepseek_and_parses_response_content(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Deskripsi kopi yang lezat.']]],
            ]),
        ]);

        $description = app(AiCopyService::class)->generateDescription('Espresso', 25000);

        $this->assertSame('Deskripsi kopi yang lezat.', $description);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === config('services.deepseek.model')
                && $request['thinking'] === ['type' => 'disabled']
                && str_contains($request['messages'][1]['content'], 'Espresso')
                && str_contains($request['messages'][1]['content'], 'Rp 25.000');
        });
    }

    public function test_generate_description_throws_without_api_key(): void
    {
        config()->set('services.deepseek.api_key', null);

        $this->expectException(MissingAiKeyException::class);

        app(AiCopyService::class)->generateDescription('Espresso', 25000);
    }

    public function test_command_fills_empty_notes_when_api_key_is_set(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        MenuItem::query()->update(['note' => null]);

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Deskripsi otomatis.']]],
            ]),
        ]);

        $this->artisan('menu:generate-copy')
            ->expectsOutputToContain('Espresso -> ok')
            ->assertExitCode(0);

        $this->assertDatabaseHas('menu_items', ['name' => 'Espresso', 'note' => 'Deskripsi otomatis.']);
        $this->assertSame(8, MenuItem::query()->whereNotNull('note')->count());
        Http::assertSentCount(8);
    }

    public function test_command_skips_items_that_already_have_notes(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Deskripsi otomatis.']]],
            ]),
        ]);

        $this->artisan('menu:generate-copy')
            ->expectsOutputToContain(__('ai-copy.command.nothing_to_do'))
            ->assertExitCode(0);

        Http::assertNothingSent();

        $this->assertDatabaseHas('menu_items', ['name' => 'Espresso', 'note' => 'Double shot, crema pekat']);
    }

    public function test_command_exits_one_gracefully_without_api_key(): void
    {
        config()->set('services.deepseek.api_key', null);

        $this->artisan('menu:generate-copy')
            ->expectsOutputToContain('DEEPSEEK_API_KEY')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_menu_form_action_generates_note_from_name(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Deskripsi otomatis.']]],
            ]),
        ]);

        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Avocado Latte',
                'price' => '45.000',
            ])
            ->callFormComponentAction('note', 'generateAiNote')
            ->assertFormSet(['note' => 'Deskripsi otomatis.']);

        Http::assertSent(function ($request) {
            return str_contains($request['messages'][1]['content'], 'Avocado Latte')
                && str_contains($request['messages'][1]['content'], 'Rp 45.000');
        });
    }

    public function test_menu_form_action_without_name_does_not_call_api(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake();

        Livewire::test(CreateMenuItem::class)
            ->callFormComponentAction('note', 'generateAiNote');

        Http::assertNothingSent();
    }

    public function test_menu_form_action_is_hidden_without_api_key(): void
    {
        config()->set('services.deepseek.api_key', null);

        Livewire::test(CreateMenuItem::class)
            ->assertFormComponentActionHidden('note', 'generateAiNote');

        Http::assertNothingSent();
    }

    public function test_command_marks_item_failed_and_exits_one_when_api_errors(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        MenuItem::query()->update(['note' => null]);

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([], 500),
        ]);

        $this->artisan('menu:generate-copy')
            ->expectsOutputToContain(__('ai-copy.command.failed', ['name' => 'Espresso', 'reason' => '']))
            ->assertExitCode(1);

        $this->assertSame(0, MenuItem::query()->whereNotNull('note')->count());
    }
}
