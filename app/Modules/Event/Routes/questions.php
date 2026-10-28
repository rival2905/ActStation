<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Panel\EventQuestionController;
use App\Modules\Event\Http\Controllers\Panel\RegistrationAdditionalInformationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])->group(function (): void {
    Route::get('panel/events/{slug}/registration-drafts/{draft}/additional', [RegistrationAdditionalInformationController::class, 'show'])->name('panel.registration-drafts.additional.show');
    Route::post('panel/events/{slug}/registration-drafts/{draft}/additional', [RegistrationAdditionalInformationController::class, 'save'])->middleware('throttle:30,1')->name('panel.registration-drafts.additional.save');
    Route::get('panel/organizer/events/{event}/questions', [EventQuestionController::class, 'index'])->name('panel.organizer.questions.index');
    Route::get('panel/organizer/events/{event}/questions/report', [EventQuestionController::class, 'report'])->name('panel.organizer.questions.report');
    Route::post('panel/organizer/events/{event}/questions', [EventQuestionController::class, 'store'])->name('panel.organizer.questions.store');
    Route::put('panel/organizer/events/{event}/questions/{question}', [EventQuestionController::class, 'update'])->name('panel.organizer.questions.update');
    Route::get('panel/events/{event}/questions/{target}/{subjectId}', [EventQuestionController::class, 'edit'])->whereIn('target', ['institution', 'team', 'participant'])->whereNumber('subjectId')->name('panel.event-questions.edit');
    Route::put('panel/events/{event}/questions/{target}/{subjectId}', [EventQuestionController::class, 'submit'])->whereIn('target', ['institution', 'team', 'participant'])->whereNumber('subjectId')->middleware('throttle:20,1')->name('panel.event-questions.update');
    Route::get('panel/events/{event}/answers/{answer}/files/{questionId}', [EventQuestionController::class, 'download'])->whereNumber('questionId')->name('panel.event-questions.download');
});
