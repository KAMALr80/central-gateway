<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('opens the admin from the site root', function () {
    $this->get('/')->assertRedirect(route('admin.dashboard'));
});

it('sends a guest from the site root on to the login page', function () {
    $this->followingRedirects()->get('/')->assertOk();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('shows the admin dashboard to an administrator who opens the site root', function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->followingRedirects()->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});
