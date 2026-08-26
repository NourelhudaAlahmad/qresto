<?php

test('dev components route is unreachable outside local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/dev/components')->assertNotFound();
});