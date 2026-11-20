<?php

use App\Enums\DemoRequestKind;
use App\Models\DemoRequest;

it('shows the landing page with all 17 modules', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertSee('Your whole school, from admission to', false)
        ->assertSee('17 modules · one login')
        ->assertSee('Activity Log Sessions')
        ->assertSee('Pay per student, per term, in Naira.');

    expect(substr_count($response->getContent(), '<article'))->toBeGreaterThanOrEqual(17);
});

it('shows plan prices once they are configured', function () {
    config(['taaleem.marketing.prices.standard' => 1500]);

    $this->get(route('home'))->assertSee('₦1,500')->assertSee('Get a quote');
});

it('shows the contact page with the chosen enquiry type', function () {
    $this->get(route('contact', ['kind' => 'trial']))
        ->assertOk()
        ->assertSee('Let’s get your school', false)
        ->assertSee('Start my free trial');
});

it('saves a demo request from the hero form with the phone number normalised', function () {
    $this->post(route('demo-requests.store'), ['source' => 'hero', 'kind' => 'demo', 'contact' => '0803 123 4567'])
        ->assertRedirect(route('home').'#hero')
        ->assertSessionHas('demo_sent', 'hero');

    expect(DemoRequest::query()->sole())
        ->phone->toBe('+2348031234567')
        ->email->toBeNull()
        ->kind->toBe(DemoRequestKind::Demo);
});

it('saves a demo request with an email address from the closing call to action', function () {
    $this->post(route('demo-requests.store'), ['source' => 'cta', 'contact' => 'Bursar@School.org'])
        ->assertRedirect(route('home').'#cta');

    expect(DemoRequest::query()->sole()->email)->toBe('bursar@school.org');
});

it('rejects a contact that is neither an email nor a Nigerian phone number', function () {
    $this->post(route('demo-requests.store'), ['source' => 'hero', 'contact' => 'call me'])
        ->assertSessionHasErrorsIn('hero', 'contact');

    expect(DemoRequest::query()->count())->toBe(0);
});

it('saves a full enquiry from the contact page', function () {
    $this->post(route('demo-requests.store'), [
        'source' => 'contact',
        'kind' => 'trial',
        'name' => 'Fatima Ahmed',
        'email' => 'fatima@alnoor.org',
        'school_name' => 'Al-Noor Weekend School',
        'student_count' => '150–400',
        'message' => 'We run on weekends.',
    ])->assertRedirect(route('contact'));

    expect(DemoRequest::query()->sole())
        ->kind->toBe(DemoRequestKind::Trial)
        ->school_name->toBe('Al-Noor Weekend School')
        ->student_count->toBe('150–400');

    $this->get(route('contact'))->assertSee('Your message is with our team.');
});

it('requires a name and email on the contact page', function () {
    $this->post(route('demo-requests.store'), ['source' => 'contact'])
        ->assertSessionHasErrorsIn('contact', ['name', 'email']);
});

it('quietly drops submissions that fill the hidden spam field', function () {
    $this->post(route('demo-requests.store'), ['source' => 'hero', 'contact' => 'bot@spam.test', 'website' => 'http://spam.test'])
        ->assertSessionHas('demo_sent', 'hero');

    expect(DemoRequest::query()->count())->toBe(0);
});

it('shows the legal pages', function (string $route, string $title) {
    $this->get(route($route))->assertOk()->assertSee($title);
})->with([
    ['privacy', 'Privacy policy'],
    ['terms', 'Terms of service'],
]);
