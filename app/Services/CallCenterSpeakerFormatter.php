<?php

namespace App\Services;

class CallCenterSpeakerFormatter
{
    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array{dialogueText: string, dialogueSegments: array<int, array<string, mixed>>}
     */
    public function format(array $segments): array
    {
        $normalizedSegments = $this->normalizeSegments($segments);
        $speakerAliases = $this->buildSpeakerAliases($normalizedSegments);
        $speakerRoleMap = $this->buildSpeakerRoleMap($normalizedSegments);
        $dialogueSegments = [];

        foreach ($normalizedSegments as $index => $segment) {
            $rawSpeakerId = $segment['speaker_id'] ?? null;
            $speaker = $rawSpeakerId !== null
                ? ($speakerRoleMap[$rawSpeakerId] ?? 'speaker')
                : $this->resolveFallbackSpeaker(
                    $segment['text'],
                    $dialogueSegments[count($dialogueSegments) - 1]['speaker'] ?? null,
                    (string) ($dialogueSegments[count($dialogueSegments) - 1]['text'] ?? ''),
                    $index,
                );

            $dialogueSegments[] = [
                ...$segment,
                'speaker' => $speaker,
                'speaker_label' => $this->speakerLabel($speaker, $rawSpeakerId, $speakerAliases),
            ];
        }

        $dialogueText = implode("\n\n", array_map(
            fn (array $segment): string => sprintf(
                '[%s - %s] %s: %s',
                (string) ($segment['start_label'] ?? ''),
                (string) ($segment['end_label'] ?? ''),
                (string) ($segment['speaker_label'] ?? $this->speakerLabel((string) ($segment['speaker'] ?? 'speaker'))),
                (string) ($segment['text'] ?? '')
            ),
            $dialogueSegments,
        ));

        return [
            'dialogueText' => $dialogueText,
            'dialogueSegments' => $dialogueSegments,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSegments(array $segments): array
    {
        $normalized = [];

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $speakerId = $segment['speaker_id'] ?? $segment['speaker'] ?? null;
            $speakerId = is_string($speakerId) && $speakerId !== '' ? $speakerId : null;

            $normalized[] = [
                ...$segment,
                'speaker_id' => $speakerId,
                'text' => $text,
                'start_label' => (string) ($segment['start_label'] ?? $this->formatTimestamp((float) ($segment['start'] ?? 0))),
                'end_label' => (string) ($segment['end_label'] ?? $this->formatTimestamp((float) ($segment['end'] ?? 0))),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<string, string>
     */
    private function buildSpeakerAliases(array $segments): array
    {
        $aliases = [];
        $counter = 1;

        foreach ($segments as $segment) {
            $speakerId = $segment['speaker_id'] ?? null;
            if (! is_string($speakerId) || $speakerId === '' || array_key_exists($speakerId, $aliases)) {
                continue;
            }

            $aliases[$speakerId] = 'Спікер '.$counter;
            $counter += 1;
        }

        return $aliases;
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<string, string>
     */
    private function buildSpeakerRoleMap(array $segments): array
    {
        $profiles = [];

        foreach ($segments as $index => $segment) {
            $speakerId = $segment['speaker_id'] ?? null;
            if (! is_string($speakerId) || $speakerId === '') {
                continue;
            }

            $text = (string) ($segment['text'] ?? '');
            $managerScore = $this->scoreManagerText($text);
            $clientScore = $this->scoreClientText($text);
            $questionCount = substr_count($text, '?');

            if (! array_key_exists($speakerId, $profiles)) {
                $profiles[$speakerId] = [
                    'speaker_id' => $speakerId,
                    'manager_score' => 0,
                    'client_score' => 0,
                    'question_count' => 0,
                    'turns' => 0,
                    'first_index' => $index,
                    'introduction_hits' => 0,
                    'first_text' => $text,
                ];
            }

            $profiles[$speakerId]['manager_score'] += $managerScore;
            $profiles[$speakerId]['client_score'] += $clientScore;
            $profiles[$speakerId]['question_count'] += $questionCount;
            $profiles[$speakerId]['turns'] += 1;
            $profiles[$speakerId]['introduction_hits'] += $this->managerIntroductionHits($text);
        }

        if ($profiles === []) {
            return [];
        }

        $speakerIds = array_keys($profiles);
        if (count($speakerIds) === 1) {
            $speakerId = $speakerIds[0];

            return [
                $speakerId => $profiles[$speakerId]['manager_score'] >= $profiles[$speakerId]['client_score']
                    ? 'manager'
                    : 'client',
            ];
        }

        $managerCandidate = null;
        $managerRank = null;
        $clientCandidate = null;
        $clientRank = null;

        foreach ($profiles as $speakerId => $profile) {
            $managerBias = $profile['manager_score'] - $profile['client_score'];
            $clientBias = $profile['client_score'] - $profile['manager_score'];
            $managerRankKey = [
                $managerBias,
                $profile['introduction_hits'],
                $profile['question_count'],
                -$profile['first_index'],
            ];
            $clientRankKey = [
                $clientBias,
                -$profile['introduction_hits'],
                -$profile['question_count'],
                -$profile['first_index'],
            ];

            if ($managerRank === null || $this->compareRankKeys($managerRankKey, $managerRank) > 0) {
                $managerRank = $managerRankKey;
                $managerCandidate = $speakerId;
            }

            if ($clientRank === null || $this->compareRankKeys($clientRankKey, $clientRank) > 0) {
                $clientRank = $clientRankKey;
                $clientCandidate = $speakerId;
            }
        }

        if ($managerCandidate === null || $clientCandidate === null || $managerCandidate === $clientCandidate) {
            uasort($profiles, fn (array $left, array $right): int => $left['first_index'] <=> $right['first_index']);
            $orderedSpeakerIds = array_keys($profiles);

            $managerCandidate = $this->resolveOpeningSpeaker($profiles, $orderedSpeakerIds) ?? $orderedSpeakerIds[0];
            $clientCandidate = $orderedSpeakerIds[0];
            foreach ($orderedSpeakerIds as $speakerId) {
                if ($speakerId !== $managerCandidate) {
                    $clientCandidate = $speakerId;
                    break;
                }
            }
        }

        $roleMap = [
            $managerCandidate => 'manager',
            $clientCandidate => $managerCandidate === $clientCandidate ? 'speaker' : 'client',
        ];

        foreach ($speakerIds as $speakerId) {
            $roleMap[$speakerId] ??= 'speaker';
        }

        return $roleMap;
    }

    /**
     * @param array<string, array<string, mixed>> $profiles
     * @param array<int, string> $orderedSpeakerIds
     */
    private function resolveOpeningSpeaker(array $profiles, array $orderedSpeakerIds): ?string
    {
        foreach (array_slice($orderedSpeakerIds, 0, 2) as $speakerId) {
            $text = (string) ($profiles[$speakerId]['first_text'] ?? '');
            if ($this->managerIntroductionHits($text) > 0) {
                return $speakerId;
            }
        }

        return null;
    }

    private function resolveFallbackSpeaker(string $text, ?string $previousSpeaker, string $previousText, int $index): string
    {
        $managerScore = $this->scoreManagerText($text);
        $clientScore = $this->scoreClientText($text);

        if ($managerScore >= $clientScore + 2) {
            return 'manager';
        }

        if ($clientScore >= $managerScore + 2) {
            return 'client';
        }

        if ($previousSpeaker === null) {
            return $managerScore >= $clientScore ? 'manager' : 'client';
        }

        if ($this->looksLikeContinuation($text, $previousText)) {
            return $previousSpeaker;
        }

        if ($this->looksLikeShortAnswer($text) || $this->endsWithQuestion($previousText)) {
            return $this->oppositeSpeaker($previousSpeaker);
        }

        if ($index === 0) {
            return 'manager';
        }

        return 'speaker';
    }

    private function scoreManagerText(string $text): int
    {
        $score = 0;
        $score += $this->matchScore($text, [
            '/\bдобр(?:ий|ого)\s+д(?:ень|ня)\b/ui',
            '/\bвітаю\b/ui',
            '/\bздравствуйте\b/ui',
            '/\bдобрый\s+день\b/ui',
            '/\bмене\s+зв(?:ати|уть)\b/ui',
            '/\bменя\s+зовут\b/ui',
            '/\bменеджер\b/ui',
            '/\bкомпан(?:ія|ии|ий|ії|ією)\b/ui',
            '/\bтелефоную\b/ui',
            '/\bпідкажіть\b/ui',
            '/\bподскажите\b/ui',
            '/\bзараз\b/ui',
            '/\bсейчас\b/ui',
            '/\bвідкриваю\b/ui',
            '/\bоткрываю\b/ui',
            '/\bмож[еу]\s+запропонувати\b/ui',
            '/\bмогу\s+предложить\b/ui',
            '/\bу\s+нас\b/ui',
            '/\bвам\s+підійде\b/ui',
            '/\bвам\s+подойдет\b/ui',
            '/\bякі\s+саме\b/ui',
            '/\bяка\s+саме\b/ui',
            '/\bкак\s+вас\s+зовут\b/ui',
            '/\bяк\s+вас\s+зв(?:ати|уть)\b/ui',
            '/\bгрив(?:ень|ні|на)\b/ui',
            '/\bпрайс\b/ui',
        ]);

        if (str_contains(mb_strtolower($text, 'UTF-8'), '?')) {
            $score += 1;
        }

        return $score;
    }

    private function scoreClientText(string $text): int
    {
        $score = 0;
        $score += $this->matchScore($text, [
            '/\bмене\s+цікав(?:ить|лять)\b/ui',
            '/\bменя\s+интересует\b/ui',
            '/\bцікав(?:ить|лять)\b/ui',
            '/\bинтересует\b/ui',
            '/\bмені\s+потрібно\b/ui',
            '/\bмне\s+нужно\b/ui',
            '/\bнам\s+потрібно\b/ui',
            '/\bнам\s+нужно\b/ui',
            '/\bпотрібн(?:о|і)\b/ui',
            '/\bнужн(?:о|ы)\b/ui',
            '/\bхочу\b/ui',
            '/\bшукаю\b/ui',
            '/\bищу\b/ui',
            '/\bскільки\b/ui',
            '/\bсколько\b/ui',
            '/\bметр(?:и|ів|ов)?\b/ui',
            '/\bштук\b/ui',
            '/\bколичеств(?:о|а)\b/ui',
            '/\bціна\b/ui',
            '/\bцена\b/ui',
            '/\bвізьму\b/ui',
            '/\bвозьму\b/ui',
            '/\bбуду\s+брати\b/ui',
            '/\bбуду\s+брать\b/ui',
            '/\bопалубк/iu',
            '/\bперекритт/iu',
        ]);

        if (preg_match('/\b\d+(?:[,.]\d+)?(?:\s*[-–]\s*\d+(?:[,.]\d+)?)?\b/u', $text) === 1) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchScore(string $text, array $patterns): int
    {
        $score = 0;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $score += 1;
            }
        }

        return $score;
    }

    private function managerIntroductionHits(string $text): int
    {
        return $this->matchScore($text, [
            '/\bдобр(?:ий|ого)\s+д(?:ень|ня)\b/ui',
            '/\bздравствуйте\b/ui',
            '/\bменеджер\b/ui',
            '/\bкомпан(?:ія|ии|ий|ії|ією)\b/ui',
            '/\bмене\s+зв(?:ати|уть)\b/ui',
            '/\bменя\s+зовут\b/ui',
        ]);
    }

    private function looksLikeShortAnswer(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') > 32) {
            return false;
        }

        return preg_match('/^(так|ні|ага|угу|добре|чудово|ясно|зрозуміло|домовились|алло|слухаю|будь ласка|да|нет|понял|поняла)\b/ui', $text) === 1;
    }

    private function looksLikeContinuation(string $text, string $previousText): bool
    {
        if ($previousText === '') {
            return false;
        }

        if ($this->endsWithQuestion($previousText)) {
            return false;
        }

        return preg_match('/^(і|й|а|та|але|тобто|бо|щоб|якщо|ну|ще|до|від|по|и|но|то есть|потому что)\b/ui', $text) === 1;
    }

    private function endsWithQuestion(string $text): bool
    {
        return str_ends_with(rtrim($text), '?');
    }

    private function oppositeSpeaker(string $speaker): string
    {
        return $speaker === 'manager' ? 'client' : 'manager';
    }

    /**
     * @param array<string, string> $speakerAliases
     */
    private function speakerLabel(string $speaker, ?string $rawSpeakerId = null, array $speakerAliases = []): string
    {
        return match ($speaker) {
            'client' => 'Клієнт',
            'manager' => 'Менеджер',
            default => $rawSpeakerId !== null ? ($speakerAliases[$rawSpeakerId] ?? 'Спікер') : 'Спікер',
        };
    }

    /**
     * @param array<int, int> $left
     * @param array<int, int> $right
     */
    private function compareRankKeys(array $left, array $right): int
    {
        $maxLength = max(count($left), count($right));

        for ($index = 0; $index < $maxLength; $index += 1) {
            $leftValue = $left[$index] ?? 0;
            $rightValue = $right[$index] ?? 0;

            if ($leftValue === $rightValue) {
                continue;
            }

            return $leftValue <=> $rightValue;
        }

        return 0;
    }

    private function formatTimestamp(float $seconds): string
    {
        $totalMilliseconds = (int) round($seconds * 1000);
        $hours = intdiv($totalMilliseconds, 3_600_000);
        $remainder = $totalMilliseconds % 3_600_000;
        $minutes = intdiv($remainder, 60_000);
        $remainder %= 60_000;
        $secs = intdiv($remainder, 1000);
        $milliseconds = $remainder % 1000;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $milliseconds);
        }

        return sprintf('%02d:%02d.%03d', $minutes, $secs, $milliseconds);
    }
}
