<?php

declare(strict_types=1);

use VeraTag\Features\FrontendServing\DisclosuresRendering\RenderScriptFileWriter;
use VeraTag\Features\VerificationFileConfiguration\VerificationFileConfigurationService;
use VeraTag\Filesystem\WordPressFileOperations;

test('WordPress file operations atomically create and replace complete files', static function (): void {
    $operations = new WordPressFileOperations();
    $directory = untrailingslashit(ABSPATH) . '/atomic';
    $target = $directory . '/payload.js';

    same(false, $operations->writeAtomically($target, 'before'), 'A missing target directory must fail closed');
    truth($operations->createDirectory($directory));
    truth($operations->writeAtomically($target, 'before'));
    same('before', file_get_contents($target));
    truth($operations->writeAtomically($target, 'after-complete'));
    same('after-complete', file_get_contents($target));
    same([], glob($target . '.tmp-*'), 'Atomic writes must not leave temporary files');
    same(['payload.js'], $operations->listDirectory($directory));
    truth(null !== $operations->getModifiedTime($target));
});

test('WordPress file deletion is confined to its declared directory', static function (): void {
    $operations = new WordPressFileOperations();
    $owned_directory = untrailingslashit(ABSPATH) . '/owned';
    $outside_directory = untrailingslashit(ABSPATH) . '/outside';
    truth($operations->createDirectory($owned_directory));
    truth($operations->createDirectory($outside_directory));
    $owned_file = $owned_directory . '/owned.txt';
    $outside_file = $outside_directory . '/outside.txt';
    truth($operations->writeAtomically($owned_file, 'owned'));
    truth($operations->writeAtomically($outside_file, 'outside'));

    same(false, $operations->deleteFileWithinDirectory($outside_file, $owned_directory));
    truth(file_exists($outside_file), 'A file outside the declared directory must remain');
    truth($operations->deleteFileWithinDirectory($owned_file, $owned_directory));
    same(false, file_exists($owned_file));
    truth($operations->deleteFileWithinDirectory($owned_file, $owned_directory), 'Deleting an absent file is idempotent');
});

test('renderer writer uses atomic storage and retains empty plugin directories', static function (): void {
    $operations = new WordPressFileOperations();
    $writer = new RenderScriptFileWriter($operations);
    $render_directory = $GLOBALS['test_upload_basedir'] . '/veratag/render-scripts';

    truth($writer->write('renderer.js', 'window.renderer = 1;'));
    same('window.renderer = 1;', file_get_contents($render_directory . '/renderer.js'));
    same(
        'https://site.example.test/wp-content/uploads/veratag/render-scripts/renderer.js',
        $writer->getPublicUrl('renderer.js')
    );
    truth(null !== $writer->getFileModifiedTime('renderer.js'));
    same(false, $writer->write('.htaccess', 'unsafe'));
    same(false, $writer->write('payload.php.js', 'unsafe'));
    same(false, $writer->write('payload.txt', 'unsafe'));

    truth($writer->deleteAll());
    truth(is_dir($render_directory), 'Renderer directory must remain after deleting its files');
    truth(is_dir(dirname($render_directory)), 'Plugin uploads directory must remain after deleting its files');
    same([], $operations->listDirectory($render_directory));
});

test('renderer writer purges unsafe legacy files and fails on unexpected directories', static function (): void {
    $operations = new WordPressFileOperations();
    $render_directory = $GLOBALS['test_upload_basedir'] . '/veratag/render-scripts';
    truth($operations->createDirectory($render_directory));
    truth($operations->writeAtomically($render_directory . '/legacy.php', '<?php'));
    truth($operations->writeAtomically($render_directory . '/existing.js', 'existing'));

    $writer = new RenderScriptFileWriter($operations);
    truth($writer->write('current.js', 'current'));
    same(false, file_exists($render_directory . '/legacy.php'));
    truth(file_exists($render_directory . '/existing.js'));
    truth(file_exists($render_directory . '/current.js'));

    reset_fixtures();
    $render_directory = $GLOBALS['test_upload_basedir'] . '/veratag/render-scripts';
    truth($operations->createDirectory($render_directory . '/unexpected'));
    $writer = new RenderScriptFileWriter($operations);
    same(false, $writer->write('current.js', 'current'));
    truth(is_dir($render_directory . '/unexpected'));
});

test('verification service atomically manages only exact vera tag verification files', static function (): void {
    $operations = new WordPressFileOperations();
    $service = new VerificationFileConfigurationService($operations);
    $well_known_directory = untrailingslashit(ABSPATH) . '/.well-known';

    error_code('veratag_settings_invalid_verification_file_token', $service->configureVerificationFile('   '));
    error_code('veratag_settings_invalid_verification_file_token_format', $service->configureVerificationFile('bad token'));
    same(true, $service->configureVerificationFile('token_1-A'));
    same('token_1-A', file_get_contents($well_known_directory . '/veratag-verify-token_1-A.txt'));
    same(
        [
            'exists' => true,
            'token' => 'token_1-A',
            'url' => 'https://site.example.test/.well-known/veratag-verify-token_1-A.txt',
        ],
        $service->getVerificationFileState()
    );
    error_code(
        'veratag_settings_verification_file_already_exists',
        $service->configureVerificationFile('different-token')
    );

    truth($operations->writeAtomically($well_known_directory . '/security.txt', 'contact'));
    truth($operations->writeAtomically($well_known_directory . '/veratag-verify-bad token.txt', 'unrelated'));
    same(true, $service->removeConfiguredVerificationFile());
    truth(is_dir($well_known_directory), 'The shared .well-known directory must remain');
    truth(file_exists($well_known_directory . '/security.txt'));
    truth(file_exists($well_known_directory . '/veratag-verify-bad token.txt'));
    same(['exists' => false, 'token' => '', 'url' => ''], $service->getVerificationFileState());
});
