<?php

test('the application returns a successful response', function () {
    $this->get('/')->assertOk()->assertSee('ARUSKU')->assertDontSee('Keuangan Kita');

    $this->get('/apps')->assertRedirect(route('home'));
});
