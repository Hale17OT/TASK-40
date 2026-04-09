<?php

use Illuminate\Console\Scheduling\Schedule;

test('harborbite:check-alerts is registered in scheduler', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $found = $events->contains(fn ($event) => str_contains($event->command ?? '', 'harborbite:check-alerts'));
    expect($found)->toBeTrue();
});

test('harborbite:reconcile-payments is registered in scheduler', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $found = $events->contains(fn ($event) => str_contains($event->command ?? '', 'harborbite:reconcile-payments'));
    expect($found)->toBeTrue();
});
