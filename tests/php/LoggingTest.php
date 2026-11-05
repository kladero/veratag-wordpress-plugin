<?php

declare(strict_types=1);

use VeraTag\Logging\WordPressLogger;

test('build-configured logger emits errors only when enabled', static function (): void {
    $log_path = untrailingslashit(ABSPATH) . '/runtime-errors.log';
    $previous_error_log = ini_get('error_log');
    if (false === $previous_error_log || false === ini_set('error_log', $log_path)) {
        throw new RuntimeException('Unable to redirect the PHP error log for the logger test.');
    }

    $disabled_logger = new WordPressLogger(false);
    $disabled_logger->error('disabled-message');
    $disabled_contents = file_exists($log_path) ? file_get_contents($log_path) : '';
    if (false === $disabled_contents) {
        throw new RuntimeException('Unable to read the disabled logger output.');
    }

    $enabled_logger = new WordPressLogger(true);
    $enabled_logger->error('enabled-message');
    $enabled_contents = file_get_contents($log_path);
    if (false === $enabled_contents) {
        throw new RuntimeException('Unable to read the enabled logger output.');
    }

    if (false === ini_set('error_log', $previous_error_log)) {
        throw new RuntimeException('Unable to restore the PHP error log after the logger test.');
    }

    same('', $disabled_contents, 'Disabled logging must produce no output');
    truth(str_contains($enabled_contents, 'enabled-message'));
    same(false, str_contains($enabled_contents, 'disabled-message'));
});
