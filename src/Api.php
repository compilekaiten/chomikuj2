<?php

namespace Chomikuj;

use Chomikuj\Exception\ChomikujException;
use Chomikuj\Mapper\FileMapper;
use Chomikuj\Mapper\FileMapperInterface;
use Chomikuj\Mapper\FolderMapper;
use Chomikuj\Mapper\FolderMapperInterface;
use Chomikuj\Service\FolderTicksService;
use Chomikuj\Service\FolderTicksServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

use function Safe\filesize;
use function Safe\fopen;
use function Safe\json_decode;
use function Safe\preg_match;

class Api implements ApiInterface {
    final public const BASE_URL = 'https://chomikuj.pl';
    final public const URIS = [
        'login' => '/action/Login/TopBarLogin',
        'logout' => '/action/Login/LogOut',
        'create_folder' => '/action/FolderOptions/NewFolderAction',
        'remove_folder' => '/action/FolderOptions/DeleteFolderAction',
        'upload_file' => '/action/Upload/GetUrl',
        'move_file' => '/action/FileDetails/MoveFileAction',
        'copy_file' => '/action/FileDetails/CopyFileAction',
        'rename_file' => '/action/FileDetails/EditNameAndDescAction',
        'get_folder_children' => '/action/tree/GetFolderChildrenHtml',
        'search' => '/action/SearchFiles/Results',
    ];
    final public const ERR_REQUEST_FAILED = 'Request failed.';
    final public const ERR_WEIRD_RESPONSE = 'Response looks valid, but could not be read (reason unknown).';
    final public const ERR_TOKEN_NOT_FOUND = 'Token could not be found.';
    final public const ERR_WRONG_FILE_PATH = 'Wrong file path / no access to file.';
    final public const ERR_FILE_IS_EMPTY = 'File is empty.';
    final public const ERR_UPLOAD_URL_FAIL = 'Could not get upload URL.';

    private readonly ClientInterface $client;
    private ?string $username = NULL;
    private readonly FolderMapperInterface $folderMapper;
    private readonly FileMapperInterface $fileMapper;
    private readonly FolderTicksServiceInterface $folderTicksService;

    public function __construct(?ClientInterface $client = NULL, FolderMapperInterface $folderMapper = NULL, FileMapperInterface $fileMapper = NULL, FolderTicksServiceInterface $folderTicksService = NULL) {
        if (NULL === $client) {
            $client = new Client([
                'cookies' => new CookieJar(),
                'allow_redirects' => FALSE,
                'http_errors' => FALSE,
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ]);
        }

        if (NULL === $folderMapper) {
            $folderMapper = new FolderMapper();
        }

        if (NULL === $fileMapper) {
            $fileMapper = new FileMapper();
        }

        if (NULL === $folderTicksService) {
            $folderTicksService = new FolderTicksService();
        }

        $this->folderMapper = $folderMapper;
        $this->fileMapper = $fileMapper;
        $this->folderTicksService = $folderTicksService;
        $this->client = $client;
    }

    public function login(string $username, string $password): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('login'), [
            'form_params' => [
                'Login' => $username,
                'Password' => $password,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_issuccess_one')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        $this->setUsername($username);

        return $this;
    }

    public function logout(): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('logout'));

        if (!$this->wasRequestSuccessful($response, 'status_200')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        $this->setUsername(NULL);

        return $this;
    }

    public function createFolder(string $folderName, int $parentFolderId = 0, bool $adult = FALSE, ?string $password = NULL): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('create_folder'), [
            'form_params' => [
                '__RequestVerificationToken' => $this->getToken(),
                'ChomikName' => $this->getUsername(),
                'FolderName' => $folderName,
                'FolderId' => $parentFolderId,
                'AdultContent' => $adult ? 'true' : 'false', // it has to be like this
                'Password' => $password,
                'NewFolderSetPassword' => NULL !== $password ? 'true' : 'false',
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_data_status_zero')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function removeFolder(int $folderId): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('remove_folder'), [
            'form_params' => [
                '__RequestVerificationToken' => $this->getToken(),
                'ChomikName' => $this->getUsername(),
                'FolderId' => $folderId,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_data_status_zero')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function getUploadUrl(int $folderId): string {
        $response = $this->client->request('POST', $this->getUrl('upload_file'), [
            'form_params' => [
                'accountname' => $this->getUsername(),
                'folderid' => $folderId,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_url')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        $json = json_decode($response->getBody()->getContents(), TRUE, 512, JSON_THROW_ON_ERROR);

        if (\array_key_exists('Url', $json)) {
            return $json['Url'];
        } else {
            throw new ChomikujException(self::ERR_UPLOAD_URL_FAIL);
        }
    }

    public function uploadFile(int $folderId, string $filePath): ApiInterface {
        if (!is_readable($filePath)) {
            throw new ChomikujException(self::ERR_WRONG_FILE_PATH);
        }

        if (0 === filesize($filePath)) {
            throw new ChomikujException(self::ERR_FILE_IS_EMPTY);
        }

        $response = $this->client->request('POST', $this->getUrl('upload_file'), [
            'form_params' => [
                'accountname' => $this->getUsername(),
                'folderid' => $folderId,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_url')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        $json = json_decode($response->getBody()->getContents());

        $response = $this->client->request('POST', $json->Url, [
            'multipart' => [
                [
                    'name' => 'files',
                    'contents' => fopen($filePath, 'r'),
                ],
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'status_200')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function getFolders(string $username, int $folderId = 0) {
        // Try for the first time
        $response = $this->makeGetFoldersRequest($username, $folderId, $this->folderTicksService->getTicks($username));

        // Try once again, because ticks might have expired
        if (!$this->wasRequestSuccessful($response, 'status_200')) {
            $response = $this->makeGetFoldersRequest($username, $folderId, $this->folderTicksService->getTicks($username, TRUE));
        }

        if (!$this->wasRequestSuccessful($response, 'status_200')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this->folderMapper->mapHtmlResponseToFolders($response);
    }

    public function moveFile(int $fileId, int $sourceFolderId, int $destinationFolderId): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('move_file'), [
            'form_params' => [
                'ChomikName' => $this->getUsername(),
                'FileId' => $fileId,
                'FolderId' => $sourceFolderId, // this has to be set
                'FolderTo' => $destinationFolderId,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_data_status_ok')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function copyFile(int $fileId, int $sourceFolderId, int $destinationFolderId): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('copy_file'), [
            'form_params' => [
                'ChomikName' => $this->getUsername(),
                'FileId' => $fileId,
                'FolderId' => $sourceFolderId, // this has to be set
                'FolderTo' => $destinationFolderId,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_data_status_ok')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function renameFile(int $fileId, string $newFilename, string $newDescription): ApiInterface {
        $response = $this->client->request('POST', $this->getUrl('rename_file'), [
            'form_params' => [
                'FileId' => $fileId,
                'Name' => $newFilename,
                'Description' => $newDescription,
            ],
        ]);

        if (!$this->wasRequestSuccessful($response, 'json_data_status_ok')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this;
    }

    public function findFiles(string $phrase, array $optionalParameters = [], int $page = 1): array {
        $basicParameters = [
            'FileName' => $phrase,
            'IsGallery' => 0,
            'Page' => $page,
        ];

        $response = $this->client->request('POST', $this->getUrl('search'), [
            'form_params' => $basicParameters + $optionalParameters,
        ]);

        if (!$this->wasRequestSuccessful($response, 'status_200')) {
            throw new ChomikujException(self::ERR_REQUEST_FAILED);
        }

        return $this->fileMapper->mapSearchResponseToFiles($response);
    }

    private function makeGetFoldersRequest(string $username, int $folderId, string $ticks): ResponseInterface {
        return $this->client->request('POST', $this->getUrl('get_folder_children'), [
            'form_params' => [
                'chomikName' => $username,
                'folderId' => $folderId,
                'ticks' => $ticks,
            ],
        ]);
    }

    private function getUsername(): ?string {
        return $this->username;
    }

    private function setUsername(?string $username): void {
        $this->username = $username;
    }

    /**
     * Validates response.
     *
     * Chomikuj.pl is extremely inconsistent when it comes to responses. Sometimes they return JSON, sometimes plain HTML, sometimes no body at all. Even for JSON responses there are at least several ways to mark it as successful.
     *
     * @param ResponseInterface $response
     * @param string            $type
     */
    private function wasRequestSuccessful(ResponseInterface $response, string $type) {
        $json = json_decode($response->getBody()->getContents());
        $response->getBody()->rewind();

        switch ($type) {
            case 'json_data_status_ok':
                return isset($json->Data->Status) && 'OK' === $json->Data->Status;

            case 'json_data_status_zero':
                return isset($json->Data->Status) && 0 === $json->Data->Status;

            case 'json_url':
                return isset($json->Url);

            case 'json_issuccess_one':
                return isset($json->IsSuccess) && TRUE === $json->IsSuccess;

            case 'status_200':
                return 200 === $response->getStatusCode();

            case 'status_400':
                return 400 === $response->getStatusCode();
        }
    }

    /**
     * Gets URL that can be used to make a HTTP request.
     */
    private function getUrl(?string $identifier): string {
        return match ($identifier) {
            '' => self::BASE_URL,
            'user_profile' => self::BASE_URL . '/' . $this->getUsername(),
            default => self::BASE_URL . self::URIS[$identifier],
        };
    }

    private function getToken(): string {
        $response = $this->client->request('GET', $this->getUrl('user_profile'), [
            'headers' => [
                'X-Requested-With' => NULL,
            ],
        ]);

        preg_match('/__RequestVerificationToken(?:.*?)value=\"(.*?)\"/', $response->getBody()->getContents(), $matches);

        if (empty($matches[1])) {
            throw new ChomikujException(self::ERR_TOKEN_NOT_FOUND);
        }

        return $matches[1];
    }
}
