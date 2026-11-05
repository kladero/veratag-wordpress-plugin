<?php

namespace VeraTag\Filesystem;

interface FileOperations
{
    public function fileExists(string $path): bool;
    public function directoryExists(string $path): bool;
    public function isRegularFile(string $path): bool;
    public function createDirectory(string $path): bool;
    /** @return list<string>|null Directory entry names, excluding `.` and `..`, or null when listing fails. */
    public function listDirectory(string $path): ?array;
    public function getModifiedTime(string $path): ?int;
    public function writeAtomically(string $target_path, string $contents): bool;
    public function deleteFileWithinDirectory(string $file_path, string $directory_path): bool;
}
