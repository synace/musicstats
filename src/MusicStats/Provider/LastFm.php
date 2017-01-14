<?php

namespace MusicStats\Provider;

use GuzzleHttp;
use MusicStats\ProviderInterface;
use MusicStats\Stat;

class LastFm implements ProviderInterface
{
    /**
     * @param string $artist
     * @param string $song
     * @param string $album
     * @return Stat[]
     */
    public function getStats($artist, $song, $album = null)
    {
        /** @var Stat[] $stats */
        $stats = [];

        $id = $this->getArtistId($artist);
        if ($id) {
            $artistTopStats = $this->getArtistTopStats($id);

            if (isset($artistTopStats['Top Track'])) {
                $stats[] = new Stat('Most Popular Song: ' . $artistTopStats['Top Track']);
            }
            if (isset($artistTopStats['Top Album'])) {
                $stats[] = new Stat('Most Popular Album: ' . $artistTopStats['Top Album']);
            }
        }

        return $stats;
    }

    /**
     * @param string $artist
     * @return string
     */
    public function getArtistId($artist)
    {
        $url = 'http://www.last.fm/search?q=' . urlencode($artist);

        $client = new GuzzleHttp\Client([
            'timeout' => 2,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        $href = $pq->find('a.link-block-target:first')->attr('href');

        if (preg_match('#^/music/([^/]+)#', $href, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param string $artistId
     * @return string
     */
    public function getArtistTopStats($artistId)
    {
        $url = 'http://www.last.fm/music/' . $artistId;

        $client = new GuzzleHttp\Client([
            'timeout' => 2,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        /** @var mixed[] $stats */
        $stats = [];

        $topTrack = $pq->find('.chartlist-name:first a:first')->text();
        if (!empty($topTrack)) {
            $stats['Top Track'] = $topTrack;
        }

        $topAlbum = $pq->find('.grid-items-item-main-text .link-block-target')->eq(0)->text();
        if (!empty($topTrack)) {
            $stats['Top Album'] = $topAlbum;
        }

        return $stats;
    }
}