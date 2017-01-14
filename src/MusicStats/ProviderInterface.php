<?php

namespace MusicStats;

interface ProviderInterface
{
    /**
     * @param string $artist
     * @param string $song
     * @param string $album
     * @return Stat[]
     */
    public function getStats($artist, $song, $album = null);
}