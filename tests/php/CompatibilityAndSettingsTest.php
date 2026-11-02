<?php

declare(strict_types=1);

use VeraTag\Features\CompatibilityCheck\{CompatibilityStatus,CompatibilityCheckApiService};
use VeraTag\Settings\OptionValues\{ChannelVisitorDisclosureLanguageSettings,ChannelVisitorDisclosureLanguageMode};
use VeraTag\VeraTagBackend\HttpResponse;

test('compatibility: OK has null message', static function (): void {
    same(['status' => 'ok','message' => null], CompatibilityStatus::ok()->toArray());
});
test('compatibility: update required keeps backend message', static function (): void {
    same(['status' => 'update_required','message' => 'Synthetic update needed'], CompatibilityStatus::updateRequired('Synthetic update needed')->toArray());
});
test('compatibility: disabled keeps backend message', static function (): void {
    same(['status' => 'disabled','message' => 'Synthetic disabled'], CompatibilityStatus::disabled('Synthetic disabled')->toArray());
});
foreach (['CMS_PLUGIN_UPDATE_REQUIRED' => 'update_required', 'CMS_PLUGIN_DISABLED' => 'disabled', 'OTHER_PERMISSION_FAILURE' => 'ok'] as $backendStatus => $expected) {
    test("compatibility: precise backend code {$backendStatus} maps to {$expected}", static function () use ($backendStatus, $expected): void {
        // Invoke the actual response mapping; there is no external HTTP request.
        $service = (new ReflectionClass(CompatibilityCheckApiService::class))->newInstanceWithoutConstructor();
        $response = new HttpResponse(403, 'Forbidden', [], json_encode(['status' => $backendStatus,'message' => 'Synthetic status message']));
        $mapped = (new ReflectionMethod($service, 'mapResponse'))->invoke($service, $response);
        same($expected, $mapped->toArray()['status']);
    });
}
test('compatibility: current mapping treats generic HTTP 403 without known code as OK', static function (): void {
    $service = (new ReflectionClass(CompatibilityCheckApiService::class))->newInstanceWithoutConstructor();
    $response = new HttpResponse(403, 'Forbidden', [], '{"message":"Not permitted"}');
    same('ok', (new ReflectionMethod($service, 'mapResponse'))->invoke($service, $response)->toArray()['status']);
});
test('compatibility: missing update message uses default', static function (): void {
    $service = (new ReflectionClass(CompatibilityCheckApiService::class))->newInstanceWithoutConstructor();
    $response = new HttpResponse(426, 'Upgrade Required', [], '{"status":"CMS_PLUGIN_UPDATE_REQUIRED"}');
    $result = (new ReflectionMethod($service, 'mapResponse'))->invoke($service, $response)->toArray();
    same('update_required', $result['status']);
    truth(str_contains($result['message'], 'update'));
});
foreach (['en','de','fr','es','pl'] as $language) {
    test("settings: roundtrip visitor-preferred {$language}", static function () use ($language): void {
        $settings = ChannelVisitorDisclosureLanguageSettings::fromArray(['preferredMode' => 'visitor_preferred','fallbackLanguageBcp47' => $language]);
        same('visitor_preferred', $settings->getPreferredMode());
        same($language, $settings->getFallbackLanguageBcp47());
        truth($settings->hasBeenSynced());
        same(['preferredMode' => 'visitor_preferred','fallbackLanguageBcp47' => $language], $settings->toArray());
    });
}
test('settings: initial unsynced state remains empty without defaults', static function (): void {
    $settings = ChannelVisitorDisclosureLanguageSettings::createInitial();
    same('', $settings->getPreferredMode());
    same('', $settings->getFallbackLanguageBcp47());
    same(false, $settings->hasBeenSynced());
});
test('settings: incomplete configuration is not marked synchronized', static function (): void {
    $settings = ChannelVisitorDisclosureLanguageSettings::fromArray(['preferredMode' => 'visitor_preferred']);
    same(false, $settings->hasBeenSynced());
});
test('settings: missing mode is not marked synchronized', static function (): void {
    $settings = ChannelVisitorDisclosureLanguageSettings::fromArray(['fallbackLanguageBcp47' => 'en']);
    same(false, $settings->hasBeenSynced());
});
test('settings: extra whitespace trimmed on deserialize', static function (): void {
    $settings = ChannelVisitorDisclosureLanguageSettings::fromArray(['preferredMode' => ' visitor_preferred ','fallbackLanguageBcp47' => ' de ']);
    same('visitor_preferred', $settings->getPreferredMode());
    same('de', $settings->getFallbackLanguageBcp47());
});
