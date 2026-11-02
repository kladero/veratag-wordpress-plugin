<?php

namespace VeraTag\Features\VerificationFileConfiguration;

use WP_Error;

final class VerificationFileConfigurationService
{
    private const VERIFICATION_FILE_PREFIX = 'veratag-verify-';
    private const VERIFICATION_FILE_SUFFIX = '.txt';

    public function getVerificationFileState(): array
    {
        $configured_verification_file_path = $this->findConfiguredVerificationFilePath();

        if (null === $configured_verification_file_path) {
            return array(
                'exists' => false,
                'token'  => '',
                'url'    => '',
            );
        }

        $verification_file_name  = basename($configured_verification_file_path);
        $verification_file_token = substr(
            $verification_file_name,
            strlen(self::VERIFICATION_FILE_PREFIX),
            -strlen(self::VERIFICATION_FILE_SUFFIX)
        );

        return array(
            'exists' => true,
            'token'  => $verification_file_token,
            'url'    => home_url('/.well-known/' . $verification_file_name),
        );
    }

    public function configureVerificationFile(string $verification_file_token): true|WP_Error
    {
        $normalized_token = $this->normalizeVerificationFileToken($verification_file_token);

        if ('' === $normalized_token) {
            return new WP_Error(
                'veratag_settings_invalid_verification_file_token',
                'verificationFileToken must not be empty.',
                array( 'status' => 400 )
            );
        }

        if (1 !== preg_match('/^[A-Za-z0-9_-]+$/', $normalized_token)) {
            return new WP_Error(
                'veratag_settings_invalid_verification_file_token_format',
                'verificationFileToken may only contain letters, numbers, underscores, and dashes.',
                array( 'status' => 400 )
            );
        }

        $current_verification_file_state = $this->getVerificationFileState();
        if (
            true === $current_verification_file_state['exists']
            && $current_verification_file_state['token'] !== $normalized_token
        ) {
            return new WP_Error(
                'veratag_settings_verification_file_already_exists',
                'An existing verification file must be removed before configuring a new one.',
                array( 'status' => 409 )
            );
        }

        $well_known_directory = $this->getWellKnownDirectoryPath();

        if (! is_dir($well_known_directory) && ! wp_mkdir_p($well_known_directory)) {
            return new WP_Error(
                'veratag_settings_failed_to_create_well_known_directory',
                'Unable to create the .well-known directory.',
                array( 'status' => 500 )
            );
        }

        $verification_file_path = $this->getVerificationFilePathForToken($normalized_token);

        if (false === file_put_contents($verification_file_path, $normalized_token)) {
            return new WP_Error(
                'veratag_settings_failed_to_write_verification_file',
                'Unable to create the verification file.',
                array( 'status' => 500 )
            );
        }

        return true;
    }

    public function removeConfiguredVerificationFile(): true|WP_Error
    {
        $configured_verification_file_paths = $this->findConfiguredVerificationFilePaths();

        foreach ($configured_verification_file_paths as $configured_verification_file_path) {
            if (! unlink($configured_verification_file_path)) {
                return new WP_Error(
                    'veratag_settings_failed_to_delete_verification_file',
                    'Unable to delete the existing verification file.',
                    array( 'status' => 500 )
                );
            }
        }
        if (! $this->removeWellKnownDirectoryIfEmpty()) {
            return new WP_Error(
                'veratag_settings_failed_to_delete_empty_well_known_directory',
                'Unable to delete the empty .well-known directory.',
                array( 'status' => 500 )
            );
        }

        return true;
    }

    /** Removes the shared directory only when no other application has files in it. */
    private function removeWellKnownDirectoryIfEmpty(): bool
    {
        $well_known_directory = $this->getWellKnownDirectoryPath();
        if (! is_dir($well_known_directory)) {
            return true;
        }
        $directory_entries = scandir($well_known_directory);
        if (false === $directory_entries) {
            return false;
        }
        if (array( '.', '..' ) !== $directory_entries) {
            return true;
        }
        return rmdir($well_known_directory); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    }

    private function getWellKnownDirectoryPath(): string
    {
        return untrailingslashit(ABSPATH) . '/.well-known';
    }

    private function getVerificationFilePathForToken(string $verification_file_token): string
    {
        return $this->getWellKnownDirectoryPath()
            . '/'
            . self::VERIFICATION_FILE_PREFIX
            . $verification_file_token
            . self::VERIFICATION_FILE_SUFFIX;
    }

    private function normalizeVerificationFileToken(string $verification_file_token): string
    {
        return trim($verification_file_token);
    }

    private function findConfiguredVerificationFilePath(): ?string
    {
        $configured_verification_file_paths = $this->findConfiguredVerificationFilePaths();

        if (array() === $configured_verification_file_paths) {
            return null;
        }

        return $configured_verification_file_paths[0];
    }

    private function findConfiguredVerificationFilePaths(): array
    {
        $well_known_directory = $this->getWellKnownDirectoryPath();

        if (! is_dir($well_known_directory)) {
            return array();
        }

        $configured_verification_file_paths = glob(
            $well_known_directory
            . '/'
            . self::VERIFICATION_FILE_PREFIX
            . '*'
            . self::VERIFICATION_FILE_SUFFIX
        );

        if (false === $configured_verification_file_paths) {
            return array();
        }

        sort($configured_verification_file_paths);

        return array_values(
            array_filter(
                $configured_verification_file_paths,
                static fn (mixed $configured_verification_file_path): bool => is_string($configured_verification_file_path)
            )
        );
    }
}
