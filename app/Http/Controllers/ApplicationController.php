<?php

namespace App\Http\Controllers;

use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Photos\Types\VideoProcessingStatus;
use Google\Rpc\Code;
use Illuminate\Http\Request;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;
use Google\Auth\OAuth2;

session_start();

class ApplicationController extends Controller
{
    # Начальная страница.
    public function index()
    {
        $msg = 'Необходима авторизация.';
        $msgClass = 'alert-danger';
        print_r($_SESSION['credentials']->fetchAuthToken());
        echo '<br>';
        echo '<br>';
        echo '<br>';
        echo '<br>';
        print_r($_SESSION['credentials']->getLastReceivedToken());
        if (time() > $_SESSION['credentials']->getLastReceivedToken()['expires_at'])
        {
            $msg = 'Срок действия токена истёк. Необходима авторизация.';
            $msgClass = 'alert-danger';
        }
        else
        {
            $diff = $_SESSION['credentials']->getLastReceivedToken()['expires_at'] - time();
            $d = $diff / 86400 % 7;
            $h = $diff / 3600 % 24;
            $m = $diff / 60 % 60;
            $s = $diff % 60;

            $msg = "До окончания срока действия токена ещё {$d} дней {$h} часов {$m} минут {$s} секунд.";
            $msgClass = 'alert-warning';
        }

        return view('index', [
            'title' => 'Google Photos Api Video Upload',
            'msg' => $msg,
            'msgClass' => $msgClass,
        ]);
    }

    public function getAlbums()
    {
        $albums = array();

        # try-catch к new PhotosLibraryClient.
        try
        {
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $_SESSION['credentials']]);
            $response = $photosLibraryClient->listAlbums();
            foreach ($response->iterateAllElements() as $album)
            {
                $albumId = $album->getId();
                $title = $album->getTitle();
                $productUrl = $album->getProductUrl();
                $isWriteable = $album->getIsWriteable();
                if (empty($isWriteable)) {
                    $isWriteable = 0;
                }
                $totalMediaItems = $album->getMediaItemsCount();

                $albums[] = ['albumId' => $albumId, 'title' => $title, 'productUrl' => $productUrl, 'isWriteable' => $isWriteable];
            }
        }
        catch (\Google\ApiCore\ValidationException $e)
        {
            echo 'PhotosLibraryClient exception: <br>';
            echo $e . '<br>';
        }
        # Catch к методу listAlbums.
        catch (ApiException $e)
        {
            echo 'listAlbums exception: <br>';
            echo $e . '<br>';
        }

        return $albums;
    }

    public function getVideos()
    {
        $albums = $this->getAlbums();
        $videos = array();

        # try-catch к new PhotosLibraryClient.
        try
        {
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $_SESSION['credentials']]);

            foreach ($albums as $album)
            {
                $albumId = $album['albumId'];
                $albumTitle = $album['title'];
                $response = $photosLibraryClient->searchMediaItems(['albumId' => $albumId]);
                foreach ($response->iterateAllElements() as $elem)
                {
                    $videos[] = [
                        'id' => $elem->getId(),
                        'description' => $elem->getDescription(),
                        'productUrl' => $elem->getProductUrl(),
                        'mimeType' => $elem->getMimeType(),
                        'filename' => $elem->getFilename(),
                        'albumId' => $albumId,
                        'albumTitle' => $albumTitle,
                    ];
                }
            }
        }
        catch (\Google\ApiCore\ValidationException $e)
        {
            echo 'PhotosLibraryClient exception: <br>';
            echo $e . '<br>';
        }
        # Catch к методу searchMediaItems.
        catch (\Google\ApiCore\ApiException $e)
        {
            echo 'searchMediaItems exception: <br>';
            echo $e . '<br>';
        }
        finally
        {
            $photosLibraryClient->close();
        }

        return $videos;
    }

    public function scanDirectory($dir, $videos)
    {
        echo '<br>Сканирую <b><i>' . $dir . '</b></i><br>';

        # Получаем содержимое директории.
        $contents = scandir($dir);
        foreach ($contents as $elem)
        {
            if ($elem == '.' or $elem == '..' or $elem == 'desktop.ini' or $elem == 'Thumbs.db')
            {
                continue;
            }

            # Если не директория, загружаем видео.
            if (!is_dir($dir . '/' . $elem))
            {
                if ($dir == BASE_DIR)
                {
                    continue;
                }

                echo '<br><b>' . $dir . '/' . $elem . "</b> не директория.<br>";

                $videoAlreadyExists = false;
                foreach($videos as $video)
                {
                    $albumName = str_replace(BASE_DIR, "", $dir);
                    $albumName = ltrim($albumName, "/");

                    if ($elem == $video['filename'] and $albumName == $video['albumTitle'])
                    {
                        $videoAlreadyExists = true;
                        echo "Видео <b>" . $dir . '/' . $elem . "</b> уже есть. <br>";
                        break;
                    }
                }

                if (!$videoAlreadyExists)
                {
                    $albums = $this->getAlbums();

                    $albumExists = false;

                    foreach($albums as $album)
                    {
                        $albumName = str_replace(BASE_DIR, "", $dir);
                        $albumName = ltrim($albumName, "/");

                        if ($album['title'] == $albumName)
                        {
                            $albumId = $album['albumId'];
                            $albumExists = true;
                            break;
                        }
                    }

                    if ($albumExists)
                    {
                        echo "Альбом <b>$albumName</b> уже существует. <br>";
                        $uploadResult = $this->uploadVideoToGooglePhotos($dir . '/' . $elem, $elem, $albumId);
                    }
                    else
                    {
                        $albumName = str_replace(BASE_DIR, "", $dir);
                        $albumName = ltrim($albumName, "/");

                        echo 'Альбома ' . $albumName . ' ещё нет. <br>';
                        $albumId = $this->addAlbum($albumName);

                        $uploadResult = $this->uploadVideoToGooglePhotos($dir . '/' . $elem, $elem, $albumId);
                    }
                }
            }
            # Если директория, вызываем функцию повторно.
            else
            {
                echo '<br><b>' . $dir . '/' . $elem . '</b> является директорией. <br>';
                $videos = $this->getVideos();
                $this->scanDirectory($dir . '/' . $elem, $videos);
            }

        }
    }

    public function addAlbum($albumName)
    {
        try
        {
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $_SESSION['credentials']]);
            $newAlbum = PhotosLibraryResourceFactory::album($albumName);
            $createdAlbum = $photosLibraryClient->createAlbum($newAlbum);
            $albumId = $createdAlbum->getId();
            $isWriteable = $createdAlbum->getIsWriteable();
            return $albumId;
        }
        catch (ValidationException $e)
        {
            echo 'PhotosLibraryClient exception: <br>';
            echo $e . '<br>';
        }
    }

    public function uploadVideoToGooglePhotos($videoPath, $videoName, $albumId)
    {
        # Загрузка видео.
        # try-catch к new PhotosLibraryClient.
        try
        {
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $_SESSION['credentials']]);

            $mimeType = mime_content_type($videoPath);
            echo "$mimeType<br>";

            # Create a new upload request by opening the file and specifying the media type (e.g. "image/png")
            $uploadToken = $photosLibraryClient->upload(file_get_contents($videoPath));
        }
        catch (\Google\ApiCore\ValidationException $e)
        {
            echo 'PhotosLibraryClient exception: <br>';
            echo $e . '<br>';
        }
        # catch к методу upload.
        catch (\GuzzleHttp\Exception\GuzzleException $e)
        {
            echo 'upload exception: <br>';
            echo $e . '<br>';
        }

        if (!empty($uploadToken))
        {
            echo "Видео <b>$videoName</b> загружено. Создание медиа-элемента... <br>";
        }
        else
        {
            exit('Строка ' . __LINE__ . ': Ошибка при загрузке видео.');
        }

        # Создание медиа-элемента.
        try
        {
            $newMediaItems = [];
            // Create a NewMediaItem with the following components:
            // - uploadToken obtained from the previous upload request
            // - filename that will be shown to the user in Google Photos
            // - description that will be shown to the user in Google Photos
            $newMediaItems[0] = PhotosLibraryResourceFactory::newMediaItemWithFileName(
                $uploadToken, $videoName);

            $response = $photosLibraryClient->batchCreateMediaItems($newMediaItems, ['albumId' => $albumId]);
            foreach ($response->getNewMediaItemResults() as $itemResult)
            {
                # Each result item is identified by its uploadToken.
                $itemUploadToken = $itemResult->getUploadToken();
                # Verify the status of each entry to ensure that the item has been uploaded correctly.
                $itemStatus = $itemResult->getStatus();
                if ($itemStatus->getCode() != Code::OK)
                {
                    # Error while creating the item.
                    echo "Ошибка при создании медиа-элемента.<br>";
                }
                else
                {
                    echo "Media item is successfully created.<br>";
                    # Media item is successfully created.
                    # Get the MediaItem object from the response.
                    $mediaItem = $itemResult->getMediaItem();
                    # It contains details such as the Id of the item, productUrl.
                    $id = $mediaItem->getId();
                    $productUrl = $mediaItem->getProductUrl();
                    # filename пустая скорее всего из-за того, что при загрузке мы не указывали имя файла,
                    # поэтому getFilename ничего не возвращает.
                    $filename = $mediaItem->getFilename();
//                    echo '<br>';echo '<br>';echo "productUrl ";print_r($productUrl);echo '<br>';echo '<br>';echo '<br>';

                    $metadata = $mediaItem->getMediaMetadata();
                    if (!is_null($metadata))
                    {
                        echo "Metadata is not null.<br>";
                        # Несмотря на то, что videoMetadata is null, видео успешно загружается.
                        # Однако мы не можем проверить статус обработки видео.
                        $videoMetadata = $metadata->getVideo();
                        if (!is_null($videoMetadata))
                        {
                            # This media item is a video and has additional video metadata.
                            if (VideoProcessingStatus::UNSPECIFIED == $videoMetadata->getStatus())
                            {
                                echo "Video processing status is unknown.<br>";
                            }
                            else if (VideoProcessingStatus::PROCESSING == $videoMetadata->getStatus())
                            {
                                echo "Video is being processed.<br>";
                            }
                            else if (VideoProcessingStatus::READY == $videoMetadata->getStatus())
                            {
                                echo "Video has been processed.<br>";
                            }
                            else if (VideoProcessingStatus::FAILED == $videoMetadata->getStatus())
                            {
                                echo "Something has gone wrong and the video has failed to process.<br>";
                            }
                        }
                        else
                        {
                            echo "videoMetadata is null.<br>";
                        }
                    }
                    else
                    {
                        echo "Metadata is null.<br>";
                    }


//                    return $filename;
                }
            }
        }
        catch (\Google\ApiCore\ApiException $e)
        {
            # Handle error.
            echo 'batchCreateMediaItems exception: <br>';
            echo $e . '<br>';
        }
    }

    # Логика приложения.
    public function runApplication()
    {
        //define('BASE_DIR', 'C:\Users\AMD\Desktop\Test Folder');
        define('BASE_DIR', 'X:\Folder');

        $rootDir = BASE_DIR;
        $videos = $this->getVideos();
        $this->scanDirectory($rootDir, $videos);
    }
}
