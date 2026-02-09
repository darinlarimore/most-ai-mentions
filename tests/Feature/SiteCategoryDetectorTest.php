<?php

use App\Enums\SiteCategory;
use App\Services\SiteCategoryDetector;

beforeEach(function () {
    $this->detector = new SiteCategoryDetector;
});

it('detects saas from meta description', function () {
    $html = '<html><head><meta name="description" content="The leading SaaS platform with pricing plans and free trial for cloud-based teams"></head><body></body></html>';

    expect($this->detector->detect($html))->toBe(SiteCategory::Saas);
});

it('detects healthcare from og tags', function () {
    $html = '<html><head><meta property="og:description" content="A healthcare platform for clinical diagnostics and patient care"></head><body></body></html>';

    expect($this->detector->detect($html))->toBe(SiteCategory::Healthcare);
});

it('detects finance from json-ld', function () {
    $jsonLd = json_encode([
        '@type' => 'Organization',
        'name' => 'FinCorp',
        'description' => 'Leading fintech company providing banking and investment solutions for wealth management',
    ]);
    $html = "<html><head><script type=\"application/ld+json\">{$jsonLd}</script></head><body></body></html>";

    expect($this->detector->detect($html))->toBe(SiteCategory::Finance);
});

it('detects tech from title and keywords', function () {
    $html = '<html><head><title>AI Research Lab</title><meta name="keywords" content="artificial intelligence, machine learning, deep learning, neural network"></head><body></body></html>';

    expect($this->detector->detect($html))->toBe(SiteCategory::Tech);
});

it('returns other for weak matches', function () {
    $html = '<html><head><title>Hello World</title><meta name="description" content="A simple website about things"></head><body></body></html>';

    expect($this->detector->detect($html))->toBe(SiteCategory::Other);
});

it('returns other for empty html', function () {
    expect($this->detector->detect(''))->toBe(SiteCategory::Other);
});

it('handles json-ld graph arrays', function () {
    $jsonLd = json_encode([
        '@graph' => [
            ['@type' => 'WebSite', 'name' => 'EduPlatform', 'description' => 'Online courses and e-learning'],
            ['@type' => 'Organization', 'description' => 'An edtech company providing learning management and training platform for students'],
        ],
    ]);
    $html = "<html><head><script type=\"application/ld+json\">{$jsonLd}</script></head><body></body></html>";

    expect($this->detector->detect($html))->toBe(SiteCategory::Education);
});

it('extracts metadata from multiple sources', function () {
    $html = '<html><head><title>Test Title</title><meta name="description" content="Test description"><meta name="keywords" content="test,keywords"><meta property="og:title" content="OG Title"><meta property="og:description" content="OG description"></head><body></body></html>';

    $text = $this->detector->extractMetadataText($html);

    expect($text)
        ->toContain('test title')
        ->toContain('test description')
        ->toContain('test,keywords')
        ->toContain('og title')
        ->toContain('og description');
});

it('requires minimum threshold of matches', function () {
    // Only one "fintech" mention — below threshold of 2
    $html = '<html><head><meta name="description" content="We are a fintech company"></head><body></body></html>';

    expect($this->detector->detect($html))->toBe(SiteCategory::Other);
});

it('handles reversed meta attribute order', function () {
    $html = '<html><head><meta content="A healthcare platform for clinical care" name="description"></head><body></body></html>';

    $text = $this->detector->extractMetadataText($html);

    expect($text)->toContain('healthcare platform for clinical care');
});
