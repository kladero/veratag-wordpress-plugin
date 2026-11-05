<?php

namespace VeraTag\Features\VerificationFileConfiguration;

use VeraTag\Filesystem\FileOperations;
use WP_Error;

final class VerificationFileConfigurationService
{
    private const VERIFICATION_FILE_PREFIX = 'veratag-verify-';
    private const VERIFICATION_FILE_SUFFIX = '.txt';

    public function __construct(private FileOperations $file_operations)
    {
    }

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

        if (! $this->file_operations->createDirectory($well_known_directory)) {
            return new WP_Error(
                'veratag_settings_failed_to_create_well_known_directory',
                'Unable to create the .well-known directory.',
                array( 'status' => 500 )
            );
        }

        $verification_file_path = $this->getVerificationFilePathForToken($normalized_token);

        if (! $this->file_operations->writeAtomically($verification_file_path, $normalized_token)) {
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
            if (
                ! $this->file_operations->deleteFileWithinDirectory(
                    $configured_verification_file_path,
                    $this->getWellKnownDirectoryPath()
                )
            ) {
                return new WP_Error(
                    'veratag_settings_failed_to_delete_verification_file',
                    'Unable to delete the existing verification file.',
                    array( 'status' => 500 )
                );
            }
        }
        return true;
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

        if (! $this->file_operations->directoryExists($well_known_directory)) {
            return array();
        }

        $directory_entries = $this->file_operations->listDirectory($well_known_directory);
        if (null === $directory_entries) {
            return array();
        }

        $configured_verification_file_paths = array();
        foreach ($directory_entries as $directory_entry) {
            if (1 !== preg_match('/^veratag-verify-[A-Za-z0-9_-]+\.txt$/D', $directory_entry)) {
                continue;
            }
            $file_path = $well_known_directory . '/' . $directory_entry;
            if ($this->file_operations->isRegularFile($file_path)) {
                $configured_verification_file_paths[] = $file_path;
            }
        }
        sort($configured_verification_file_paths);
        return $configured_verification_file_paths;
    }
}
