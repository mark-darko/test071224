<?php

/**
 * YandexMusicParser
 * Created by Mark Eskilev
 * Date: 2024-12-07 17:26
 */

namespace App\YandexMusicParser;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;

class YandexMusicParser
{
    private $mysqli;
    private $artistId;

    public $insertedTracks = 0;
    public $insertedAlbums = 0;

    /**
     * Инциализация класса
     * @param string $url
     * @param \mysqli $mysqli
     */
    public function __construct($id, $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->artistId = $id;
    }

    /**
     * Получение xpath страницы
     * @param int $id
     * @param string $sublink
     * @param string $type
     * @return \DOMXPath
     */
    private function getXpath($id, $type = 'artist', $sublink = null)
    {
        $host = 'http://selenium-hub:4444/wd/hub'; // URL Selenium Server

        //$proxy = $this->getProxy();
        $capabilities = new DesiredCapabilities([
            WebDriverCapabilityType::BROWSER_NAME => 'chrome',
            // можно использовать для прокси
            // WebDriverCapabilityType::PROXY => [
            //     'proxyType' => 'manual',
            //     'httpProxy' => $proxy,
            //     'sslProxy' => $proxy,
            // ],
        ]);

        // Создаем экземпляр WebDriver
        $driver = RemoteWebDriver::create($host, $capabilities);

        // Открываем страницу
        $driver->get("https://music.yandex.ru/$type/$id/$sublink");

        // Скроллим страницу до конца
        $lastHeight = $driver->executeScript("return document.body.scrollHeight");
        while (true) {
            $driver->executeScript("window.scrollTo(0, document.body.scrollHeight);");
            sleep(1); // Ждем, пока контент загрузится
            $newHeight = $driver->executeScript("return document.body.scrollHeight");
            if ($newHeight == $lastHeight) {
                break;
            }
            $lastHeight = $newHeight;
        }

        // Получаем HTML-код страницы
        $response = $driver->getPageSource();
        if ($response === false)
            die("Ошибка при получении HTML-кода страницы.");

        // Закрываем браузер
        $driver->quit();

        // Загрузка HTML в DOMDocument
        $dom = new \DOMDocument();
        @$dom->loadHTML($response);
        $xpath = new \DOMXPath($dom);

        return $xpath;
    }
    
    /**
     * Получение информации об артисте
     * @return void
     */
    public function getArtistInfo()
    {
        $xpath = $this->getXpath($this->artistId);

        $artistId = $this->artistId;
        $artistAvatar = 'https://' . ltrim($xpath->query('.//img[contains(@class, "artist-pics__pic")]')->item(0)->getAttribute('src'), '/');
        $artistName = $xpath->query('.//h1[contains(@class, "page-artist__title")]')->item(0)->nodeValue;
        $subscribers = $xpath->query('.//span[contains(@class, "d-like d-like_theme-count")]')->item(0)->nodeValue;
        $listeners = $xpath->query('.//div[contains(@class, "page-artist__summary")]//span')->item(0)->nodeValue;
        
        $this->insertDB('artists', [
            'id' => $artistId,
            'name' => $artistName,
            'subscribers' => $subscribers,
            'listeners' => $listeners,
            'avatar_url' => $artistAvatar,
        ]);
    }

    /**
     * Получение альбомов артиста
     * @param int $id
     * @return void
     */
    public function getAlbum($id)
    {
        $xpath = $this->getXpath($id, 'album');

        $albumName = $xpath->query('.//div[contains(@class, "page-album__title typo-h1_small")]//span[contains(@class, "deco-typo")]')->item(0)->nodeValue;
        $albumAvatar = 'https://' . ltrim($xpath->query('.//div[contains(@class, "d-generic-page-head__aside")]//img[contains(@class, "entity-cover__image deco-pane")]')->item(0)->getAttribute('src'), '/');
        $albumYear = $xpath->query('.//span[contains(@class, "typo deco-typo-secondary")]')->item(0)->nodeValue;

        $status = $this->insertDB('albums', [
            'id' => $id,
            'name' => $albumName,
            'avatar_url' => $albumAvatar,
            'year' => $albumYear,
        ]);

        if ($status['status'] == 'success')
            $this->insertedAlbums++;
    }

    /**
     * Получение треков артиста
     * @return void
     */
    public function getTracks()
    {
        $xpath = $this->getXpath($this->artistId, sublink: 'tracks');

        $tracks = $xpath->query('.//div[contains(@class, "lightlist__cont")][1]//div[contains(@class, "d-track typo-track")]');
        foreach ($tracks as $track) {
            $trackId = explode('/', $xpath->query('.//div[contains(@class, "d-track__name")]//a', $track)->item(0)->getAttribute('href'))[4];
            $trackName = $xpath->query('.//div[contains(@class, "d-track__name")]', $track)->item(0)->nodeValue;
            $trackDuration = $xpath->query('.//div[contains(@class, "d-track__info d-track__nohover")]', $track)->item(0)->nodeValue;
            $trackAlbumId = explode('/', $xpath->query('.//div[contains(@class, "d-track__meta")]//a', $track)->item(0)->getAttribute('href'))[2];

            //включить если есть прокси
            //$this->getAlbum($trackAlbumId);

            $status = $this->insertDB('tracks', [
                'id' => $trackId,
                'album_id' => $trackAlbumId,
                'name' => $trackName,
                'duration' => $trackDuration,
                'artist_id' => $this->artistId,
            ]);

            if ($status['status'] == 'success')
                $this->insertedTracks++;
        };
    }

    /**
     * Функция подготовки запроса
     * @param string $query
     * @param array $array
     * @return bool|\mysqli_result
     */
    public function queryDB($query, $array)
    {
        $stmt = $this->mysqli->prepare($query);
        $stmt->execute($array);
        return $stmt->get_result();
    }

    /**
     * Функция InsertOrFail
     * @param string $table
     * @param array $array
     * @return array
     */
    public function insertDB($table, $array)
    {
        $columns = implode(', ', array_keys($array));
        $placeholders = str_repeat('?,', count($array) - 1) . '?';
        
        if ($this->queryDB("SELECT count(*) as count FROM $table WHERE id = ?", [$array['id']])->fetch_assoc()['count'] == 0) {
            $this->queryDB("INSERT INTO $table ($columns) VALUES ($placeholders)", array_values($array));
            return ['status' => 'success'];
        } else {
            return ['status' => 'error'];
        }
    }

    /**
     * Старт парсинга
     * @return void
     */
    public function startParse()
    {
        $this->getArtistInfo();
        $this->getTracks();
    }

    // /**
    //  * Получение прокси (можно использовать для ротации ip, если есть список прокси)
    //  * @return string
    //  */
    // private function getProxy()
    // {
    //     $proxys = [
    //         '203.175.102.44:8082',
    //     ];

    //     return $proxys[rand(0, count($proxys) - 1)];
    // }
}