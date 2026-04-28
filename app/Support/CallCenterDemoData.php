<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class CallCenterDemoData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function storageCalls(): array
    {
        $calls = [
            [
                'direction' => 'in',
                'caller' => '099 072 96 26',
                'caller_meta' => null,
                'employee' => 'Wire: Марина Корост',
                'employee_meta' => '098 329 86 99',
                'duration_seconds' => 72,
                'started_at' => CarbonImmutable::create(2026, 4, 8, 15, 36, 0),
                'transcript_status' => 'Текст готовий',
                'audio_status' => 'Запис доступний',
                'score' => 100,
                'summary' => 'Розмову завершено без втрат: менеджер швидко визначив запит і одразу зафіксував наступний крок.',
                'transcript' => "Менеджер: Добрий день, мене звати Марина. Підкажіть, чи правильно я розумію, що вам потрібен розрахунок сьогодні?\nКлієнт: Так, і бажано одразу з варіантами.\nМенеджер: Чудово, підготую й надішлю протягом години.",
                'note' => 'Рекомендація: використовувати як еталон для команди, бо структура розмови дуже чиста.',
                'score_items' => [
                    ['title' => 'Привітання', 'score' => 100, 'text' => 'Вхід у розмову швидкий і впевнений.', 'sort_order' => 1],
                    ['title' => 'Виявлення потреби', 'score' => 92, 'text' => 'Потрібне запитання поставлено в перші секунди розмови.', 'sort_order' => 2],
                    ['title' => 'Наступний крок', 'score' => 96, 'text' => 'Менеджер чітко зафіксував обіцянку щодо строку відправлення.', 'sort_order' => 3],
                ],
            ],
            [
                'direction' => 'out',
                'caller' => '067 968 94 07',
                'caller_meta' => null,
                'employee' => 'Wire: Анна Шиповская',
                'employee_meta' => '050 558 02 38',
                'duration_seconds' => 54,
                'started_at' => CarbonImmutable::create(2026, 4, 8, 15, 34, 0),
                'transcript_status' => 'Текст частково готовий',
                'audio_status' => 'Прослухати',
                'score' => 75,
                'summary' => 'Діалог непоганий за темпом, але менеджер зарано перейшов до презентації й не до кінця розкрив контекст клієнта.',
                'transcript' => "Менеджер: Підкажіть, інтерес ще актуальний?\nКлієнт: Так, але поки розглядаю варіанти.\nМенеджер: Тоді розповім про нашу пропозицію та тарифи.",
                'note' => 'Потрібно посилити блок запитань до презентації: зараз розмова надто швидко переходить в офер.',
                'score_items' => [
                    ['title' => 'Привітання', 'score' => 82, 'text' => 'Нормальний вхід, але без чіткої рамки розмови.', 'sort_order' => 1],
                    ['title' => 'Виявлення потреби', 'score' => 64, 'text' => 'Не вистачило уточнювальних запитань перед пропозицією.', 'sort_order' => 2],
                    ['title' => 'Робота із запереченнями', 'score' => 79, 'text' => 'Є спроба втримати інтерес, але аргументація коротка.', 'sort_order' => 3],
                ],
            ],
            [
                'direction' => 'out',
                'caller' => '097 656 90 51',
                'caller_meta' => null,
                'employee' => 'Wire: Мария Унгарова',
                'employee_meta' => '066 749 64 69',
                'duration_seconds' => 39,
                'started_at' => CarbonImmutable::create(2026, 4, 8, 15, 34, 0),
                'transcript_status' => 'Текст в обробці',
                'audio_status' => 'Запис доступний',
                'score' => 80,
                'summary' => 'Розмова коротка, але менеджер зберіг ввічливий тон і встиг озвучити основний офер.',
                'transcript' => "Менеджер: Я телефоную за вашим запитом.\nКлієнт: Зараз незручно, передзвоніть пізніше.\nМенеджер: Звісно, тоді уточню зручний час і повернуся.",
                'note' => 'Непоганий сценарій короткого контакту, але поки даних замало для повної оцінки.',
                'score_items' => [
                    ['title' => 'Привітання', 'score' => 88, 'text' => 'Тон спокійний і коректний.', 'sort_order' => 1],
                    ['title' => 'Гнучкість', 'score' => 80, 'text' => 'Менеджер правильно не тиснув, а запропонував альтернативу.', 'sort_order' => 2],
                    ['title' => 'Фіксація наступного кроку', 'score' => 72, 'text' => 'Потрібно жорсткіше фіксувати час повторного контакту.', 'sort_order' => 3],
                ],
            ],
            [
                'direction' => 'in',
                'caller' => '067 658 14 33',
                'caller_meta' => null,
                'employee' => 'Wire: Мария Унгарова',
                'employee_meta' => '066 749 64 69',
                'duration_seconds' => 75,
                'started_at' => CarbonImmutable::create(2026, 4, 7, 15, 32, 0),
                'transcript_status' => 'Текст готовий',
                'audio_status' => 'Прослухати',
                'score' => 90,
                'summary' => 'Клієнт зайшов із конкретним запитом, менеджер утримав структуру й не дав розмові розплистися.',
                'transcript' => "Клієнт: Підкажіть, які строки запуску?\nМенеджер: У середньому до двох робочих днів після погодження.\nКлієнт: Чудово, тоді рухаємося далі.",
                'note' => "Можна посилити фінал уточненням щодо відповідального та дедлайну на документи.",
                'score_items' => [
                    ['title' => 'Розуміння запиту', 'score' => 90, 'text' => 'Менеджер одразу відповів у контексті запитання.', 'sort_order' => 1],
                    ['title' => 'Структура розмови', 'score' => 92, 'text' => 'Зайвих відступів не було.', 'sort_order' => 2],
                    ['title' => 'Закриття', 'score' => 84, 'text' => "Є рух уперед, але можна сильніше фіксувати зобов'язання.", 'sort_order' => 3],
                ],
            ],
            [
                'direction' => 'in',
                'caller' => '097 124 14 66',
                'caller_meta' => 'Новий',
                'employee' => 'Марина Корост Sip',
                'employee_meta' => '802 > 909',
                'duration_seconds' => 370,
                'started_at' => CarbonImmutable::create(2026, 4, 6, 15, 29, 0),
                'transcript_status' => 'Текст готовий',
                'audio_status' => 'Запис доступний',
                'score' => 86,
                'summary' => 'Довга розмова з хорошою глибиною запитань. Є кілька затягнутих блоків, де можна було коротше вести клієнта.',
                'transcript' => "Менеджер: Скажіть, що для вас зараз головний критерій?\nКлієнт: Важливі швидкість запуску та супровід.\nМенеджер: Тоді покажу варіант, де обидві речі закриваються без збільшення бюджету.",
                'note' => 'Корисний дзвінок для розбору на командній зустрічі: сильне виявлення потреби, середнє утримання темпу.',
                'score_items' => [
                    ['title' => 'Виявлення потреби', 'score' => 94, 'text' => 'Хороша глибина запитань і правильна логіка.', 'sort_order' => 1],
                    ['title' => 'Презентація рішення', 'score' => 89, 'text' => "Зв'язок із болем клієнта є, але формулювання місцями задовгі.", 'sort_order' => 2],
                    ['title' => 'Темп розмови', 'score' => 74, 'text' => 'Були зайві паузи та повторення.', 'sort_order' => 3],
                ],
            ],
        ];

        foreach (self::repeatedInteractionTestCalls() as $call) {
            $calls[] = $call;
        }

        foreach (range(0, 35) as $index) {
            $calls[] = self::buildGeneratedCall($index);
        }

        return $calls;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function frontendCalls(): array
    {
        $frontendCalls = [];

        foreach (self::storageCalls() as $index => $call) {
            $frontendCalls[] = [
                'id' => $index + 1,
                'direction' => $call['direction'],
                'caller' => $call['caller'],
                'callerMeta' => $call['caller_meta'] ?? '',
                'employee' => $call['employee'],
                'employeeMeta' => $call['employee_meta'] ?? '',
                'duration' => self::formatDuration((int) $call['duration_seconds']),
                'time' => $call['started_at']->format('H:i'),
                'date' => $call['started_at']->format('d.m.Y'),
                'transcriptStatus' => $call['transcript_status'],
                'audioStatus' => $call['audio_status'],
                'score' => $call['score'],
                'summary' => $call['summary'],
                'transcript' => $call['transcript'],
                'note' => $call['note'],
                'scoreItems' => array_map(
                    static fn (array $item): array => [
                        'title' => $item['title'],
                        'score' => $item['score'],
                        'text' => $item['text'],
                    ],
                    $call['score_items'],
                ),
            ];
        }

        return $frontendCalls;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function repeatedInteractionTestCalls(): array
    {
        return [
            self::buildRepeatedInteractionTestCall('in', 18, 47, 165, 91, 'Клієнт повернувся до попереднього запиту, менеджер швидко згадав контекст і уточнив актуальні строки.'),
            self::buildRepeatedInteractionTestCall('out', 14, 21, 96, 84, 'Повторний контакт після паузи: менеджер перевірив готовність клієнта рухатися далі.'),
            self::buildRepeatedInteractionTestCall('in', 10, 4, 212, 88, 'Клієнт уточнив деталі по оплаті, менеджер звірив попередні домовленості та зафіксував наступний крок.'),
            self::buildRepeatedInteractionTestCall('out', 7, 11, 58, 79, 'Короткий дзвінок для підтвердження отримання інформації після попереднього контакту.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildRepeatedInteractionTestCall(
        string $direction,
        int $hour,
        int $minute,
        int $durationSeconds,
        int $score,
        string $summary
    ): array {
        return [
            'direction' => $direction,
            'caller' => '063 777 11 22',
            'caller_meta' => 'Тест повторних взаємодій',
            'employee' => 'Wire: Марина Корост',
            'employee_meta' => '098 329 86 99',
            'duration_seconds' => $durationSeconds,
            'started_at' => CarbonImmutable::create(2026, 4, 8, $hour, $minute, 0),
            'transcript_status' => 'Текст готовий',
            'audio_status' => 'Запис доступний',
            'score' => $score,
            'summary' => $summary,
            'transcript' => "Менеджер: Вітаю, продовжуємо ваше попереднє питання.\nКлієнт: Так, хочу уточнити деталі.\nМенеджер: Добре, звірю контекст і запропоную наступний крок.",
            'note' => 'Тестовий повторний дзвінок для перевірки колонки Кількість.',
            'score_items' => self::buildGeneratedScoreItems($score, 'повторній взаємодії'),
        ];
    }

    /**
     * @return array<int, array{employee: string, employee_meta: string}>
     */
    private static function demoManagerProfiles(): array
    {
        return [
            ['employee' => 'Wire: Марина Корост', 'employee_meta' => '098 329 86 99'],
            ['employee' => 'Wire: Анна Шиповская', 'employee_meta' => '050 558 02 38'],
            ['employee' => 'Wire: Мария Унгарова', 'employee_meta' => '066 749 64 69'],
            ['employee' => 'Марина Корост Sip', 'employee_meta' => '802 > 909'],
            ['employee' => 'Анна Шиповская Sip', 'employee_meta' => '802 > 915'],
            ['employee' => 'Wire: Олена Довбня', 'employee_meta' => '095 863 93 26'],
            ['employee' => 'Wire: Альона Хижняк', 'employee_meta' => '095 856 53 32'],
            ['employee' => 'Wire: Галина Зубенко', 'employee_meta' => '067 263 57 73'],
            ['employee' => 'Wire: Денис Джаним', 'employee_meta' => '093 144 76 08'],
            ['employee' => 'Wire: Єлизавета Ключник', 'employee_meta' => '066 217 25 18'],
            ['employee' => 'Wire: Юлия Королева', 'employee_meta' => '099 446 88 95'],
            ['employee' => 'Wire: Кристина Романец', 'employee_meta' => '050 275 18 56'],
            ['employee' => 'Wire: Андрей Рябко', 'employee_meta' => '098 880 09 73'],
            ['employee' => 'Wire: Юлия Кузьменко', 'employee_meta' => '095 222 84 86'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function demoTopics(): array
    {
        return [
            'швидкому прорахунку',
            'порівнянню пакетів',
            'термінам запуску',
            'умовам супроводу',
            'допродажу сервісу',
            'повторному контакту',
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function generatedScorePattern(): array
    {
        return [92, 88, 83, 95, 76, 90, 86, 81, 97, 84, 89, 91, 78, 93];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildGeneratedCall(int $index): array
    {
        $managerProfiles = self::demoManagerProfiles();
        $topics = self::demoTopics();
        $scorePattern = self::generatedScorePattern();

        $manager = $managerProfiles[$index % count($managerProfiles)];
        $topic = $topics[$index % count($topics)];
        $score = $scorePattern[$index % count($scorePattern)];
        $startedAt = CarbonImmutable::create(2026, 4, 8, 19, 25, 0)->subMinutes($index * 83);

        $transcriptStatus = $score >= 90
            ? 'Текст готовий'
            : ($score >= 85 ? 'Текст частково готовий' : 'Текст в обробці');

        $summary = $score >= 90
            ? 'Менеджер тримає розмову структурно, швидко виходить на потребу й добре фіксує домовленість.'
            : ($score >= 85
                ? 'Комунікація стабільна, але фінальне закриття і темп можна ще посилити.'
                : 'Є хороший контакт із клієнтом, але потрібно глибше виявляти потребу й чіткіше завершувати розмову.');

        return [
            'direction' => $index % 3 === 0 ? 'in' : 'out',
            'caller' => self::buildPhoneNumber(630000000 + ($index * 7919)),
            'caller_meta' => $index % 6 === 0
                ? 'Новий'
                : ($index % 4 === 0 ? self::buildPhoneNumber(500000000 + ($index * 3187)) : null),
            'employee' => $manager['employee'],
            'employee_meta' => $manager['employee_meta'],
            'duration_seconds' => 54 + (($index * 37) % 386),
            'started_at' => $startedAt,
            'transcript_status' => $transcriptStatus,
            'audio_status' => $index % 2 === 0 ? 'Запис доступний' : 'Прослухати',
            'score' => $score,
            'summary' => $summary,
            'transcript' => "Менеджер: Доброго дня, телефоную щодо запиту по {$topic}.\nКлієнт: Так, зараз це актуально.\nМенеджер: Тоді коротко звірю задачу і запропоную найзручніший наступний крок.",
            'note' => 'Демо-дзвінок для візуального наповнення таблиці та перевірки пагінації.',
            'score_items' => self::buildGeneratedScoreItems($score, $topic),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildGeneratedScoreItems(int $score, string $topic): array
    {
        return [
            [
                'title' => 'Привітання',
                'score' => self::clampScore($score + 4),
                'text' => 'Початок розмови звучить рівно та без зайвого тиску.',
                'sort_order' => 1,
            ],
            [
                'title' => 'Виявлення потреби',
                'score' => self::clampScore($score - 2),
                'text' => "Менеджер уточнює контекст клієнта по темі {$topic}.",
                'sort_order' => 2,
            ],
            [
                'title' => 'Наступний крок',
                'score' => self::clampScore($score - 6),
                'text' => 'Фінал розмови можна ще чіткіше закривати на конкретну дію.',
                'sort_order' => 3,
            ],
        ];
    }

    private static function buildPhoneNumber(int $seed): string
    {
        $digits = substr(str_pad((string) $seed, 10, '0', STR_PAD_LEFT), -10);

        return sprintf(
            '%s %s %s %s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 2),
            substr($digits, 8, 2),
        );
    }

    private static function clampScore(int $value): int
    {
        return max(60, min(100, $value));
    }

    private static function formatDuration(int $totalSeconds): string
    {
        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }
}
