<?php
    require '../vendor/autoload.php';
    require './YandexMusicParser/YandexMusicParser.php';
    use App\YandexMusicParser\YandexMusicParser;

    // mysqli 
    $mysqli = new mysqli("mysql", "test", "test", "test");
    if ($mysqli->connect_errno) {
        die("Failed to connect to MySQL: $mysqli->connect_error");
    }

    // parsing
    if (isset($_GET['id'])) {
        $parser = new YandexMusicParser($_GET['id'], $mysqli);
        $parser->startParse();
    }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yandex Parser</title>

    <style>
        body {
            margin: 0;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .header {
            font-size: 25px;
            font-weight: bold;
        }

        .container {
            display: flex;
            gap: 40px;
        }

        form {
            display: flex;
            flex-direction: column;
            width: 20%;
            gap: 10px;
        }

        form > input {
            border-radius: 12px;
            border: 1px solid gray;
            padding: 6px 10px;
            font-size: 16px;
        }

        form > button {
            border-radius: 12px;
            border: none;
            padding: 8px;
            background-color: #027bff;
            font-size: 18px;
            transition: background 0.3s;
            cursor: pointer;
            color: white;
        }

        form > button:hover {
            background-color: #0169d9;
        }

        .result {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="/example.php" method="GET">
            <div class="header">Парсер YandexMusic</div>

            <input name="id" type="text" placeholder="Введите id артиста">
            <button type="submit">Парсить</button>
        </form>

        <?php if (isset($_GET['id'])) : ?>
        <div>
            <div class="header">Результат:</div>

            <div class="result">
                <div>
                    Добавлено альбомов: <?= $parser->insertedAlbums ?>
                </div>
                <div>
                    Добавлено треков: <?= $parser->insertedTracks ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>