<?php

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard', ['current_team' => institution()->slug]))->assertRedirect(route('login'));
});

test('a director can visit the dashboard', function () {
    $team = institution();

    $this->actingAs(director($team))->get(route('dashboard', ['current_team' => $team->slug]))->assertOk()->assertSee($team->name);
});
