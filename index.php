<?php

include __DIR__ . '/vendor/autoload.php';

$artist = $_GET['artist'];
$song = $_GET['song'];
$album = $_GET['album'];

$musicStats = new MusicStats\MusicStats();

$musicStats->addProvider(new MusicStats\Provider\Twitter());
$musicStats->addProvider(new MusicStats\Provider\SongKick());
$musicStats->addProvider(new MusicStats\Provider\LastFm());

$stats = $musicStats->getStats($artist, $song, $album);

header('Content-type: text/json');

echo json_encode($stats);
