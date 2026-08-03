<?php

namespace Tests\Feature;

use App\Exceptions\MissingAiKeyException;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Models\MenuItem;
use App\Services\AiCopyService;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
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

        // Bind a service that fails immediately: the command's error path
        // is what is under test here; the real retry/exception conversion
        // is covered by the service-level tests below (and would otherwise
        // sleep 1s per retry for all 8 items).
        $this->app->instance(AiCopyService::class, new class extends AiCopyService
        {
            public function generateDescription(string $menuName, ?int $priceIdr = null): string
            {
                throw new RuntimeException('DeepSeek API request failed with status 500');
            }
        });

        $this->artisan('menu:generate-copy')
            ->expectsOutputToContain(__('ai-copy.command.failed', ['name' => 'Espresso', 'reason' => '']))
            ->assertExitCode(1);

        $this->assertSame(0, MenuItem::query()->whereNotNull('note')->count());
    }

    // ---------------------------------------------------------------------
    // DeepSeek response handling (Vikunja 127): thinking mode can return
    // the content as an ARRAY of part objects, which must be joined (never
    // string-cast); server errors and timeouts are retried with backoff and
    // converted to a RuntimeException once the retries are exhausted.
    // ---------------------------------------------------------------------

    public function test_generate_description_joins_array_content_parts(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => [
                        ['type' => 'text', 'text' => 'Bagian satu.'],
                        ['type' => 'text', 'text' => 'Bagian dua.'],
                    ]],
                ]],
            ]),
        ]);

        $description = app(AiCopyService::class)->generateDescription('Espresso', 25000);

        $this->assertSame('Bagian satu.Bagian dua.', $description);
    }

    public function test_generate_description_joins_mixed_string_and_array_content_parts(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => [
                        'Teks polos.',
                        ['type' => 'text', 'text' => ' dan bagian kedua.'],
                    ]],
                ]],
            ]),
        ]);

        $description = app(AiCopyService::class)->generateDescription('Espresso', 25000);

        $this->assertSame('Teks polos. dan bagian kedua.', $description);
    }

    public function test_generate_description_retries_server_error_then_succeeds(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fakeSequence('https://api.deepseek.com/chat/completions')
            ->pushStatus(500)
            ->push([
                'choices' => [['message' => ['content' => 'Retry sukses.']]],
            ]);

        $description = app(AiCopyService::class)->generateDescription('Espresso', 25000);

        $this->assertSame('Retry sukses.', $description);
        Http::assertSentCount(2);
    }

    public function test_generate_description_throws_runtime_exception_after_retries_exhausted(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([], 500),
        ]);

        try {
            app(AiCopyService::class)->generateDescription('Espresso', 25000);
            $this->fail('Expected a RuntimeException once the retries are exhausted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('500', $exception->getMessage());
        }

        Http::assertSentCount(2);
    }

    public function test_generate_description_throws_runtime_exception_on_timeout(): void
    {
        config()->set('services.deepseek.api_key', 'test-key');

        $attempts = 0;
        Http::fake([
            'https://api.deepseek.com/chat/completions' => function () use (&$attempts) {
                $attempts++;
                throw new ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        try {
            app(AiCopyService::class)->generateDescription('Espresso', 25000);
            $this->fail('Expected a RuntimeException on timeout.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('timed out', $exception->getMessage());
        }

        $this->assertSame(2, $attempts);
    }
}
