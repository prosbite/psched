<?php

it('redirects guests from the root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
