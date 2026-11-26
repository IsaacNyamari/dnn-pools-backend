<?php

test('the root address leads to sign-in, or to the home page once signed in', function () {
    $this->get(route('home'))->assertRedirect(route('login'));

    $this->actingAs(director(institution()))->get(route('home'))->assertRedirect(route('app.home'));
});

test('a signed-in visitor to the sign-in page is sent home', function () {
    $this->actingAs(director(institution()))->get(route('login'))->assertRedirect('/home');
});
