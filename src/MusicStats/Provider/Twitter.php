<?php

namespace MusicStats\Provider;

use GuzzleHttp;
use MusicStats\ProviderInterface;
use MusicStats\Stat;

class Twitter implements ProviderInterface
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

        $handle = $this->getArtistHandle($artist);
        if ($handle) {
            $countStats = $this->getTwitterCounterStats($handle);

            if (isset($countStats['Daily New Followers'])) {
                $stats[] = new Stat('@' . $handle . ' adds about ' . number_format($countStats['Daily New Followers']) . ' new Twitter followers a day');
            }
            if (isset($countStats['Followers'])) {
                $stats[] = new Stat('@' . $handle . ' has ' . number_format($countStats['Followers']) . ' Twitter followers');
            }
            if (isset($countStats['Tweets'])) {
                $stats[] = new Stat('@' . $handle . ' has posted ' . number_format($countStats['Tweets']) . ' tweets');
            }
            if (isset($countStats['Favourites'])) {
                $stats[] = new Stat('@' . $handle . ' has been favorited by ' . number_format($countStats['Favourites']) . ' Twitter users');
            }
            if (isset($countStats['Worldwide Rank'])) {
                $stats[] = new Stat('@' . $handle . ' is ranked ' . number_format($countStats['Worldwide Rank']) . ' on Twitter');
            }

            $recentTopTweets = $this->getRecentTopTweets($handle);
            foreach ($recentTopTweets as $tweet) {
                $stats[] = new Stat('@'. $handle . ' Tweeted: ' . $tweet['text'] . ' on ' . date_create($tweet['created_at'])->format('M jS'));
            }
        }

        return $stats;
    }

    /**
     * @param string $artist
     * @return string
     */
    public function getArtistHandle($artist)
    {
        $url = 'https://www.google.com/search?q=site%3Atwitter.com+' . urlencode($artist);

        $client = new GuzzleHttp\Client([
            'timeout' => 1,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        $href = $pq->find('#ires .r:first a')->attr('href');

        if (preg_match('#^/url\?q=https://twitter.com/([a-z0-9_-]+)#', $href, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * [
     *   'Daily New Followers' => 41,
     *   'Followers' => 146473,
     *   'Following' => 2012,
     *   'Worldwide Rank' => 10965,
     *   'Tweets' => 22963,
     *   'Favourites' => 3951,
     *   'Listed' => 2119,
     * ]
     *
     * @param string $handle
     * @return int[]
     */
    public function getTwitterCounterStats($handle)
    {
        $url = 'http://twittercounter.com/' . urlencode($handle);

        $client = new GuzzleHttp\Client([
            'timeout' => 1,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $html = $response->getBody();

        $pq = \phpQuery::newDocumentHTML($html);

        /** @var int[] $stats */
        $stats = [];

        $followers = $pq->find('.new-followers');
        if ($followers->length) {
            $stats['Daily New Followers'] = (int)str_replace(',', '', $followers->text());
        }

        $divs = $pq->find('.main-stats-inner > div');
        foreach (iterator_to_array($divs) as $div) {
            $stats[pq($div)->find('.metric')->text()] = (int)str_replace(',', '', pq($div)->find('.counter')->text());
        }

        return $stats;
    }

    /**
     * @TODO TOO SLOW 3.1s
     * [
     *   [
     *     'id' => 815339857019080704,
     *     'created_at' => 'Sat Dec 31 23:32:24 +0000 2016',
     *     'text' => 'WHATEVER WENT DOWN IN YOUR LIFE THIS YEAR THAT YOU HATED, WATCH IT GO UP IN FLAMES IN YOUR
     *     MIND\'S EYE',
     *     'retweet_count' => 2848,
     *     'favorite_count' => 5506,
     *     'score' => 8354,
     *   ],
     *   ...
     * ]
     * @param string $handle
     * @param int    $count
     * @return array[]
     */
    public function getRecentTopTweets($handle, $count = 4)
    {
        $url = 'https://socialbearing.com/scripts/get-tweets.php?sid=0&searchtype=user&search=' . urlencode($handle);

        $client = new GuzzleHttp\Client([
            'timeout' => 4,
            'allow_redirects' => false,
        ]);
        $response = $client->get($url);
        $json = $response->getBody();

        /** @var array[] $json */
        $tweets = json_decode($json, true);

        $tweets = \Functional\filter($tweets, function ($tweet) {
            return
                'RT' !== strtoupper(substr($tweet['text'], 0, 2)) &&
                '.@' !== substr($tweet['text'], 0, 2) &&
                null === $tweet['in_reply_to_status_id'] &&
                null === $tweet['in_reply_to_status_id_str'] &&
                null === $tweet['in_reply_to_user_id'] &&
                null === $tweet['in_reply_to_user_id_str'] &&
                null === $tweet['in_reply_to_screen_name'] &&
                null === $tweet['is_quote_status'];
        });

        $tweets = \Functional\map($tweets, function ($tweet) {
            return [
                'id' => $tweet['id'],
                'created_at' => $tweet['created_at'],
                'text' => $tweet['text'],
                'retweet_count' => $tweet['retweet_count'],
                'favorite_count' => $tweet['favorite_count'],
                'score' => (float)$tweet['retweet_count'] + $tweet['favorite_count'],
            ];
        });

        usort($tweets, function ($tweetA, $tweetB) {
            $a = $tweetA['score'];
            $b = $tweetB['score'];

            return $a > $b ? 1 : ($a < $b ? -1 : 0);
        });

        return array_slice(array_reverse($tweets), 0, $count);
    }
}