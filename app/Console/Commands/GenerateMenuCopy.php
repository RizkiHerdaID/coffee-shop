<?php

namespace App\Console\Commands;

use App\Exceptions\MissingAiKeyException;
use App\Models\MenuItem;
use App\Services\AiCopyService;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateMenuCopy extends Command
{
    protected $signature = 'menu:generate-copy';

    public function __construct()
    {
        parent::__construct();

        $this->description = __('ai-copy.command.description');
    }

    public function handle(AiCopyService $aiCopy): int
    {
        if (blank(config('services.deepseek.api_key'))) {
            $this->error(__('ai-copy.command.no_key'));

            return self::FAILURE;
        }

        $items = MenuItem::query()
            ->where(fn ($query) => $query->whereNull('note')->orWhere('note', ''))
            ->orderBy('sort_order')
            ->get();

        if ($items->isEmpty()) {
            $this->info(__('ai-copy.command.nothing_to_do'));

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($items as $item) {
            try {
                $note = $aiCopy->generateDescription($item->name, $item->price);
                $item->update(['note' => $note]);
                $this->info(__('ai-copy.command.ok', ['name' => $item->name]));
            } catch (MissingAiKeyException) {
                $this->error(__('ai-copy.command.skipped_no_key', ['name' => $item->name]));
                $failed++;
            } catch (RuntimeException $exception) {
                $this->error(__('ai-copy.command.failed', [
                    'name' => $item->name,
                    'reason' => $exception->getMessage(),
                ]));
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
