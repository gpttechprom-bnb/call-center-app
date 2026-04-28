<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CallCenterChecklistStore
{
    private const DEFAULT_ITEM_MAX_POINTS = 10;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->read()['checklists'] ?? null;
        if (! is_array($stored) || $stored === []) {
            return array_map(
                fn (array $checklist): array => $this->normalizeChecklist($checklist, (string) ($checklist['id'] ?? ''), []),
                CallCenterChecklistCatalog::all(),
            );
        }

        $normalized = [];
        foreach ($stored as $checklist) {
            if (! is_array($checklist)) {
                continue;
            }

            $normalized[] = $this->normalizeChecklist($checklist);
        }

        return $normalized !== []
            ? $normalized
            : array_map(
                fn (array $checklist): array => $this->normalizeChecklist($checklist, (string) ($checklist['id'] ?? ''), []),
                CallCenterChecklistCatalog::all(),
            );
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_map(
            static fn (array $checklist): string => (string) $checklist['id'],
            $this->all(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $checklist) {
            if ((string) $checklist['id'] === $id) {
                return $checklist;
            }
        }

        return null;
    }

    public function defaultId(): string
    {
        $checklists = $this->all();

        return (string) ($checklists[0]['id'] ?? CallCenterChecklistCatalog::defaultId());
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function save(array $attributes): array
    {
        $existing = $this->all();
        $requestedId = trim((string) ($attributes['id'] ?? ''));
        $existingIndex = null;

        foreach ($existing as $index => $checklist) {
            if ((string) $checklist['id'] === $requestedId && $requestedId !== '') {
                $existingIndex = $index;
                break;
            }
        }

        $normalized = $this->normalizeChecklist(
            $attributes,
            $requestedId !== '' ? $requestedId : null,
            array_values(array_filter(
                array_map(
                    static fn (array $checklist, int $index): ?string => $index === $existingIndex ? null : (string) $checklist['id'],
                    $existing,
                    array_keys($existing),
                ),
            )),
        );

        if ($existingIndex === null) {
            $existing[] = $normalized;
        } else {
            $existing[$existingIndex] = $normalized;
        }

        $this->write($existing);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicate(string $id): array
    {
        $existing = $this->all();
        $source = $this->find($id);
        if ($source === null) {
            throw new RuntimeException('Чек-лист не знайдено.');
        }

        $sourceIndex = null;
        foreach ($existing as $index => $checklist) {
            if ((string) ($checklist['id'] ?? '') === $id) {
                $sourceIndex = $index;
                break;
            }
        }

        $normalized = $this->normalizeChecklist([
            'name' => $this->buildCopyName((string) ($source['name'] ?? ''), $existing),
            'type' => $source['type'],
            'items' => $source['items'] ?? [],
            'summary' => $source['summary'] ?? '',
            'prompt' => $source['prompt'] ?? '',
        ], null, array_map(
            static fn (array $checklist): string => (string) ($checklist['id'] ?? ''),
            $existing,
        ));

        if ($sourceIndex === null) {
            $existing[] = $normalized;
        } else {
            array_splice($existing, $sourceIndex + 1, 0, [$normalized]);
        }

        $this->write($existing);

        return $normalized;
    }

    public function delete(string $id): void
    {
        $existing = $this->all();
        $remaining = [];
        $deleted = false;

        foreach ($existing as $checklist) {
            if ((string) ($checklist['id'] ?? '') === $id) {
                $deleted = true;
                continue;
            }

            $remaining[] = $checklist;
        }

        if (! $deleted) {
            throw new RuntimeException('Чек-лист не знайдено.');
        }

        $this->write($remaining);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $reservedIds
     * @return array<string, mixed>
     */
    public function normalizeChecklist(array $payload, ?string $preferredId = null, array $reservedIds = []): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $prompt = $this->normalizePrompt((string) ($payload['prompt'] ?? $payload['evaluation_prompt'] ?? ''));
        $rawItems = $payload['items'] ?? $payload['checklist_items'] ?? null;
        $itemsText = $this->normalizeItemsText((string) ($payload['items_text'] ?? $payload['itemsText'] ?? ''));
        $items = is_array($rawItems)
            ? $this->normalizeStructuredItems($rawItems)
            : $this->parseItemsText($itemsText);

        if ($name === '') {
            throw new RuntimeException('Вкажіть назву чек-листа.');
        }

        if ($items === []) {
            throw new RuntimeException('Додайте хоча б один пункт чек-листа.');
        }

        $baseId = $preferredId ?: trim((string) ($payload['id'] ?? ''));
        $baseId = Str::slug($baseId !== '' ? $baseId : $name);
        $id = $this->makeUniqueId($baseId !== '' ? $baseId : 'checklist', $reservedIds);

        return [
            'id' => $id,
            'name' => $name,
            'type' => $type !== '' ? $type : 'Загальний сценарій',
            'prompt' => $prompt,
            'summary' => $this->buildSummary($items),
            'items_text' => $this->buildItemsText($items),
            'items' => $items,
            'total_points' => $this->sumMaxPoints($items),
        ];
    }

    /**
     * @return array<int, array{label:string,max_points:int}>
     */
    public function parseItemsText(string $itemsText): array
    {
        $lines = preg_split('/\r\n|\r|\n/u', $itemsText) ?: [];
        $hasMarkedLines = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:[-*•]|\d+[\.\)])\s+/u', $line) === 1) {
                $hasMarkedLines = true;
                break;
            }
        }

        $items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $isMarked = preg_match('/^\s*(?:[-*•]|\d+[\.\)])\s+/u', $trimmed) === 1;
            if ($hasMarkedLines && ! $isMarked) {
                continue;
            }

            $normalized = trim(preg_replace('/^\s*(?:[-*•]|\d+[\.\)])\s*/u', '', $trimmed) ?? '');
            if ($normalized === '') {
                continue;
            }

            if ($this->looksLikeInstructionLine($normalized) || $this->looksLikeSectionHeader($normalized)) {
                continue;
            }

            $items[] = [
                'label' => $normalized,
                'max_points' => self::DEFAULT_ITEM_MAX_POINTS,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array{label:string,max_points:int}>
     */
    private function normalizeStructuredItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $item) {
            $label = trim(is_array($item)
                ? (string) ($item['label'] ?? $item['title'] ?? $item['name'] ?? '')
                : (string) $item);
            $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

            if ($label === '') {
                continue;
            }

            $maxPoints = is_array($item)
                ? $this->normalizeMaxPoints($item['max_points'] ?? $item['maxPoints'] ?? null)
                : self::DEFAULT_ITEM_MAX_POINTS;

            $normalized[] = [
                'label' => $label,
                'max_points' => $maxPoints,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $existing
     */
    private function buildCopyName(string $sourceName, array $existing): string
    {
        $fallbackName = 'Новий чек-лист';
        $normalizedSourceName = trim($sourceName) !== '' ? trim($sourceName) : $fallbackName;
        $rootName = $this->extractCopyRootName($normalizedSourceName, $fallbackName);
        $highestCopyNumber = 0;

        foreach ($existing as $checklist) {
            $name = trim((string) ($checklist['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $copyNumber = $this->detectCopyNumber($name, $rootName);
            if ($copyNumber === null) {
                continue;
            }

            $highestCopyNumber = max($highestCopyNumber, $copyNumber);
        }

        $nextCopyNumber = $highestCopyNumber + 1;

        return $nextCopyNumber === 1
            ? $rootName.' (копія)'
            : $rootName.' (копія '.$nextCopyNumber.')';
    }

    private function extractCopyRootName(string $name, string $fallbackName): string
    {
        if (preg_match('/^(.*?)(?:\s+\(копія(?:\s+(\d+))?\))?$/u', $name, $matches) !== 1) {
            return $name !== '' ? $name : $fallbackName;
        }

        $rootName = trim((string) ($matches[1] ?? ''));

        return $rootName !== '' ? $rootName : $fallbackName;
    }

    private function detectCopyNumber(string $name, string $rootName): ?int
    {
        $pattern = '/^'.preg_quote($rootName, '/').'\s+\(копія(?:\s+(\d+))?\)$/u';
        if (preg_match($pattern, $name, $matches) !== 1) {
            return null;
        }

        $copyNumber = (int) ($matches[1] ?? 1);

        return $copyNumber > 0 ? $copyNumber : 1;
    }

    private function normalizeMaxPoints(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_ITEM_MAX_POINTS;
        }

        return max(1, min(100, (int) round((float) $value)));
    }

    private function normalizeItemsText(string $itemsText): string
    {
        return trim(preg_replace("/\r\n?/", "\n", $itemsText) ?? $itemsText);
    }

    private function normalizePrompt(string $prompt): string
    {
        return trim(preg_replace("/\r\n?/", "\n", $prompt) ?? $prompt);
    }

    private function buildItemsText(array $items): string
    {
        return implode("\n", array_map(
            static fn (array $item, int $index): string => ($index + 1).'. '.$item['label'],
            $items,
            array_keys($items),
        ));
    }

    private function looksLikeInstructionLine(string $value): bool
    {
        $normalized = mb_strtolower($value, 'UTF-8');

        return str_contains($normalized, 'оценивай транск')
            || str_contains($normalized, 'оцінюй транск')
            || str_contains($normalized, 'оцени транск')
            || str_contains($normalized, 'оцінюй діалог')
            || str_contains($normalized, 'оціни діалог')
            || str_contains($normalized, 'по данному чек лист')
            || str_contains($normalized, 'по даному чек-лист');
    }

    private function looksLikeSectionHeader(string $value): bool
    {
        $normalized = trim($value);

        if (str_ends_with($normalized, ':')) {
            return true;
        }

        $lower = mb_strtolower($normalized, 'UTF-8');

        return str_contains($lower, 'сумарно')
            || preg_match('/^\p{L}[\p{L}\s\/()"\-]+:$/u', $normalized) === 1;
    }

    /**
     * @param array<int, array{label:string,max_points:int}> $items
     */
    private function buildSummary(array $items): string
    {
        return implode(', ', array_map(
            static fn (array $item): string => $item['label'],
            array_slice($items, 0, 3),
        )).'.';
    }

    /**
     * @param array<int, array{label:string,max_points:int}> $items
     */
    private function sumMaxPoints(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) ($item['max_points'] ?? 0),
            $items,
        ));
    }

    /**
     * @param array<int, string> $reservedIds
     */
    private function makeUniqueId(string $baseId, array $reservedIds): string
    {
        $candidate = $baseId;
        $suffix = 2;

        while (in_array($candidate, $reservedIds, true)) {
            $candidate = $baseId.'-'.$suffix;
            $suffix += 1;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->storagePath();

        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, array<string, mixed>> $checklists
     */
    private function write(array $checklists): void
    {
        Storage::disk('local')->put(
            $this->storagePath(),
            json_encode([
                'checklists' => array_values($checklists),
                'updated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    protected function storagePath(): string
    {
        return trim((string) config('call_center.checklists.storage_path', 'call-center/checklists.json'), '/');
    }
}
