<?php

use App\Http\Controllers\Api\AltCallCenterChecklistController;
use App\Http\Controllers\Api\AltCallCenterAutomationController;
use App\Http\Controllers\Api\AltCallCenterBootstrapController;
use App\Http\Controllers\Api\AltCallCenterCallsController;
use App\Http\Controllers\Api\AltCallCenterEvaluationController;
use App\Http\Controllers\Api\AltCallCenterManagersSummaryController;
use App\Http\Controllers\Api\AltCallCenterSettingsController;
use App\Http\Controllers\Api\AltCallCenterTranscriptionController;
use App\Http\Controllers\Api\AltCallCenterTranscriptionAiRewriteController;
use App\Http\Controllers\Api\AltCallCenterUiPreferenceController;
use App\Http\Controllers\Api\AltCallCenterTranscriptionTaskController;
use App\Http\Controllers\Api\BinotelFeedbackController;
use App\Http\Controllers\Api\BinotelWebhookController;
use App\Http\Controllers\Api\CallCenterCallAudioFileController;
use App\Http\Controllers\Api\CallCenterCallAudioUrlController;
use App\Http\Controllers\Api\CallCenterBootstrapController;
use App\Http\Controllers\Api\CallCenterChecklistController;
use App\Http\Controllers\Api\CallCenterEvaluationController;
use App\Http\Controllers\Api\CallCenterSettingsController;
use App\Http\Controllers\Api\CallCenterTranscriptionController;
use App\Http\Controllers\Api\CallCenterTranscriptionAiRewriteController;
use App\Http\Controllers\Api\CallCenterTranscriptionTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/binotel-webhook', BinotelWebhookController::class)
    ->name('api.binotel.hooks');

Route::get('/binotel-feedback', BinotelFeedbackController::class)
    ->name('api.binotel.feedback');

Route::post('/call-center/transcriptions', CallCenterTranscriptionController::class)
    ->name('api.call-center.transcriptions.store');

Route::post('/call-center/transcriptions/ai-rewrite', CallCenterTranscriptionAiRewriteController::class)
    ->name('api.call-center.transcriptions.ai-rewrite');

Route::post('/call-center/transcriptions/tasks', [CallCenterTranscriptionTaskController::class, 'store'])
    ->name('api.call-center.transcriptions.tasks.store');

Route::delete('/call-center/transcriptions/tasks/{taskId}', [CallCenterTranscriptionTaskController::class, 'destroy'])
    ->name('api.call-center.transcriptions.tasks.destroy');

Route::post('/call-center/evaluations', [CallCenterEvaluationController::class, 'store'])
    ->name('api.call-center.evaluations.store');

Route::get('/call-center/evaluations/{jobId}', [CallCenterEvaluationController::class, 'show'])
    ->name('api.call-center.evaluations.show');

Route::delete('/call-center/evaluations/{jobId}', [CallCenterEvaluationController::class, 'destroy'])
    ->name('api.call-center.evaluations.destroy');

Route::get('/call-center/bootstrap', CallCenterBootstrapController::class)
    ->name('api.call-center.bootstrap');

Route::get('/call-center/calls/{call}/audio-url', CallCenterCallAudioUrlController::class)
    ->withoutMiddleware('throttle:api')
    ->name('api.call-center.calls.audio-url');

Route::get('/call-center/calls/{call}/audio-file', CallCenterCallAudioFileController::class)
    ->withoutMiddleware('throttle:api')
    ->name('api.call-center.calls.audio-file');

Route::get('/call-center/checklists', [CallCenterChecklistController::class, 'index'])
    ->name('api.call-center.checklists.index');

Route::post('/call-center/checklists', [CallCenterChecklistController::class, 'store'])
    ->name('api.call-center.checklists.store');

Route::post('/call-center/checklists/{checklistId}/duplicate', [CallCenterChecklistController::class, 'duplicate'])
    ->name('api.call-center.checklists.duplicate');

Route::delete('/call-center/checklists/{checklistId}', [CallCenterChecklistController::class, 'destroy'])
    ->name('api.call-center.checklists.destroy');

Route::get('/call-center/settings', [CallCenterSettingsController::class, 'show'])
    ->name('api.call-center.settings.show');

Route::post('/call-center/settings/available-models', [CallCenterSettingsController::class, 'availableModels'])
    ->name('api.call-center.settings.available-models');

Route::put('/call-center/settings', [CallCenterSettingsController::class, 'update'])
    ->name('api.call-center.settings.update');

Route::prefix('/alt/call-center')->name('api.alt.call-center.')->group(function () {
    Route::post('/transcriptions', AltCallCenterTranscriptionController::class)
        ->name('transcriptions.store');

    Route::post('/transcriptions/ai-rewrite', AltCallCenterTranscriptionAiRewriteController::class)
        ->name('transcriptions.ai-rewrite');

    Route::post('/transcriptions/tasks', [AltCallCenterTranscriptionTaskController::class, 'store'])
        ->name('transcriptions.tasks.store');

    Route::delete('/transcriptions/tasks/{taskId}', [AltCallCenterTranscriptionTaskController::class, 'destroy'])
        ->name('transcriptions.tasks.destroy');

    Route::post('/evaluations', [AltCallCenterEvaluationController::class, 'store'])
        ->name('evaluations.store');

    Route::get('/evaluations/{jobId}', [AltCallCenterEvaluationController::class, 'show'])
        ->name('evaluations.show');

    Route::delete('/evaluations/{jobId}', [AltCallCenterEvaluationController::class, 'destroy'])
        ->name('evaluations.destroy');

    Route::get('/bootstrap', AltCallCenterBootstrapController::class)
        ->name('bootstrap');

    Route::get('/calls', AltCallCenterCallsController::class)
        ->name('calls.index');

    Route::get('/managers/summary', AltCallCenterManagersSummaryController::class)
        ->name('managers.summary');

    Route::get('/automation', [AltCallCenterAutomationController::class, 'show'])
        ->name('automation.show');

    Route::get('/automation/calendar-stats', [AltCallCenterAutomationController::class, 'calendarStats'])
        ->name('automation.calendar-stats');

    Route::post('/automation/play', [AltCallCenterAutomationController::class, 'play'])
        ->name('automation.play');

    Route::post('/automation/pause', [AltCallCenterAutomationController::class, 'pause'])
        ->name('automation.pause');

    Route::put('/automation/settings', [AltCallCenterAutomationController::class, 'updateSettings'])
        ->name('automation.settings.update');

    Route::get('/automation/next-first-call', [AltCallCenterAutomationController::class, 'nextFirstCall'])
        ->name('automation.next-first-call');

    Route::post('/calls/{call}/force-process', [AltCallCenterAutomationController::class, 'forceCall'])
        ->name('calls.force-process');

    Route::get('/calls/{call}/audio-url', CallCenterCallAudioUrlController::class)
        ->withoutMiddleware('throttle:api')
        ->name('calls.audio-url');

    Route::get('/calls/{call}/audio-file', CallCenterCallAudioFileController::class)
        ->withoutMiddleware('throttle:api')
        ->name('calls.audio-file');

    Route::get('/checklists', [AltCallCenterChecklistController::class, 'index'])
        ->name('checklists.index');

    Route::post('/checklists', [AltCallCenterChecklistController::class, 'store'])
        ->name('checklists.store');

    Route::post('/checklists/{checklistId}/duplicate', [AltCallCenterChecklistController::class, 'duplicate'])
        ->name('checklists.duplicate');

    Route::delete('/checklists/{checklistId}', [AltCallCenterChecklistController::class, 'destroy'])
        ->name('checklists.destroy');

    Route::get('/settings', [AltCallCenterSettingsController::class, 'show'])
        ->name('settings.show');

    Route::post('/settings/available-models', [AltCallCenterSettingsController::class, 'availableModels'])
        ->name('settings.available-models');

    Route::put('/settings', [AltCallCenterSettingsController::class, 'update'])
        ->name('settings.update');

    Route::get('/ui-preferences/calls-table-columns', [AltCallCenterUiPreferenceController::class, 'show'])
        ->name('ui-preferences.calls-table-columns.show');

    Route::put('/ui-preferences/calls-table-columns', [AltCallCenterUiPreferenceController::class, 'update'])
        ->name('ui-preferences.calls-table-columns.update');
});
