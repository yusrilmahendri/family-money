<?php

test('the application returns a successful response', function () {
    $this->get('/')->assertOk()->assertSee('Keuangan Kita');

    $this->get('/apps')->assertRedirect(route('home'));
});
