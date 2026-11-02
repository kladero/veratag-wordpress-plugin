<?php

declare(strict_types=1);

use VeraTag\Features\PushApiReceiving\PushApiReceivingController;

/**
 * Contract-level early validation of the REAL controller's instruction dispatch.
 * This deliberately never reaches WordPress persistence/transactions: those
 * require a real database and are not covered by the offline test harness.
 */
function fixture_push_instruction(string $instruction, array $data): bool|WP_Error
{
    $controller = (new ReflectionClass(PushApiReceivingController::class))->newInstanceWithoutConstructor();
    return (new ReflectionMethod($controller, 'applyInstruction'))->invoke($controller, $instruction, $data);
}

/** Confirm the error is about the intended field, not some other missing input. */
function fixture_push_invalid_field(string $instruction, array $data, string $field): void
{
    $result = fixture_push_instruction($instruction, $data);
    error_code('veratag_push_api_invalid_data', $result);
    truth(str_contains($result->get_error_message(), $field), "Expected error naming {$field}");
}

$refFields = [
    'WEB_CONTENT_UPSERT' => 'pageUrl',
    'WEB_CONTENT_DELETE' => 'pageUrl',
    'WEB_CONFIGURATION_UPSERT' => 'configurationRef',
    'WEB_CONFIGURATION_DELETE' => 'configurationRef',
    'WEB_PLACEMENT_UPSERT' => 'placementRef',
    'WEB_PLACEMENT_DELETE' => 'placementRef',
    'WEB_TEXT_TEMPLATE_UPSERT' => 'textTemplateRef',
    'WEB_TEXT_TEMPLATE_DELETE' => 'textTemplateRef',
];

foreach ($refFields as $instruction => $field) {
    test("push instruction: {$instruction} rejects a missing {$field}", static function () use ($instruction, $field): void {
        fixture_push_invalid_field($instruction, [], $field);
    });

    test("push instruction: {$instruction} rejects a whitespace-only {$field}", static function () use ($instruction, $field): void {
        fixture_push_invalid_field($instruction, [$field => '  '], $field);
    });

    test("push instruction: {$instruction} rejects a non-string {$field}", static function () use ($instruction, $field): void {
        fixture_push_invalid_field($instruction, [$field => 123], $field);
    });
}

$missingBodies = [
    'WEB_CONTENT_UPSERT' => ['pageUrl' => 'https://site.example.test/article/'],
    'WEB_CONFIGURATION_UPSERT' => ['configurationRef' => 'TEST_CONFIG_REF'],
    'WEB_PLACEMENT_UPSERT' => ['placementRef' => 'TEST_PLACEMENT_REF'],
    'WEB_TEXT_TEMPLATE_UPSERT' => ['textTemplateRef' => 'TEST_TEMPLATE_REF'],
];
foreach ($missingBodies as $instruction => $data) {
    test("push instruction: {$instruction} rejects a missing object body", static function () use ($instruction, $data): void {
        $result = fixture_push_instruction($instruction, $data);
        error_code('veratag_push_api_invalid_data', $result);
        $expected_body = [
            'WEB_CONTENT_UPSERT' => 'content',
            'WEB_CONFIGURATION_UPSERT' => 'configuration',
            'WEB_PLACEMENT_UPSERT' => 'placement',
            'WEB_TEXT_TEMPLATE_UPSERT' => 'textTemplate',
        ][$instruction];
        same("{$instruction} requires {$expected_body}.", $result->get_error_message());
    });
    test("push instruction: {$instruction} rejects scalar object body", static function () use ($instruction, $data): void {
        $key = [
            'WEB_CONTENT_UPSERT' => 'content',
            'WEB_CONFIGURATION_UPSERT' => 'configuration',
            'WEB_PLACEMENT_UPSERT' => 'placement',
            'WEB_TEXT_TEMPLATE_UPSERT' => 'textTemplate',
        ][$instruction];
        $result = fixture_push_instruction($instruction, $data + [$key => 'invalid']);
        error_code('veratag_push_api_invalid_data', $result);
        same("{$instruction} requires {$key}.", $result->get_error_message());
    });
}

test('push instruction: unknown instruction is rejected rather than ignored', static function (): void {
    error_code('veratag_push_api_unknown_instruction', fixture_push_instruction('SYNTHETIC_UNKNOWN', []));
});

test('push instruction: content delete needs both pageUrl and contentId', static function (): void {
    fixture_push_invalid_field('WEB_CONTENT_DELETE', [
        'pageUrl' => 'https://site.example.test/article/',
    ], 'contentId');
});
