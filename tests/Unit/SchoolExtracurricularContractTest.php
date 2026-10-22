<?php

declare(strict_types=1);
use App\Contracts\SchoolExtracurriculars;
use App\Modules\Community\Services\SchoolExtracurricularService;

test('school extracurricular service implements shared module contract', function (): void {
    expect(is_subclass_of(SchoolExtracurricularService::class, SchoolExtracurriculars::class))->toBeTrue();
});
