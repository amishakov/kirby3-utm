<?php

use Spatie\Ray\Ray;

const KIRBY_HELPER_DUMP = false;
require_once __DIR__.'/../vendor/autoload.php';

if (class_exists(Ray::class)) {
    Ray::$enabled = false;
    Ray::$fakeUuid = '00000000-0000-4000-8000-000000000000';
}

echo (new Kirby)->render();
