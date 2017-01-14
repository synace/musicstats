<?php

namespace MusicStats\Provider;

use GuzzleHttp;
use MusicStats\ProviderInterface;
use MusicStats\Stat;

class SongKick implements ProviderInterface
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
            $concertStats = $this->getConcertStats($id);

            if (isset($concertStats['Upcoming Concert'])) {
                $stats[] = new Stat('Next Concert: ' . $concertStats['Upcoming Concert']['text']);
            }
            if (isset($concertStats['Previous Concert'])) {
                $stats[] = new Stat('Most Recent Concert: ' . $concertStats['Previous Concert']['text']);
            }
            if (isset($concertStats['Distance Travelled'])) {
                $stats[] = new Stat('Distance Travelled On Tour: ' . $concertStats['Distance Travelled']);
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
        $url = 'http://www.songkick.com/search?utf8=%E2%9C%93&type=artists&query=' . urlencode($artist);

        $client = new GuzzleHttp\Client([
            'timeout' => 2,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        $href = $pq->find('.search .artist:first a')->attr('href');

        if (preg_match('#^/artists/([a-z0-9_-]+)#', $href, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * [
     *   'Upcoming Concert' => [
     *      'date' => '2017-04-06T00:00:00-04:00',
     *      'venue' => 'Badlands Bar',
     *      'city' => 'Perth, WA, Australia',
     *      'text' => 'Thursday, Apr 6, \'17 at Badlands Bar in Perth, WA, Australia',
     *   ],
     *   'Previous Concert' => [
     *     'date' => '2016-12-05T00:00:00-05:00',
     *     'venue' => 'Cat\'s Cradle Back Room',
     *     'city' => 'Carrboro, NC, US',
     *     'text' => 'Monday, Dec 5, \'16 at Cat\'s Cradle Back Room in Carrboro, NC, US',
     *   ],
     *   'Distance Travelled' => '600,942 miles',
     * ]
     *
     * @param string $artistId
     * @return string
     */
    public function getConcertStats($artistId)
    {
        $url = 'http://www.songkick.com/artists/' . $artistId;

        $client = new GuzzleHttp\Client([
            'timeout' => 2,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        /**
         * @param \phpQueryObject $event
         * @return string[]
         */
        $parseEvent = function (\phpQueryObject $event) {
            $date = trim($event->attr('title'));
            $venue = trim($event->find('.venue-name a')->text());
            $city = trim($event->find('.location span:not(.venue-name) span:first')->text());

            if (strlen($date) && strlen($venue) && strlen($city)) {
                $date = date_create($date);

                return [
                    'date' => $date->format('c'),
                    'venue' => $venue,
                    'city' => $city,
                    'text' => $date->format('l, M j, \'y') . ' at ' . $venue . ' in ' . $city,
                ];
            }

            return [];
        };

        /** @var mixed[] $stats */
        $stats = [];

        $event = $pq->find('.events-summary .event-listings:first')->find('li[title]:first');
        $upcoming = $parseEvent($event);
        if (!empty($upcoming)) {
            $stats['Upcoming Concert'] = $upcoming;
        }

        $event = $pq->find('.events-summary .event-listings:last')->find('li[title]:first');
        $previous = $parseEvent($event);
        if (!empty($previous)) {
            $stats['Previous Concert'] = $previous;
        }

        $distanceStat = $pq->find('.artist-touring-stats .stat .distance-travelled');
        if ($distanceStat->length) {
            $stats['Distance Travelled'] = $pq->find('.artist-touring-stats .stat .distance-travelled')->text();
        }

        return $stats;
    }
}