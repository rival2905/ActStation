<?php

declare(strict_types=1);

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use Illuminate\Database\Eloquent\Collection;

test('event registration price label distinguishes missing free fixed and mixed prices', function (): void {
    $event = new Event;

    $event->setRelation('categories', new Collection);
    expect($event->registrationPriceLabel())->toBe('Belum tersedia');

    $event->setRelation('categories', new Collection([
        new EventCategory(['registration_fee' => 0]),
    ]));
    expect($event->registrationPriceLabel())->toBe('Gratis');

    $event->setRelation('categories', new Collection([
        new EventCategory(['registration_fee' => 150000]),
        new EventCategory(['registration_fee' => 150000]),
    ]));
    expect($event->registrationPriceLabel())->toBe('Rp 150.000');

    $event->setRelation('categories', new Collection([
        new EventCategory(['registration_fee' => 0]),
        new EventCategory(['registration_fee' => 250000]),
    ]));
    expect($event->registrationPriceLabel())->toBe('Mulai Gratis');

    $event->setRelation('categories', new Collection([
        new EventCategory(['registration_fee' => 100000]),
        new EventCategory(['registration_fee' => 250000]),
    ]));
    expect($event->registrationPriceLabel())->toBe('Mulai Rp 100.000');
});

test('category display labels show exact price and age bounds', function (): void {
    expect((new EventCategory(['registration_fee' => 0]))->registrationPriceLabel())->toBe('Gratis')
        ->and((new EventCategory(['registration_fee' => 125000]))->registrationPriceLabel())->toBe('Rp 125.000')
        ->and((new EventCategory)->ageRangeLabel())->toBe('Semua usia')
        ->and((new EventCategory(['max_age' => 12]))->ageRangeLabel())->toBe('Maks. 12 tahun')
        ->and((new EventCategory(['min_age' => 18]))->ageRangeLabel())->toBe('Min. 18 tahun')
        ->and((new EventCategory(['min_age' => 13, 'max_age' => 17]))->ageRangeLabel())->toBe('13–17 tahun');
});
