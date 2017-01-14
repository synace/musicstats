<?php

namespace MusicStats;

class MusicStats implements ProviderInterface
{
    /**
     * @var ProviderInterface[]
     */
    protected $providers;

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param ProviderInterface[] $providers
     */
    public function setProviders($providers)
    {
        $this->providers = $providers;
    }

    /**
     * @param ProviderInterface $provider
     */
    public function addProvider(ProviderInterface $provider)
    {
        $this->providers[] = $provider;
    }

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

        if (!strlen($artist) || !strlen($song)) {
            return $stats;
        }

        foreach ($this->getProviders() as $provider) {
            $stats = array_merge($stats, $provider->getStats($artist, $song, $album));
        }

        return $stats;
    }
}