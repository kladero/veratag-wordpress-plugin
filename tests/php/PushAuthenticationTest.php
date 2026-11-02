<?php

declare(strict_types=1);

use VeraTag\Features\PushApiReceiving\{PushApiReceivingController,PushApiReceivingRouteRegistrar};
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpConfig;
use VeraTag\Settings\OptionKeys\SettingKey;

function fixture_push_authorize(string $header, bool $enabled = true, string $secret = 'TEST_SYNTHETIC_PUSH_KEY'): bool|WP_Error
{
    $GLOBALS['test_options'][SettingKey::PUSH_API_ENABLED] = $enabled;
    $GLOBALS['test_options'][SettingKey::PUSH_API_SECRET] = $secret;
    $controller = (new ReflectionClass(PushApiReceivingController::class))->newInstanceWithoutConstructor();
    $registrar = new PushApiReceivingRouteRegistrar($controller, new GlobalSettings(new HttpConfig()));
    return $registrar->isAuthenticPushRequest(new WP_REST_Request($header));
}
test('push auth: matching synthetic bearer secret accepted', static function (): void {
    same(true, fixture_push_authorize('Bearer TEST_SYNTHETIC_PUSH_KEY'));
});
test('push auth: case-insensitive bearer scheme accepted', static function (): void {
    same(true, fixture_push_authorize('bearer TEST_SYNTHETIC_PUSH_KEY'));
});
test('push auth: trailing whitespace around header trimmed', static function (): void {
    same(true, fixture_push_authorize('  Bearer TEST_SYNTHETIC_PUSH_KEY   '));
});
test('push auth: disabled endpoint rejects even valid bearer secret', static function (): void {
    error_code('veratag_push_api_disabled', fixture_push_authorize('Bearer TEST_SYNTHETIC_PUSH_KEY', false));
});
test('push auth: missing bearer rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize(''));
});
test('push auth: bare key rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('TEST_SYNTHETIC_PUSH_KEY'));
});
test('push auth: bearer with different key rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('Bearer TEST_INVALID_KEY'));
});
test('push auth: empty configured secret never permits request', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('Bearer ', true, ''));
});
test('push auth: exact key required (prefix alone rejected)', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('Bearer TEST_SYNTHETIC'));
});
test('push auth: extra suffix rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('Bearer TEST_SYNTHETIC_PUSH_KEY_EXTRA'));
});
test('push auth: wrong scheme rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize('Basic TEST_SYNTHETIC_PUSH_KEY'));
});
test('push auth: embedded newline/multiple bearer tokens rejected', static function (): void {
    error_code('veratag_push_api_unauthorized', fixture_push_authorize("Bearer TEST_SYNTHETIC_PUSH_KEY\nBearer X"));
});
