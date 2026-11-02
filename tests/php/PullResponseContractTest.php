<?php

declare(strict_types=1);

use VeraTag\Features\PullSynchronization\PullSynchronizationApiResponseDto;
use VeraTag\Features\PullSynchronization\PullSynchronizationApiIssueDto;

function fixture_pull_data(): array
{
    return json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/pull-response.json'), true, 512, JSON_THROW_ON_ERROR);
}
test('pull DTO: empty response is representable and has zero items', static function (): void {
    $dto = PullSynchronizationApiResponseDto::fromArray([]);
    same(0, $dto->countRegisteredContentItems());
    same(0, $dto->countConfiguredContentItems());
    same(0, $dto->countUnconfiguredContentItems());
    same([], $dto->getPages());
    same([], $dto->getConfigurations());
});
test('pull DTO: supplied synthetic backend contract has coherent counts', static function (): void {
    $dto = PullSynchronizationApiResponseDto::fromArray(fixture_pull_data());
    same(count(fixture_pull_data()['configurations']), count($dto->getConfigurations()));
    same(count(fixture_pull_data()['pages']), count($dto->getPages()));
    same(3, $dto->countRegisteredContentItems());
    same(2, $dto->countConfiguredContentItems());
    same(1, $dto->countUnconfiguredContentItems());
    same(array_keys(fixture_pull_data()['configurations']), array_keys($dto->getConfigurations()));
});
test('pull DTO: toArray roundtrip preserves record counts and keys', static function (): void {
    $dto = PullSynchronizationApiResponseDto::fromArray(fixture_pull_data());
    $roundtrip = PullSynchronizationApiResponseDto::fromArray($dto->toArray());
    same($dto->countRegisteredContentItems(), $roundtrip->countRegisteredContentItems());
    same(array_keys($dto->getPlacements()), array_keys($roundtrip->getPlacements()));
    same($dto->toArray(), $roundtrip->toArray());
});
foreach (['configurations', 'placements', 'textTemplates', 'pages'] as $field) {
    test("pull DTO: invalid {$field} input gracefully becomes empty", static function () use ($field): void {
        $data = fixture_pull_data();
        $data[$field] = 'wrong type';
        $dto = PullSynchronizationApiResponseDto::fromArray($data);
        $result = $dto->toArray();
        same([], $result[$field]);
    });
}
foreach (['action_required','action_recommended'] as $impact) {
    test("issues: {$impact} impact is preserved from backend issue DTO", static function () use ($impact): void {
        $input = ['id' => 3, 'reason' => 'synthetic-' . $impact, 'impact' => $impact, 'message' => 'Testing issue', 'detectedAt' => '2026-10-29T12:00:00Z'];
        $dto = PullSynchronizationApiIssueDto::fromArray($input);
        same($impact, $dto->getImpact());
        same($input, $dto->toArray());
    });
}
test('issues: missing fields normalized by production DTO', static function (): void {
    $dto = PullSynchronizationApiIssueDto::fromArray([]);
    same('', $dto->getReason());
    same('', $dto->getImpact());
    same('', $dto->getMessage());
});
