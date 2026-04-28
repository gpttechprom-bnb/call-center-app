<?php

namespace App\Support;

class CallCenterChecklistCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'cold-call',
                'name' => 'Холодний дзвінок',
                'type' => 'Перший контакт',
                'summary' => 'Привітання, виявлення потреби, опрацювання заперечень, фіксація наступного кроку.',
                'items' => self::withDefaultPoints([
                    'Представився та окреслив мету дзвінка',
                    'Перевірив, чи зручно клієнту говорити',
                    "З'ясував потребу клієнта",
                    'Поставив уточнювальні запитання',
                    'Опрацював заперечення або сумніви',
                    'Зафіксував наступний крок',
                    'Коректно завершив розмову',
                ]),
            ],
            [
                'id' => 'repeat-contact',
                'name' => 'Повторний контакт',
                'type' => 'Повторний дзвінок',
                'summary' => 'Перевірка статусу клієнта, допрацювання аргументації, робота із сумнівами, закриття на дію.',
                'items' => self::withDefaultPoints([
                    'Нагадав контекст попереднього контакту',
                    'Актуалізував статус і потребу клієнта',
                    'Уточнив, що змінилося після минулої розмови',
                    'Допрацював сумніви або заперечення',
                    'Підсумував вигоду для клієнта',
                    'Узгодив нову домовленість або наступний крок',
                ]),
            ],
            [
                'id' => 'upsell',
                'name' => 'Допродаж',
                'type' => 'Допродаж',
                'summary' => 'Виявлення додаткової потреби, доречність пропозиції, ціннісна подача, домовленість.',
                'items' => self::withDefaultPoints([
                    'Виявив додаткову потребу клієнта',
                    'Запропонував релевантний додатковий продукт або послугу',
                    'Пояснив цінність пропозиції мовою вигоди для клієнта',
                    'Перевірив реакцію клієнта на пропозицію',
                    'Опрацював заперечення щодо допродажу',
                    'Закрив розмову на конкретне рішення або наступний крок',
                ]),
            ],
            [
                'id' => 'manager-call',
                'name' => 'Чек-лист №1',
                'type' => 'Загальний сценарій',
                'summary' => 'Базовий універсальний сценарій оцінки якості дзвінка менеджера.',
                'items' => self::withDefaultPoints([
                    'Менеджер привітався та представився',
                    'Менеджер зрозуміло пояснив причину звернення',
                    'Менеджер виявив запит клієнта',
                    'Менеджер дав релевантну консультацію',
                    'Менеджер утримував структуру розмови',
                    'Менеджер погодив наступний крок',
                    'Менеджер завершив розмову ввічливо й предметно',
                ]),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function ids(): array
    {
        return array_map(
            static fn (array $checklist): string => $checklist['id'],
            self::all(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $checklist) {
            if ($checklist['id'] === $id) {
                return $checklist;
            }
        }

        return null;
    }

    public static function defaultId(): string
    {
        return 'cold-call';
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, array{label:string,max_points:int}>
     */
    private static function withDefaultPoints(array $labels): array
    {
        return array_map(
            static fn (string $label): array => [
                'label' => $label,
                'max_points' => 10,
            ],
            $labels,
        );
    }
}
