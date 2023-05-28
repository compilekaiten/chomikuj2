<?php

namespace Chomikuj;

use Chomikuj\Entity\File;
use Chomikuj\Entity\Folder;

interface ApiInterface {
    /**
     * Logs in using provided credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function login(string $username, string $password): ApiInterface;

    /**
     * Logs out.
     *
     * @return self
     */
    public function logout(): ApiInterface;

    /**
     * Returns an array of found Files.
     *
     * @param string $phrase             search phrase
     * @param array  $optionalParameters optional parameters directly passed to request
     * @param int    $page               search result page (starts from 1)
     *
     * @return File[]
     *
     * @throws ChomikujException if request failed
     */
    public function findFiles(string $phrase, array $optionalParameters = [], int $page = 1): array;

    /**
     * Returns first level subfolders of specified folder of specified user.
     *
     * @param string $username
     * @param int    $folderId use 0 for root folder
     *
     * @return Folder[]
     *
     * @throws ChomikujException if request failed
     */
    public function getFolders(string $username, int $folderId);

    /**
     * Creates folder of provided name.
     *
     * @param string      $folderName
     * @param int         $parentFolderId use 0 for root folder
     * @param bool        $adult          true for nsfw content
     * @param null|string $password       if set, folder will be password-protected
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function createFolder(string $folderName, int $parentFolderId, bool $adult, ?string $password): ApiInterface;

    /**
     * Removes folder of provided id.
     *
     * @param int $folderId
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function removeFolder(int $folderId): ApiInterface;

    /**
     * Gets a one-time-use upload URL for a folder.
     *
     * @param int $folderId
     *
     * @return string
     *
     * @throws ChomikujException if request failed
     */
    public function getUploadUrl(int $folderId): string;

    /**
     * Uploads a file.
     *
     * @param int    $folderId
     * @param string $filePath
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function uploadFile(int $folderId, string $filePath): ApiInterface;

    /**
     * Moves a file between folders.
     *
     * @param int $fileId
     * @param int $sourceFolderId
     * @param int $destinationFolderId
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function moveFile(int $fileId, int $sourceFolderId, int $destinationFolderId): ApiInterface;

    /**
     * Copies a file from source folder to destination folder.
     *
     * @param int $fileId
     * @param int $sourceFolderId
     * @param int $destinationFolderId
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function copyFile(int $fileId, int $sourceFolderId, int $destinationFolderId): ApiInterface;

    /**
     * Changes name and description of a file.
     *
     * @param int    $fileId
     * @param string $newFilename
     * @param string $newDescription
     *
     * @return self
     *
     * @throws ChomikujException if request failed
     */
    public function renameFile(int $fileId, string $newFilename, string $newDescription): ApiInterface;
}
