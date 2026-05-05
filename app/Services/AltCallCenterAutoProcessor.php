<?php

namespace App\Services;

use App\Models\BinotelApiCallCompleted;
use App\Support\AltCallCenterAutomationStore;
use App\Support\AltCallCenterEvaluationJobStore;
use App\Support\AltCallCenterTranscriptionSettings;
use App\Support\CallCenterChecklistStore;
use App\Support\CallCenterLlmPrompts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AltCallCenterAutoProcessor
{
    private const INCOMING_CALL_TYPES = ['0', 'in', 'incoming'];
    private const AI_REWRITE_PROMPT = 'Знайди тільки точкові виправлення в транскрибації: очевидні орфографічні помилки, російські слова, які треба замінити українськими відповідниками, і неіснуючі або неправильно розпізнані слова, якщо правильний варіант очевидний з контексту. Не змінюй сенс, структуру реплік, імена, телефони, цифри, артикули та бренди.';
    // After the soft threshold we keep retrying the same call with the capped delay,
    // but we no longer pause the whole queue on transient Ollama/LLM failures.
    private const RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS = 3;
    private const RETRYABLE_LLM_FAILURE_BASE_DELAY_SECONDS = 30;
    private const RETRYABLE_LLM_FAILURE_MAX_DELAY_SECONDS = 180;
    private const BINOTEL_RECORD_URL_RETRY_MINUTES = BinotelCallRecordUrlResolver::RETRY_MINUTES;
    private const BINOTEL_RECORD_URL_MAX_ATTEMPTS = BinotelCallRecordUrlResolver::MAX_MISSING_URL_ATTEMPTS;
    private const BINOTEL_RECORD_URL_MISSING_MESSAGE = 'Binotel ще не повернув пряме посилання на запис. Повторна cron-перевірка запланована через 15 хвилин.';
    private const BINOTEL_RECORD_URL_FINAL_MESSAGE = 'Binotel не повернув пряме посилання на запис протягом 30 хвилин від першої перевірки. Далі цей дзвінок більше не перевіряємо.';
    private const AI_REWRITE_DISABLED_MESSAGE = 'AI-обробку вимкнено. Після Whisper одразу передаємо текст у блок оцінювання.';

    public function __construct(
        private readonly AltCallCenterAutomationStore $automationStore,
        private readonly AltCallCenterTranscriptionService $transcriptionService,
        private readonly CallCenterTranscriptionAiRewriteService $aiRewriteService,
        private readonly AltCallCenterTranscriptionSettings $transcriptionSettings,
        private readonly CallCenterChecklistStore $checklistStore,
        private readonly AltCallCenterEvaluationJobStore $jobStore,
        private readonly BinotelCallFeedbackStore $feedbackStore,
        private readonly BinotelCallRecordUrlResolver $recordUrlResolver,
        private readonly BinotelCallAudioCacheService $audioCacheService,
        private readonly CallCenterCrmPhoneLookupService $crmPhoneLookupService,
        private readonly CallCenterCrmCallStatusStore $crmCallStatusStore,
    ) {
    }

    public function processNext(?string $forcedGeneralCallId = null): bool
    {
        if (! Schema::hasColumn('binotel_api_call_completeds', 'alt_auto_status')) {
            return false;
        }

        $this->recoverInterruptedCurrentCallIfNeeded();

        if ($this->jobStore->latestActiveJob() !== null) {
            $this->automationStore->markMessage(
                'Очікуємо завершення активного LLM-оцінювання перед наступним дзвінком.',
                'waiting',
                'evaluation',
            );

            return false;
        }

        $this->markStaleRunningCallsFailed();
        $this->releaseStaleReservedCalls();

        $call = $forcedGeneralCallId !== null && trim($forcedGeneralCallId) !== ''
            ? $this->forcedCall($forcedGeneralCallId)
            : ($this->nextRetryCall() ?? $this->nextCall());

        if ($call === null) {
            $this->automationStore->clearCurrentCall();
            $this->automationStore->markMessage(
                $forcedGeneralCallId !== null && trim($forcedGeneralCallId) !== ''
                    ? 'Вказаний дзвінок для ручного запуску не знайдено або він уже недоступний для обробки.'
                    : $this->waitingMessageForAutoQueue(),
                'waiting',
                'waiting',
            );

            return false;
        }

        return $this->processCall($call, $forcedGeneralCallId !== null && trim($forcedGeneralCallId) !== '');
    }

    /**
     * @return array{eligible:bool,message:string,code?:string}
     */
    public function validateForcedCall(BinotelApiCallCompleted $call, bool $allowContinueWithoutCrm = false): array
    {
        $generalCallId = trim((string) $call->call_details_general_call_id);

        if ($generalCallId === '') {
            return [
                'eligible' => false,
                'message' => 'У цього дзвінка немає General Call ID, тому примусова обробка недоступна.',
            ];
        }

        if (trim((string) ($call->request_type ?? '')) !== 'apiCallCompleted') {
            return [
                'eligible' => false,
                'message' => 'Цей запис не належить до завершених дзвінків Binotel, тому його не можна примусово обробити.',
            ];
        }

        if (trim((string) ($call->call_details_disposition ?? '')) !== 'ANSWER' || (int) ($call->call_details_billsec ?? 0) <= 0) {
            return [
                'eligible' => false,
                'message' => 'Цей дзвінок має статус недозвону або нульову тривалість розмови, тому він не підлягає обробці.',
            ];
        }

        if (! $this->meetsMinimumDuration($call)) {
            return [
                'eligible' => false,
                'message' => 'Тривалість цього дзвінка менша за встановлений поріг '
                    .$this->minimumDurationMinutes()
                    .' хв, тому примусова обробка для нього недоступна.',
            ];
        }

        $interactionNumber = (int) ($call->interaction_number ?? 0);
        if ($interactionNumber < 1 || $interactionNumber > 20) {
            return [
                'eligible' => false,
                'message' => 'Для цього дзвінка не визначено коректний номер взаємодії, тому його не можна обробити за поточними правилами.',
            ];
        }

        if ($this->automationEvaluationEnabled() && $this->matchesConfiguredRoutingRule($call) === false) {
            return [
                'eligible' => false,
                'message' => 'Цей дзвінок не підпадає під жодне активне правило автопривʼязки чек-листів, тому примусова обробка для нього недоступна.',
            ];
        }

        $phone = $this->crmPhoneLookupService->normalizePhone(
            (string) ($call->call_details_external_number ?? $call->call_details_customer_from_outside_external_number ?? '')
        );

        if ($phone !== '') {
            try {
                $lookup = $this->lookupCrmForCall($call, $phone);
                if (is_array($lookup)) {
                    $this->crmCallStatusStore->storeLookupForCall($call, $lookup);
                }
            } catch (Throwable $exception) {
                $this->crmCallStatusStore->storeLookupErrorForCall($call, $exception->getMessage());

                if ($allowContinueWithoutCrm) {
                    return [
                        'eligible' => true,
                        'message' => 'CRM не відповіла, але примусову обробку дозволено продовжити вручну.',
                    ];
                }

                return [
                    'eligible' => false,
                    'message' => 'CRM не відповіла під час перевірки номера '.$phone.'. Продовжити обробку без відповіді CRM?',
                    'code' => 'crm_unavailable_confirmation_required',
                ];
            }

            if (is_array($lookup) && $this->shouldSkipBecauseCrmResultMatches($lookup)) {
                $manager = trim((string) ($lookup['manager'] ?? ''));
                $crmCase = trim((string) ($lookup['case'] ?? ''));
                $reason = ($lookup['phone_exist'] ?? false)
                    ? ($manager !== ''
                        ? 'Для номера '.$phone.' вже знайдено лід у CRM за менеджером '.$manager.', тому цей дзвінок не можна примусово обробити.'
                        : 'Для номера '.$phone.' вже знайдено лід у CRM, тому цей дзвінок не можна примусово обробити.')
                    : ($crmCase !== ''
                        ? 'Для номера '.$phone.' CRM повернула статус '.$crmCase.', тому цей дзвінок не можна примусово обробити.'
                        : 'CRM позначила номер '.$phone.' як такий, що не підлягає обробці, тому цей дзвінок не можна примусово обробити.');

                return [
                    'eligible' => false,
                    'message' => $reason,
                ];
            }
        }

        return [
            'eligible' => true,
            'message' => 'Дзвінок відповідає поточним правилам і може бути примусово оброблений.',
        ];
    }

    private function processCall(BinotelApiCallCompleted $call, bool $allowRepeat = false): bool
    {
        $generalCallId = trim((string) $call->call_details_general_call_id);
        $transcriptionResult = null;
        $runId = '';

        try {
            if (! $allowRepeat && $this->callAlreadyEvaluated($call)) {
                $this->automationStore->clearRetry($generalCallId);
                $this->markCall($call, 'completed');

                return true;
            }

            if ($this->shouldSkipBecausePhoneExistsInCrm($call, $generalCallId)) {
                return true;
            }

            if ($generalCallId !== '') {
                $runId = $this->feedbackStore->startRun($generalCallId, [
                    'source_context' => $allowRepeat ? 'alt_force_repeat' : 'alt_auto',
                ])['run_id'];
            }

            $transcriptionResult = $allowRepeat ? null : $this->transcriptionResultFromFeedback($call);

            if ($transcriptionResult === null) {
                $cachedAudio = $this->audioCacheService->ensureLocalCopy($call);
                $audioUrl = trim((string) ($call->call_record_url ?? ''));

                if ($cachedAudio === null) {
                    if ($audioUrl === '') {
                        $this->handleMissingBinotelRecordUrl($call, $generalCallId);

                        return true;
                    }

                    throw new RuntimeException('Не вдалося підготувати локальний аудіофайл для фонового Whisper.');
                }

                $this->markCall($call, 'running');
                $this->automationStore->markCurrentCall(
                    $generalCallId,
                    route('api.alt.call-center.calls.audio-file', ['call' => $call->id]),
                    'Транскрибуємо дзвінок '.$generalCallId.' через локально збережений аудіофайл.',
                    'transcription',
                );

                $transcriptionResult = $this->transcriptionService->transcribeStoredFile(
                    (string) $cachedAudio['absolute_path'],
                    (string) $cachedAudio['file_name'],
                    (string) $cachedAudio['relative_path'],
                    'auto',
                    null,
                    function (array $payload) use ($generalCallId): void {
                        $liveText = trim((string) ($payload['formatted_text'] ?? $payload['text'] ?? ''));
                        $segmentsCount = max(0, (int) ($payload['segments_count'] ?? 0));

                        $this->automationStore->updateCurrentWhisperText(
                            $liveText,
                            $segmentsCount > 0
                                ? 'Транскрибація дзвінка '.$generalCallId.' вже додала '.$segmentsCount.' фрагмент(ів) у живе вікно.'
                                : 'Оператор транскрибації підключився до дзвінка '.$generalCallId.' і готує перші фрагменти для живого вікна.',
                            'transcription',
                        );
                    },
                );
            } else {
                $this->markCall($call, 'running');
                $this->automationStore->markCurrentCall(
                    $generalCallId,
                    trim((string) ($call->call_record_url ?? '')) ?: null,
                    $this->automationAiRewriteEnabled()
                        ? 'У дзвінка '.$generalCallId.' вже є транскрипт. Продовжуємо AI-обробку та оцінювання без повторного Whisper.'
                        : 'У дзвінка '.$generalCallId.' вже є транскрипт. AI-обробку вимкнено, тому переходимо одразу до оцінювання.',
                    $this->automationAiRewriteEnabled() ? 'ai_rewrite' : 'evaluation',
                );
            }

            $transcription = is_array($transcriptionResult['transcription'] ?? null)
                ? $transcriptionResult['transcription']
                : [];
            $sourceText = $this->transcriptionText($transcription);

            if ($sourceText === '') {
                throw new RuntimeException('Оператор транскрибації повернув порожню транскрибацію.');
            }

            $this->automationStore->updateCurrentWhisperText(
                $sourceText,
                'Базову транскрибацію завершено. Текст зафіксовано і передано далі по черзі в AI/LLM.',
            );

            $contextHeader = $this->contextHeader($call);
            $augmentedText = str_starts_with($sourceText, 'Цей дзвінок між клієнтом та менеджером:')
                ? $sourceText
                : $contextHeader."\n\n".$sourceText;
            $augmentedTranscription = $this->replaceTranscriptionText($transcription, $augmentedText, [
                'context_header' => $contextHeader,
                'original_text' => $sourceText,
                'interaction_number' => $call->interaction_number,
                'direction' => $this->direction($call),
            ]);
            $transcriptionResult['transcription'] = $augmentedTranscription;
            $finalTranscription = $augmentedTranscription;

            if ($this->automationAiRewriteEnabled()) {
                $this->automationStore->updateCurrentTranscript(
                    $augmentedText,
                    'Текст уже повернувся від оператора транскрибації. Верхній AI-блок готує його до фінального оцінювання.',
                    'ai_rewrite',
                );

                $this->automationStore->markMessage(
                    'Дані дзвінка додано у верх транскрипту. Запускаємо AI-обробку тексту.',
                    'running',
                    'ai_rewrite',
                );

                $aiRewriteSettings = $this->automationStore->processingSettings()['ai_rewrite'];
                $aiRewriteModel = $this->configuredProcessingModel(
                    $aiRewriteSettings,
                    $this->transcriptionSettings->llmModel(),
                );
                $aiRewritePrompt = $this->configuredProcessingPrompt(
                    $aiRewriteSettings,
                    $aiRewriteModel,
                    'prompt',
                    self::AI_REWRITE_PROMPT,
                );
                $aiRewriteGenerationSettings = $this->configuredGenerationSettings(
                    $aiRewriteSettings,
                    $aiRewriteModel,
                );

                try {
                    $rewrite = $this->aiRewriteService->rewrite(
                        $augmentedText,
                        $aiRewritePrompt,
                        $aiRewriteModel,
                        $this->transcriptionSettings,
                        $this->backgroundLlmGenerationSettings($aiRewriteGenerationSettings),
                    );

                    $rewrittenText = trim((string) ($rewrite['text'] ?? ''));
                    if ($rewrittenText === '') {
                        throw new RuntimeException('AI-обробка повернула порожній текст.');
                    }

                    $finalTranscription = $this->replaceTranscriptionText($augmentedTranscription, $rewrittenText, [
                        'context_header' => $contextHeader,
                        'original_text' => $sourceText,
                        'ai_rewrite' => $rewrite,
                        'interaction_number' => $call->interaction_number,
                        'direction' => $this->direction($call),
                    ]);
                    $transcriptionResult['transcription'] = $finalTranscription;
                    $appliedCorrections = is_array($rewrite['corrections'] ?? null) ? $rewrite['corrections'] : [];
                    $this->automationStore->updateCurrentAiCorrections(
                        $appliedCorrections,
                        trim((string) ($rewrite['raw_corrections'] ?? '')) ?: null,
                        count($appliedCorrections) > 0
                            ? 'AI-модель не переписувала текст повністю. Вона повернула тільки карту точкових виправлень, а скрипт підставив їх у транскрипт.'
                            : 'AI-модель завершила перевірку, але не знайшла надійних точкових виправлень у JSON.',
                        'ai_rewrite',
                    );
                    $this->automationStore->updateCurrentTranscript(
                        $rewrittenText,
                        $this->automationEvaluationEnabled()
                            ? 'AI-обробку завершено. Нижній LLM-блок уже працює з фінальним текстом.'
                            : 'AI-обробку завершено. Зберігаємо фінальний текст у картку дзвінка.',
                        $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                    );
                } catch (Throwable $aiRewriteException) {
                    report($aiRewriteException);

                    $fallbackMessage = $this->automationEvaluationEnabled()
                        ? 'AI-обробка тимчасово не вдалася. Продовжуємо оцінювання по сирому тексту після Whisper, без автоправок.'
                        : 'AI-обробка тимчасово не вдалася. Зберігаємо сирий текст після Whisper без автоправок.';

                    $this->automationStore->updateCurrentAiCorrections(
                        [],
                        null,
                        $fallbackMessage,
                        $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                    );
                    $this->automationStore->updateCurrentTranscript(
                        $augmentedText,
                        $fallbackMessage,
                        $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                    );
                    $this->automationStore->markMessage(
                        $fallbackMessage,
                        'running',
                        $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                    );
                }
            } else {
                $this->automationStore->updateCurrentAiCorrections(
                    [],
                    null,
                    self::AI_REWRITE_DISABLED_MESSAGE,
                    $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                );
                $this->automationStore->updateCurrentTranscript(
                    $augmentedText,
                    $this->automationEvaluationEnabled()
                        ? 'AI-обробку вимкнено. Нижній LLM-блок уже працює з текстом одразу після Whisper.'
                        : 'AI-обробку вимкнено. Зберігаємо текст після Whisper без додаткових правок.',
                    $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                );
                $this->automationStore->markMessage(
                    self::AI_REWRITE_DISABLED_MESSAGE,
                    'running',
                    $this->automationEvaluationEnabled() ? 'evaluation' : 'completed',
                );
            }

            $evaluationCompleted = false;

            if ($this->automationEvaluationEnabled()) {
                $this->automationStore->markMessage(
                    $this->automationAiRewriteEnabled()
                        ? 'AI-обробку завершено. Запускаємо LLM-оцінювання по чек-листу.'
                        : 'Запускаємо LLM-оцінювання по чек-листу одразу після Whisper, без AI-обробки.',
                    'running',
                    'evaluation',
                );

                $evaluationCompleted = $this->runChecklistEvaluation($generalCallId, $finalTranscription, $runId);
                $this->feedbackStore->storeTranscription($generalCallId, array_merge($transcriptionResult, [
                    'transcription' => $finalTranscription,
                ]), $runId);
            } else {
                $this->feedbackStore->storeTranscription($generalCallId, array_merge($transcriptionResult, [
                    'transcription' => $finalTranscription,
                ]), $runId);
                $this->automationStore->markMessage(
                    $this->automationAiRewriteEnabled()
                        ? 'AI-обробку завершено. Оцінювання за чек-листом вимкнено для фонового режиму.'
                        : 'AI-обробку вимкнено. Оцінювання за чек-листом теж вимкнено для фонового режиму.',
                    'running',
                    'completed',
                );
            }

            $this->automationStore->clearRetry($generalCallId);
            $this->markCall($call, 'completed');
            $this->automationStore->markMessage(
                $this->automationEvaluationEnabled()
                    ? ($evaluationCompleted
                        ? ($this->automationAiRewriteEnabled()
                            ? 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт, AI-правки та оцінка готові.'
                            : 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт після Whisper і оцінка готові.')
                        : ($this->automationAiRewriteEnabled()
                            ? 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт і AI-правки готові. Для цього напрямку/взаємодії чек-лист не підібрано.'
                            : 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт після Whisper готовий. Для цього напрямку/взаємодії чек-лист не підібрано.'))
                    : ($this->automationAiRewriteEnabled()
                        ? 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт і AI-правки готові.'
                        : 'Дзвінок '.$generalCallId.' повністю оброблено: транскрипт після Whisper готовий.'),
                'running',
                'completed',
            );
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Не вдалося автоматично обробити дзвінок.';

            if ($generalCallId !== '' && $call instanceof BinotelApiCallCompleted && $this->shouldSkipAfterAudioDownloadFailure($message)) {
                $this->handleAudioDownloadFailure($call, $generalCallId, $message);
                report($exception);

                return true;
            }

            $shouldRetryCurrentCall = $generalCallId !== '' && $this->shouldRetryCurrentCall($message);

            if ($generalCallId !== '' && is_array($transcriptionResult)) {
                $this->feedbackStore->storeTranscription($generalCallId, $transcriptionResult, $runId !== '' ? $runId : null);
            }

            $this->feedbackStore->markEvaluationFailed($generalCallId, $message, 'alt-auto', $runId !== '' ? $runId : null);

            $retryAttempt = $this->nextRetryAttempt($generalCallId);
            $delaySeconds = $this->retryDelaySeconds($retryAttempt);
            $retryAt = now()->addSeconds($delaySeconds);
            $isExtendedRetry = $retryAttempt > self::RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS;
            $retryStage = (string) ($this->automationStore->state()['current_stage'] ?? 'evaluation');

            $this->releaseCallForRetry($call, $message);
            $this->automationStore->clearCurrentCall();
            $this->automationStore->scheduleRetry(
                $generalCallId,
                $retryAttempt,
                max(self::RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS, $retryAttempt),
                $retryAt->toIso8601String(),
                $shouldRetryCurrentCall
                    ? ($isExtendedRetry
                        ? 'LLM тимчасово не відповіла по дзвінку '.$generalCallId.'. Черга не зупиняється: повторимо цей дзвінок без паузи через '.$delaySeconds.' с (спроба '.$retryAttempt.').'
                        : 'LLM тимчасово не відповіла по дзвінку '.$generalCallId.'. Черга не зупиняється: повторимо цей дзвінок через '
                            .$delaySeconds
                            .' с (спроба '
                            .$retryAttempt
                            .'/'
                            .self::RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS
                            .') і беремо наступні дзвінки.')
                    : ($generalCallId !== ''
                        ? 'Помилка AI/LLM на дзвінку '.$generalCallId.'. Черга не зупиняється: відкладемо повтор на '.$delaySeconds.' с і переходимо до наступних дзвінків.'
                        : 'Помилка AI/LLM. Черга не зупиняється: відкладемо повтор і переходимо до наступних дзвінків.'),
                $retryStage !== '' ? $retryStage : 'evaluation',
            );

            report($exception);
        }

        return true;
    }

    private function forcedCall(string $generalCallId): ?BinotelApiCallCompleted
    {
        $normalized = trim($generalCallId);

        if ($normalized === '') {
            return null;
        }

        $call = $this->callByGeneralCallId($normalized);

        if ($call === null) {
            return null;
        }

        if (in_array((string) ($call->alt_auto_status ?? ''), ['running', 'reserved'], true)) {
            $this->releaseCallForRetry($call, 'Дзвінок переведено в ручний примусовий запуск із таблиці.');
            $call = $this->callByGeneralCallId($normalized);
        }

        return $call;
    }

    private function nextCall(): ?BinotelApiCallCompleted
    {
        $supportsLocalAudioCache = Schema::hasColumn('binotel_api_call_completeds', 'local_audio_relative_path');
        $scheduledRetry = $this->scheduledRetryState();
        $minimumBillsec = $this->minimumDurationMinutes() * 60;
        $query = BinotelApiCallCompleted::query()
            ->with('feedback')
            ->where('request_type', 'apiCallCompleted')
            ->whereNotNull('call_details_general_call_id')
            ->where('call_details_general_call_id', '<>', '')
            ->where('call_details_disposition', 'ANSWER')
            ->where('call_details_billsec', '>', 0)
            ->whereBetween('interaction_number', [1, 20])
            ->where(function ($query) use ($supportsLocalAudioCache): void {
                if ($supportsLocalAudioCache) {
                    $query
                        ->whereNotNull('local_audio_relative_path')
                        ->orWhereNotNull('call_record_url')
                        ->orWhereNotNull('call_details_link_to_call_record_in_my_business')
                        ->orWhereNotNull('call_details_link_to_call_record_overlay_in_my_business');

                    return;
                }

                $query
                    ->whereNotNull('call_record_url')
                    ->orWhereNotNull('call_details_link_to_call_record_in_my_business')
                    ->orWhereNotNull('call_details_link_to_call_record_overlay_in_my_business');
            })
            ->where(function ($query) use ($supportsLocalAudioCache): void {
                if ($supportsLocalAudioCache) {
                    $query
                        ->whereNotNull('local_audio_relative_path')
                        ->orWhere(function ($audioQuery): void {
                            $audioQuery
                                ->whereNotNull('call_record_url')
                                ->where('call_record_url', '<>', '');
                        })
                        ->orWhere(function ($directUrlQuery): void {
                            $directUrlQuery
                                ->where(function ($emptyUrlQuery): void {
                                    $emptyUrlQuery
                                        ->whereNull('call_record_url')
                                        ->orWhere('call_record_url', '');
                                })
                                ->where('call_record_url_check_attempts', '<', self::BINOTEL_RECORD_URL_MAX_ATTEMPTS)
                                ->where(function ($retryQuery): void {
                                    $retryQuery
                                        ->whereNull('call_record_url_last_checked_at')
                                        ->orWhere('call_record_url_last_checked_at', '<=', now()->subMinutes(self::BINOTEL_RECORD_URL_RETRY_MINUTES));
                                });
                        });

                    return;
                }

                $query
                    ->where(function ($audioQuery): void {
                        $audioQuery
                            ->whereNotNull('call_record_url')
                            ->where('call_record_url', '<>', '');
                    })
                    ->orWhere(function ($directUrlQuery): void {
                        $directUrlQuery
                            ->where(function ($emptyUrlQuery): void {
                                $emptyUrlQuery
                                    ->whereNull('call_record_url')
                                    ->orWhere('call_record_url', '');
                            })
                            ->where('call_record_url_check_attempts', '<', self::BINOTEL_RECORD_URL_MAX_ATTEMPTS)
                            ->where(function ($retryQuery): void {
                                $retryQuery
                                    ->whereNull('call_record_url_last_checked_at')
                                    ->orWhere('call_record_url_last_checked_at', '<=', now()->subMinutes(self::BINOTEL_RECORD_URL_RETRY_MINUTES));
                            });
                    });
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('alt_auto_status')
                    ->orWhereIn('alt_auto_status', ['pending', 'failed']);
            })
            ->whereDoesntHave('feedback', function ($query): void {
                $query
                    ->whereIn('evaluation_status', ['pending', 'running', 'completed'])
                    ->orWhereNotNull('evaluation_score')
                    ->orWhereNotNull('evaluated_at');
            });

        if ($minimumBillsec > 0) {
            $query->where('call_details_billsec', '>=', $minimumBillsec);
        }

        if ($scheduledRetry !== null) {
            $query->where('call_details_general_call_id', '<>', (string) $scheduledRetry['general_call_id']);
        }

        $routingRules = $this->configuredChecklistRoutingRules();

        if ($this->automationEvaluationEnabled()) {
            if ($routingRules === []) {
                return null;
            }

            $query->where(function ($rulesQuery) use ($routingRules): void {
                foreach ($routingRules as $rule) {
                    $rulesQuery->orWhere(function ($ruleQuery) use ($rule): void {
                        $ruleQuery->where('interaction_number', (int) $rule['interaction_number']);

                        $direction = (string) $rule['direction'];

                        if ($direction === 'in') {
                            $ruleQuery->whereIn('call_details_call_type', self::INCOMING_CALL_TYPES);

                            return;
                        }

                        if ($direction === 'out') {
                            $ruleQuery->where(function ($directionQuery): void {
                                $directionQuery
                                    ->whereNull('call_details_call_type')
                                    ->orWhere('call_details_call_type', '')
                                    ->orWhereNotIn('call_details_call_type', self::INCOMING_CALL_TYPES);
                            });
                        }
                    });
                }
            });
        }

        return $query
            ->orderBy('call_details_start_time')
            ->orderBy('id')
            ->first();
    }

    private function handleMissingBinotelRecordUrl(BinotelApiCallCompleted $call, string $generalCallId): void
    {
        $attempts = max(0, (int) ($call->call_record_url_check_attempts ?? 0));
        $isFinalAttempt = $attempts >= self::BINOTEL_RECORD_URL_MAX_ATTEMPTS;
        $message = $isFinalAttempt
            ? self::BINOTEL_RECORD_URL_FINAL_MESSAGE
            : self::BINOTEL_RECORD_URL_MISSING_MESSAGE;

        $this->markCall($call, 'failed', $message);
        $this->automationStore->clearCurrentCall();
        $this->automationStore->clearRetry($generalCallId);
        $this->automationStore->markMessage(
            $isFinalAttempt
                ? ($generalCallId !== ''
                    ? 'Binotel не віддав пряме посилання для дзвінка '.$generalCallId.' навіть через 30 хвилин від першої перевірки. Фіксуємо фінальну помилку і більше не повертаємо цей дзвінок у чергу.'
                    : 'Binotel не віддав пряме посилання для дзвінка навіть через 30 хвилин від першої перевірки. Фіксуємо фінальну помилку і більше не повертаємо цей дзвінок у чергу.')
                : ($generalCallId !== ''
                    ? 'Binotel ще не віддав пряме посилання для дзвінка '.$generalCallId.'. Наступна автоматична перевірка буде через 15 хвилин.'
                    : 'Binotel ще не віддав пряме посилання для дзвінка. Наступна автоматична перевірка буде через 15 хвилин.'),
            'running',
            'waiting',
        );
    }

    private function handleAudioDownloadFailure(BinotelApiCallCompleted $call, string $generalCallId, string $message): void
    {
        $call->forceFill([
            'call_record_url' => null,
            'call_record_url_last_checked_at' => now(),
            'alt_auto_status' => 'failed',
            'alt_auto_error' => $message,
            'alt_auto_finished_at' => now(),
        ])->save();

        $this->automationStore->clearCurrentCall();
        $this->automationStore->clearRetry($generalCallId);
        $this->automationStore->markMessage(
            'Не вдалося скачати аудіо для дзвінка '.$generalCallId.'. Скинули кешований прямий URL, щоб на наступній спробі запросити нове посилання у Binotel, і переходимо до наступного дзвінка.',
            'running',
            'waiting',
        );
    }

    private function nextRetryCall(): ?BinotelApiCallCompleted
    {
        $retry = $this->automationStore->retryState();
        $generalCallId = trim((string) ($retry['general_call_id'] ?? ''));
        $availableAt = trim((string) ($retry['available_at'] ?? ''));

        if ($generalCallId === '') {
            return null;
        }

        if ($availableAt !== '') {
            try {
                if (CarbonImmutable::parse($availableAt)->isFuture()) {
                    return null;
                }
            } catch (Throwable) {
                $this->automationStore->clearRetry($generalCallId);

                return null;
            }
        }

        $call = $this->callByGeneralCallId($generalCallId);

        if ($call === null) {
            $this->automationStore->clearRetry($generalCallId);

            return null;
        }

        if (! $this->meetsMinimumDuration($call)) {
            $this->automationStore->clearRetry($generalCallId);

            return null;
        }

        if ($this->callAlreadyEvaluated($call) || (string) ($call->alt_auto_status ?? '') === 'completed') {
            $this->automationStore->clearRetry($generalCallId);

            return null;
        }

        if (in_array((string) ($call->alt_auto_status ?? ''), ['running', 'reserved'], true)) {
            $this->releaseCallForRetry($call, 'Автоматично відновлюємо незавершений дзвінок після перезапуску воркера.');
            $call = $this->callByGeneralCallId($generalCallId);
        }

        return $call;
    }

    private function markStaleRunningCallsFailed(): void
    {
        BinotelApiCallCompleted::query()
            ->where('alt_auto_status', 'running')
            ->where('alt_auto_started_at', '<=', now()->subHours(12))
            ->update([
                'alt_auto_status' => 'failed',
                'alt_auto_finished_at' => now(),
                'alt_auto_error' => 'Фоновий процес не завершив дзвінок за 12 годин.',
            ]);
    }

    private function releaseStaleReservedCalls(): void
    {
        BinotelApiCallCompleted::query()
            ->where('alt_auto_status', 'reserved')
            ->where('alt_auto_started_at', '<=', now()->subMinutes(10))
            ->whereDoesntHave('feedback')
            ->update([
                'alt_auto_status' => 'pending',
                'alt_auto_finished_at' => null,
                'alt_auto_error' => null,
            ]);
    }

    private function callAlreadyEvaluated(BinotelApiCallCompleted $call): bool
    {
        $feedback = $call->feedback;

        return $feedback !== null && (string) $feedback->evaluation_status === 'completed';
    }

    private function markCall(BinotelApiCallCompleted $call, string $status, ?string $error = null): void
    {
        $attributes = [
            'alt_auto_status' => $status,
            'alt_auto_error' => $error,
        ];

        if ($status === 'running') {
            $attributes['alt_auto_started_at'] = now();
            $attributes['alt_auto_finished_at'] = null;
        }

        if (in_array($status, ['completed', 'failed'], true)) {
            $attributes['alt_auto_finished_at'] = now();
        }

        $call->forceFill($attributes)->save();
    }

    private function releaseCallForRetry(BinotelApiCallCompleted $call, string $message): void
    {
        if (! array_key_exists('alt_auto_status', $call->getAttributes())) {
            return;
        }

        $attributes = [
            'alt_auto_status' => 'pending',
            'alt_auto_error' => $message,
        ];

        if (array_key_exists('alt_auto_started_at', $call->getAttributes())) {
            $attributes['alt_auto_started_at'] = null;
        }

        if (array_key_exists('alt_auto_finished_at', $call->getAttributes())) {
            $attributes['alt_auto_finished_at'] = null;
        }

        $call->forceFill($attributes)->save();
    }

    private function recoverInterruptedCurrentCallIfNeeded(): void
    {
        $state = $this->automationStore->state();
        $generalCallId = trim((string) ($state['current_general_call_id'] ?? ''));

        if ($generalCallId === '') {
            return;
        }

        $call = $this->callByGeneralCallId($generalCallId);
        if ($call === null) {
            $this->automationStore->clearRetry($generalCallId);

            return;
        }

        if ($this->callAlreadyEvaluated($call) || (string) ($call->alt_auto_status ?? '') === 'completed') {
            $this->automationStore->clearRetry($generalCallId);

            return;
        }

        if (! in_array((string) ($call->alt_auto_status ?? ''), ['running', 'reserved'], true)) {
            return;
        }

        $this->releaseCallForRetry($call, 'Автоматично відновлюємо дзвінок після перезапуску або падіння воркера.');
        $this->automationStore->scheduleRetry(
            $generalCallId,
            max(1, (int) ($this->automationStore->retryState()['attempt'] ?? 1)),
            max(self::RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS, (int) ($this->automationStore->retryState()['max_attempts'] ?? self::RETRYABLE_LLM_FAILURE_MAX_ATTEMPTS)),
            now()->toIso8601String(),
            'Знайдено незавершений дзвінок '.$generalCallId.'. Відновлюємо обробку саме цього дзвінка після перезапуску воркера.',
            (string) ($state['current_stage'] ?? 'evaluation'),
        );
    }

    private function callByGeneralCallId(string $generalCallId): ?BinotelApiCallCompleted
    {
        $normalized = trim($generalCallId);

        if ($normalized === '') {
            return null;
        }

        return BinotelApiCallCompleted::query()
            ->with('feedback')
            ->where('request_type', 'apiCallCompleted')
            ->where('call_details_general_call_id', $normalized)
            ->where('interaction_number', 1)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{general_call_id:string,available_at:CarbonImmutable,seconds:int,attempt:int,max_attempts:int}|null
     */
    private function scheduledRetryState(): ?array
    {
        $retry = $this->automationStore->retryState();
        $generalCallId = trim((string) ($retry['general_call_id'] ?? ''));
        $availableAt = trim((string) ($retry['available_at'] ?? ''));

        if ($generalCallId === '' || $availableAt === '') {
            return null;
        }

        try {
            $retryAt = CarbonImmutable::parse($availableAt);
        } catch (Throwable) {
            $this->automationStore->clearRetry($generalCallId);

            return null;
        }

        if (! $retryAt->isFuture()) {
            return null;
        }

        return [
            'general_call_id' => $generalCallId,
            'available_at' => $retryAt,
            'seconds' => max(1, now()->diffInSeconds($retryAt)),
            'attempt' => max(1, (int) ($retry['attempt'] ?? 1)),
            'max_attempts' => max(1, (int) ($retry['max_attempts'] ?? 1)),
        ];
    }

    private function nextRetryAttempt(string $generalCallId): int
    {
        $retry = $this->automationStore->retryState();

        if (trim((string) ($retry['general_call_id'] ?? '')) !== trim($generalCallId)) {
            return 1;
        }

        return max(0, (int) ($retry['attempt'] ?? 0)) + 1;
    }

    private function retryDelaySeconds(int $attempt): int
    {
        return min(
            self::RETRYABLE_LLM_FAILURE_MAX_DELAY_SECONDS,
            self::RETRYABLE_LLM_FAILURE_BASE_DELAY_SECONDS * (2 ** max(0, $attempt - 1)),
        );
    }

    private function shouldRetryCurrentCall(string $message): bool
    {
        $normalized = mb_strtolower(trim($message), 'UTF-8');

        if ($normalized === '') {
            return false;
        }

        foreach ([
            'too many attempts',
            '429',
            'ollama не встиг',
            'не вдалося підключитися до ollama',
            'connection refused',
            'timed out',
            'timeout',
            'curl error 28',
        ] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function shouldSkipAfterAudioDownloadFailure(string $message): bool
    {
        $normalized = mb_strtolower(trim($message), 'UTF-8');

        if ($normalized === '') {
            return false;
        }

        foreach ([
            'не вдалося завантажити аудіо за посиланням',
            'http 403',
            'http 404',
            'request timed out',
            'curl error',
        ] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $transcription
     */
    private function transcriptionResultFromFeedback(BinotelApiCallCompleted $call): ?array
    {
        $feedback = $call->feedback;

        if ($feedback === null || (string) ($feedback->transcription_status ?? '') !== 'completed') {
            return null;
        }

        $transcription = is_array($feedback->transcription_payload ?? null)
            ? $feedback->transcription_payload
            : [];

        if ($transcription === []) {
            $text = trim((string) (
                $feedback->transcription_dialogue_text
                ?? $feedback->transcription_formatted_text
                ?? $feedback->transcription_text
                ?? ''
            ));

            if ($text === '') {
                return null;
            }

            $transcription = [
                'text' => $text,
                'formattedText' => $text,
                'dialogueText' => $text,
            ];
        }

        return [
            'source' => [
                'type' => 'stored-transcription',
                'name' => 'Збережена транскрибація',
                'relativePath' => null,
            ],
            'transcription' => $transcription,
            'storageRunDirectory' => $feedback->transcription_storage_run_directory,
        ];
    }

    /**
     * @param array<string, mixed> $transcription
     */
    private function transcriptionText(array $transcription): string
    {
        foreach (['dialogueText', 'formattedText', 'text'] as $key) {
            $value = trim((string) ($transcription[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $transcription
     * @param array<string, mixed> $automation
     * @return array<string, mixed>
     */
    private function replaceTranscriptionText(array $transcription, string $text, array $automation): array
    {
        $transcription['text'] = $text;
        $transcription['formattedText'] = $text;
        $transcription['dialogueText'] = $text;
        $transcription['automation'] = array_merge([
            'processed_by' => 'alt_auto_worker',
            'processed_at' => now()->toIso8601String(),
        ], $automation);

        return $transcription;
    }

    private function contextHeader(BinotelApiCallCompleted $call): string
    {
        $interactionNumber = (int) ($call->interaction_number ?? 0);
        $interactionLabel = $interactionNumber > 0
            ? $this->ordinalInteractionLabel($interactionNumber)
            : 'не визначено';

        $directionLine = $this->direction($call) === 'in'
            ? 'Вхідний дзвінок від клієнта.'
            : 'Вихідний дзвінок від менеджера до клієнта.';

        return "Цей дзвінок між клієнтом та менеджером: {$interactionLabel}.\n{$directionLine}";
    }

    private function direction(BinotelApiCallCompleted $call): string
    {
        $callType = trim((string) ($call->call_details_call_type ?? ''));

        return in_array($callType, self::INCOMING_CALL_TYPES, true) ? 'in' : 'out';
    }

    private function ordinalInteractionLabel(int $number): string
    {
        $labels = [
            1 => 'перший',
            2 => 'другий',
            3 => 'третій',
            4 => 'четвертий',
            5 => 'п\'ятий',
            6 => 'шостий',
            7 => 'сьомий',
            8 => 'восьмий',
            9 => 'дев\'ятий',
            10 => 'десятий',
            11 => 'одинадцятий',
            12 => 'дванадцятий',
            13 => 'тринадцятий',
            14 => 'чотирнадцятий',
            15 => 'п\'ятнадцятий',
            16 => 'шістнадцятий',
            17 => 'сімнадцятий',
            18 => 'вісімнадцятий',
            19 => 'дев\'ятнадцятий',
            20 => 'двадцятий',
        ];

        return $labels[$number] ?? $number.'-й';
    }

    /**
     * @param array<string, mixed> $transcription
     */
    private function runChecklistEvaluation(string $generalCallId, array $transcription, ?string $runId = null): bool
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];
        $checklistId = $this->resolveAutomationChecklistId($evaluationSettings, $generalCallId);

        if ($checklistId === '') {
            $this->feedbackStore->markEvaluationSkipped($generalCallId, $runId);
            $this->automationStore->markMessage(
                'Для цього дзвінка не знайдено правило автопривʼязки чек-листа. Зберігаємо транскрипт без LLM-оцінювання.',
                'running',
                'completed',
            );

            return false;
        }

        $checklist = $this->checklistStore->find($checklistId);

        if ($checklist === null) {
            throw new RuntimeException('Не знайдено чек-лист із правила автопривʼязки для автоматичного оцінювання.');
        }

        $job = $this->jobStore->create(
            $transcription,
            $checklist,
            array_merge($this->llmEvaluationSettings(), [
                'run_id' => $runId,
            ]),
            $generalCallId,
        );
        $jobId = (string) ($job['id'] ?? '');

        if ($jobId === '') {
            throw new RuntimeException('Не вдалося створити фонове завдання LLM-оцінювання.');
        }

        $this->feedbackStore->storeEvaluationRequested($generalCallId, $transcription, $checklist, $jobId, false, $runId);

        $exitCode = Artisan::call('call-center:alt-evaluate-job', [
            'jobId' => $jobId,
        ]);
        $finishedJob = $this->jobStore->find($jobId);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());
            $jobError = trim((string) ($finishedJob['error'] ?? ''));

            throw new RuntimeException(
                $jobError !== ''
                    ? $jobError
                    : ($output !== '' ? $output : 'LLM-оцінювання завершилося помилкою.')
            );
        }

        if ((string) ($finishedJob['status'] ?? '') !== 'completed') {
            $jobError = trim((string) ($finishedJob['error'] ?? ''));

            throw new RuntimeException($jobError !== '' ? $jobError : 'LLM-оцінювання не повернуло завершений статус.');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $evaluationSettings
     */
    private function resolveAutomationChecklistId(array $evaluationSettings, string $generalCallId): string
    {
        $rules = $this->configuredChecklistRoutingRules($evaluationSettings);

        if ($generalCallId === '' || $rules === []) {
            return '';
        }

        $call = BinotelApiCallCompleted::query()
            ->where('call_details_general_call_id', $generalCallId)
            ->first();

        if (! $call instanceof BinotelApiCallCompleted) {
            return '';
        }

        $interactionNumber = max(1, min(20, (int) ($call->interaction_number ?? 1)));
        $direction = $this->direction($call);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $ruleChecklistId = trim((string) ($rule['checklist_id'] ?? ''));
            $ruleInteractionNumber = (int) ($rule['interaction_number'] ?? 0);
            $ruleDirection = trim((string) ($rule['direction'] ?? 'any'));

            if ($ruleChecklistId === '' || $ruleInteractionNumber !== $interactionNumber) {
                continue;
            }

            if ($ruleDirection !== 'any' && $ruleDirection !== $direction) {
                continue;
            }

            return $ruleChecklistId;
        }

        return '';
    }

    private function matchesConfiguredRoutingRule(BinotelApiCallCompleted $call): bool
    {
        $interactionNumber = max(1, min(20, (int) ($call->interaction_number ?? 1)));
        $direction = $this->direction($call);

        foreach ($this->configuredChecklistRoutingRules() as $rule) {
            if ((int) ($rule['interaction_number'] ?? 0) !== $interactionNumber) {
                continue;
            }

            $ruleDirection = trim((string) ($rule['direction'] ?? 'any'));
            if ($ruleDirection !== 'any' && $ruleDirection !== $direction) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function minimumDurationMinutes(): int
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];

        return max(0, min(10, (int) ($evaluationSettings['minimum_duration_minutes'] ?? 0)));
    }

    private function meetsMinimumDuration(BinotelApiCallCompleted $call): bool
    {
        $minimumDurationMinutes = $this->minimumDurationMinutes();

        if ($minimumDurationMinutes <= 0) {
            return true;
        }

        return (int) ($call->call_details_billsec ?? 0) >= ($minimumDurationMinutes * 60);
    }

    /**
     * @param  array<string, mixed>|null  $evaluationSettings
     * @return array<int, array{checklist_id:string,interaction_number:int,direction:string}>
     */
    private function configuredChecklistRoutingRules(?array $evaluationSettings = null): array
    {
        $evaluationSettings ??= $this->automationStore->processingSettings()['evaluation'];
        $rawRules = is_array($evaluationSettings['checklist_routing_rules'] ?? null)
            ? $evaluationSettings['checklist_routing_rules']
            : [];

        $normalized = [];
        $seen = [];

        foreach ($rawRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $checklistId = trim((string) ($rule['checklist_id'] ?? ''));
            $interactionNumber = (int) ($rule['interaction_number'] ?? 0);
            $direction = trim((string) ($rule['direction'] ?? 'any'));
            $direction = in_array($direction, ['in', 'out', 'any'], true) ? $direction : 'any';

            if ($checklistId === '' || $interactionNumber < 1 || $interactionNumber > 20) {
                continue;
            }

            $signature = $checklistId.'|'.$interactionNumber.'|'.$direction;

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $normalized[] = [
                'checklist_id' => $checklistId,
                'interaction_number' => $interactionNumber,
                'direction' => $direction,
            ];
        }

        return $normalized;
    }

    private function waitingMessageForAutoQueue(): string
    {
        $scheduledRetry = $this->scheduledRetryState();

        if ($scheduledRetry !== null) {
            return 'Поки немає інших підходящих дзвінків. Для дзвінка '
                .$scheduledRetry['general_call_id']
                .' заплановано повтор через '
                .$scheduledRetry['seconds']
                .' с (спроба '
                .$scheduledRetry['attempt']
                .'/'
                .$scheduledRetry['max_attempts']
                .').';
        }

        if ($this->automationEvaluationEnabled()) {
            return $this->configuredChecklistRoutingRules() === []
                ? 'Автооцінювання увімкнено, але правила автопривʼязки чек-листів не задані. Черга очікує.'
                : 'Немає нових дзвінків із готовим записом, що підходять під правила автопривʼязки чек-листів. Черга очікує.';
        }

        return 'Нових дзвінків з готовим записом поки немає. Черга очікує.';
    }

    private function shouldSkipBecausePhoneExistsInCrm(BinotelApiCallCompleted $call, string $generalCallId): bool
    {
        $phone = $this->crmPhoneLookupService->normalizePhone(
            (string) ($call->call_details_external_number ?? $call->call_details_customer_from_outside_external_number ?? '')
        );

        if ($phone === '') {
            return false;
        }

        try {
            $lookup = $this->lookupCrmForCall($call, $phone);
            if (is_array($lookup)) {
                $this->crmCallStatusStore->storeLookupForCall($call, $lookup);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->crmCallStatusStore->storeLookupErrorForCall($call, $exception->getMessage());

            $this->automationStore->markMessage(
                'Не вдалося перевірити номер '.$phone.' у CRM. Черга не зупиняється, тому продовжуємо стандартну обробку дзвінка'
                    .($generalCallId !== '' ? ' '.$generalCallId : '')
                    .'.',
                'running',
                'transcription',
            );

            return false;
        }

        if (! is_array($lookup) || ! $this->shouldSkipBecauseCrmResultMatches($lookup)) {
            return false;
        }

        $manager = trim((string) ($lookup['manager'] ?? ''));
        $crmCase = trim((string) ($lookup['case'] ?? ''));
        $message = ($lookup['phone_exist'] ?? false)
            ? ($manager !== ''
                ? 'У CRM вже є лід по номеру '.$phone.' (менеджер: '.$manager.'). Пропускаємо дзвінок без транскрибації та оцінювання.'
                : 'У CRM вже є лід по номеру '.$phone.'. Пропускаємо дзвінок без транскрибації та оцінювання.')
            : ($crmCase !== ''
                ? 'CRM повернула для номера '.$phone.' статус '.$crmCase.'. Пропускаємо дзвінок без транскрибації та оцінювання.'
                : 'CRM позначила номер '.$phone.' як такий, що не підлягає обробці. Пропускаємо дзвінок без транскрибації та оцінювання.');

        $this->automationStore->clearRetry($generalCallId);
        $this->markCall($call, 'completed', $message);
        $this->automationStore->clearCurrentCall();
        $logMessage = ($lookup['phone_exist'] ?? false)
            ? 'Дзвінок '.($generalCallId !== '' ? $generalCallId.' ' : '').'пропущено, бо номер '.$phone.' уже знайдено в CRM'.($manager !== '' ? ' за менеджером '.$manager : '').'.'
            : 'Дзвінок '.($generalCallId !== '' ? $generalCallId.' ' : '').'пропущено, бо CRM повернула для номера '.$phone.' статус '.($crmCase !== '' ? $crmCase : 'skip').'.';
        $this->automationStore->markMessage(
            $logMessage,
            'running',
            'completed',
        );

        return true;
    }

    /**
     * For already-created leads the CRM endpoint can miss records when queried only
     * for the exact day, so we reuse the lookup service fallback logic bound to the
     * call date whenever Binotel gives us a timestamp.
     */
    private function lookupCrmForCall(BinotelApiCallCompleted $call, string $phone): ?array
    {
        $startedAt = (int) ($call->call_details_start_time ?? 0);
        $timezone = (string) config('binotel.timezone', 'Europe/Kyiv');

        if ($startedAt > 0) {
            $day = CarbonImmutable::createFromTimestamp($startedAt, $timezone)->startOfDay();

            return $this->crmPhoneLookupService->lookupForDay($phone, $day);
        }

        return $this->crmPhoneLookupService->lookup($phone);
    }

    /**
     * @param  array<string, mixed>  $lookup
     */
    private function shouldSkipBecauseCrmResultMatches(array $lookup): bool
    {
        if ((bool) ($lookup['phone_exist'] ?? false)) {
            return true;
        }

        return mb_strtolower(trim((string) ($lookup['case'] ?? ''))) === 'low-quality lead';
    }

    /**
     * @return array<string, mixed>
     */
    private function llmGenerationSettings(array $overrides = []): array
    {
        $settings = [
            'thinking_enabled' => false,
            'temperature' => $this->transcriptionSettings->llmTemperature(),
            'num_ctx' => $this->transcriptionSettings->llmNumCtx(),
            'top_k' => $this->transcriptionSettings->llmTopK(),
            'top_p' => $this->transcriptionSettings->llmTopP(),
            'repeat_penalty' => $this->transcriptionSettings->llmRepeatPenalty(),
            'num_predict' => $this->transcriptionSettings->llmNumPredict(),
            'seed' => $this->transcriptionSettings->llmSeed(),
            'timeout_seconds' => $this->transcriptionSettings->llmTimeoutSeconds(),
        ];

        foreach ([
            'temperature',
            'num_ctx',
            'top_k',
            'top_p',
            'repeat_penalty',
            'num_predict',
            'seed',
            'timeout_seconds',
        ] as $key) {
            if (array_key_exists($key, $overrides)) {
                $settings[$key] = $overrides[$key];
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function backgroundLlmGenerationSettings(array $overrides = []): array
    {
        $settings = $this->llmGenerationSettings($overrides);

        if (! array_key_exists('timeout_seconds', $overrides) || ! is_numeric($overrides['timeout_seconds'])) {
            $settings['timeout_seconds'] = $this->transcriptionSettings->llmBackgroundTimeoutSeconds();
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function llmEvaluationSettings(): array
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];
        $model = $this->configuredProcessingModel(
            $evaluationSettings,
            $this->transcriptionSettings->llmModel(),
        );
        $generationSettings = $this->configuredGenerationSettings($evaluationSettings, $model);
        $settings = $this->backgroundLlmGenerationSettings($generationSettings);

        if (! array_key_exists('timeout_seconds', $generationSettings) || ! is_numeric($generationSettings['timeout_seconds'])) {
            $settings['timeout_seconds'] = $this->transcriptionSettings->llmBackgroundTimeoutSeconds();
        }

        return array_merge($settings, [
            'model' => $model,
            'evaluation_scenario' => trim((string) ($evaluationSettings['evaluation_scenario'] ?? '')) !== ''
                ? trim((string) $evaluationSettings['evaluation_scenario'])
                : AltCallCenterChecklistEvaluator::SCENARIO_STATELESS_SINGLE_ITEM,
            'system_prompt' => $this->configuredProcessingPrompt(
                $evaluationSettings,
                $model,
                'system_prompt',
                CallCenterLlmPrompts::statelessChecklistItemSystemPrompt(),
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function configuredProcessingModel(array $settings, string $fallbackModel = ''): string
    {
        $model = trim((string) ($settings['model'] ?? ''));

        return $model !== '' ? $model : trim($fallbackModel);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function configuredProcessingPrompt(
        array $settings,
        string $model,
        string $promptKey,
        string $fallbackPrompt,
    ): string {
        $promptByModelKey = $promptKey.'_by_model';
        $promptByModel = is_array($settings[$promptByModelKey] ?? null) ? $settings[$promptByModelKey] : [];
        $modelName = trim($model);
        $modelPrompt = $modelName !== '' && is_string($promptByModel[$modelName] ?? null)
            ? $promptByModel[$modelName]
            : null;
        $prompt = is_string($modelPrompt) && trim($modelPrompt) !== ''
            ? $modelPrompt
            : (string) ($settings[$promptKey] ?? '');

        return trim($prompt) !== '' ? $prompt : $fallbackPrompt;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function configuredGenerationSettings(array $settings, string $model): array
    {
        $generationSettingsByModel = is_array($settings['generation_settings_by_model'] ?? null)
            ? $settings['generation_settings_by_model']
            : [];
        $modelName = trim($model);

        if ($modelName !== '' && is_array($generationSettingsByModel[$modelName] ?? null)) {
            return $generationSettingsByModel[$modelName];
        }

        return is_array($settings['generation_settings'] ?? null)
            ? $settings['generation_settings']
            : [];
    }

    private function automationEvaluationEnabled(): bool
    {
        $evaluationSettings = $this->automationStore->processingSettings()['evaluation'];

        if (! array_key_exists('enabled', $evaluationSettings)) {
            return true;
        }

        return (bool) $evaluationSettings['enabled'];
    }

    private function automationAiRewriteEnabled(): bool
    {
        $aiRewriteSettings = $this->automationStore->processingSettings()['ai_rewrite'];

        if (! array_key_exists('enabled', $aiRewriteSettings)) {
            return true;
        }

        return (bool) $aiRewriteSettings['enabled'];
    }
}
