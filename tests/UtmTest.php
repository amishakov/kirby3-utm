<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';
test('instance', function () {
    $utm = new \Bnomei\Utm;

    expect($utm)->toBeInstanceOf(\Bnomei\Utm::class);
});

test('option', function () {
    $utm = new \Bnomei\Utm(['debug' => true]);

    expect($utm->option('debug'))->toBeTrue();
});

test('track', function () {
    $id = page('home')->id();

    $utm = new \Bnomei\Utm([
        'ip' => '169.150.197.101',
        'ipstack_access_key' => F::read(__DIR__.'/.ipstackkey'),
    ]);

    $count = $utm->count();

    $utm->track($id, [
        'utm_source' => 'UTM_SOURCE',
        'utm_medium' => 'UTM_MEDIUM',
        'utm_campaign' => 'UTM_CAMPAIGN',
        'utm_term' => 'UTM_TERM',
        'utm_content' => 'UTM_CONTENT',
    ]);

    expect($utm->count())->toEqual($count + 1);
});

test('rate limit', function () {
    $utm = new \Bnomei\Utm([
        'ratelimit_trials' => 5,
        'ip' => '123.123.123.123', // different ip than other tests because of ratelimit
    ]);

    // flush
    $utm->database()->execute('DELETE FROM utm WHERE id > 0');

    for ($n = 0; $n < 5; $n++) {
        expect($utm->track('home', [
            'utm_source' => 'UTM_SOURCE',
            'utm_medium' => 'UTM_MEDIUM',
            'utm_campaign' => 'UTM_CAMPAIGN',
            'utm_term' => 'UTM_TERM',
            'utm_content' => 'UTM_CONTENT',
        ]))->toBeTrue();
    }

    expect($utm->track('home', [
        'utm_source' => 'UTM_SOURCE',
        'utm_medium' => 'UTM_MEDIUM',
        'utm_campaign' => 'UTM_CAMPAIGN',
        'utm_term' => 'UTM_TERM',
        'utm_content' => 'UTM_CONTENT',
    ]))->toBeFalse();
});

test('many events', function () {
    $faker = Faker\Factory::create('en');
    $utm = new \Bnomei\Utm([
        'ratelimit_trials' => 999999, // allow mass creation
    ]);

    // flush
    $utm->database()->execute('DELETE FROM utm WHERE id > 0');

    $count = $utm->count();

    $days90to60 = new DatePeriod(new DateTime('now - 90 days'), new DateInterval('P1D'), new DateTime('now - 60 days'));
    foreach ($days90to60 as $day) {
        for ($c = 0; $c < 100; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    $days60to30 = new DatePeriod(new DateTime('now - 60 days'), new DateInterval('P1D'), new DateTime('now - 30 days'));
    foreach ($days60to30 as $day) {
        for ($c = 0; $c < 100; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    $days30to0 = new DatePeriod(new DateTime('now - 30 days'), new DateInterval('P1D'), new DateTime('now'));
    foreach ($days30to0 as $day) {
        for ($c = 0; $c < 300; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    expect($count < $utm->count())->toBeTrue();
});
function createEvent($utm, $faker, $day)
{
    $utm->track('home', [
        'visited_at' => $day->format('Y-m-d').' '.$faker->time('H:i:s', '23:59:59'),
        'iphash' => sha1(__DIR__.$faker->numberBetween(0, 200)),
        'country' => $faker->randomElement([
            'England',
            'France',
            'Germany',
            'Switzerland',
            'USA',
        ]),
        'city' => $faker->randomElement([
            'London',
            'Paris',
            'Berlin',
            'Zurich',
            'New York',
        ]),
        'useragent' => $faker->randomElement([
            'mobile',
            'tablet',
            'desktop',
        ]),
        'utm_source' => $faker->randomElement([
            'Destructiod',
            'Games Radar',
            'Metacritic',
            'GameSpot',
        ]),
        'utm_medium' => $faker->randomElement([
            'cpc',
            'email',
            'newsletter',
        ]),
        'utm_campaign' => $faker->randomElement([
            'Tunic', 'Tunic', 'Tunic',
            'Sifu', 'Sifu', 'Sifu',
            'Neon White', 'Neon White', 'Neon White',
            'Call Of Duty: Modern Warfare 2', 'Call Of Duty: Modern Warfare 2', 'Call Of Duty: Modern Warfare 2',
            'Immortality', 'Immortality', 'Immortality',
            'Xenoblade Chronicles 3', 'Xenoblade Chronicles 3', 'Xenoblade Chronicles 3',
            'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem',
            'Stray', 'Stray', 'Stray', 'Stray', 'Stray', 'Stray',
            'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West',
            'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring',
            'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök']),
        'utm_term' => $faker->word,
        'utm_content' => '',
    ]);
}
