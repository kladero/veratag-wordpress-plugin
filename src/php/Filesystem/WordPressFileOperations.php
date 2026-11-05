<?php

namespace VeraTag\Filesystem;

/**
 * Owns the plugin's filesystem operations and their WordPress-specific safety rules.
 *
 * Atomic writes intentionally use a same-directory temporary file and one rename. WordPress's direct
 * WP_Filesystem::put_contents() implementation writes to the public target with fopen()/fwrite(), which
 * could expose a partially written renderer script to visitors. The necessary low-level operations are
 * therefore isolated here and each related PHPCS exclusion is kept narrowly scoped to one operation.
 */
final class WordPressFileOperations implements FileOperations
{
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function directoryExists(string $path): bool
    {
        return is_dir($path);
    }

    public function isRegularFile(string $path): bool
    {
        return is_file($path);
    }

    public function createDirectory(string $path): bool
    {
        return $this->directoryExists($path) || wp_mkdir_p($path);
    }

    /** @return list<string>|null */
    public function listDirectory(string $path): ?array
    {
        $entries = scandir($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
        if (false === $entries) {
            return null;
        }

        return array_values(
            array_filter(
                $entries,
                static fn (string $entry): bool => '.' !== $entry && '..' !== $entry
            )
        );
    }

    public function getModifiedTime(string $path): ?int
    {
        $modified_time = filemtime($path);
        return false === $modified_time ? null : $modified_time;
    }

    public function writeAtomically(string $target_path, string $contents): bool
    {
        $directory_path = dirname($target_path);
        if (! $this->directoryExists($directory_path)) {
            return false;
        }

        $temporary_path = $target_path . '.tmp-' . bin2hex(random_bytes(16));
        $temporary_file = fopen($temporary_path, 'x+b'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if (false === $temporary_file) {
            return false;
        }

        $expected_bytes = strlen($contents);
        $written_bytes  = 0;
        while ($written_bytes < $expected_bytes) {
            $written = fwrite($temporary_file, substr($contents, $written_bytes)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
            if (false === $written || 0 === $written) {
                fclose($temporary_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                $this->deleteFileWithinDirectory($temporary_path, $directory_path);
                return false;
            }
            $written_bytes += $written;
        }

        if (! fflush($temporary_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
            fclose($temporary_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            $this->deleteFileWithinDirectory($temporary_path, $directory_path);
            return false;
        }
        if (! fclose($temporary_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            $this->deleteFileWithinDirectory($temporary_path, $directory_path);
            return false;
        }

        // One same-filesystem rename publishes the complete file without exposing a partial write.
        if (! rename($temporary_path, $target_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
            $this->deleteFileWithinDirectory($temporary_path, $directory_path);
            return false;
        }

        return true;
    }

    public function deleteFileWithinDirectory(string $file_path, string $directory_path): bool
    {
        if (! $this->fileExists($file_path)) {
            return true;
        }

        // The WordPress helper validates that the resolved file path remains inside the allowed directory.
        wp_delete_file_from_directory($file_path, $directory_path);

        // WordPress 6.5 does not provide a Boolean deletion result, so verify the required postcondition.
        return ! $this->fileExists($file_path);
    }
}
