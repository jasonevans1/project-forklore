<?php

it('serves the Oswald display font from the font CDN in the page head', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('oswald:500,700', false);
});

it('serves the JetBrains Mono ticket font from the font CDN in the page head', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('jetbrains-mono:400,500', false);
});

it('still serves the existing Instrument Sans body font', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('instrument-sans:400,500,600', false);
});

it('declares the diner page tokens in the light theme block', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@theme static {');
    expect($css)->toContain("--font-display: 'Oswald', 'Arial Narrow', sans-serif;");
    expect($css)->toContain("--font-mono-ticket: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;");
    expect($css)->toContain('--color-page: #f7f2e8;');
    expect($css)->toContain('--color-ink: #241c12;');
    expect($css)->toContain('--color-ticket-bg: #f2e9d6;');
    expect($css)->toContain('--color-ticket-ink: #221a10;');
    expect($css)->toContain('--color-ticket-line: #c9b98f;');
    expect($css)->toContain('--color-ticket-accent: #8a5220;');
});

it('declares the diner page token overrides in the dark theme block', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    $darkBlockStart = strpos($css, '.dark {');
    $darkBlock = substr($css, $darkBlockStart, strpos($css, '}', $darkBlockStart) - $darkBlockStart);

    expect($darkBlock)->toContain('--color-page: #1c1712;');
    expect($darkBlock)->toContain('--color-ink: #f2e9d6;');
    expect($darkBlock)->toContain('--color-accent: #e3a742;');
});
