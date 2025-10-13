<?php

namespace App\Service;

use Predis\Client as PredisClient;

class VisitCounter
{
    private ?PredisClient $redis = null;
    private string $fallbackPath;

    public function __construct(?string $redisUrl = null)
    {
        $this->fallbackPath = dirname(__DIR__, 2) . '/var/counters/homepage.txt';
        if ($redisUrl) {
            try {
                $this->redis = new PredisClient($redisUrl);
                $this->redis->connect();
            } catch (\Throwable) {
                $this->redis = null;
            }
        }
    }

    public function incrementHome(): int
    {
        if ($this->redis) {
            return (int) $this->redis->incr('visits:homepage');
        }
        return $this->incrementFallback();
    }

    public function getHome(): int
    {
        if ($this->redis) {
            return (int) ($this->redis->get('visits:homepage') ?? 0);
        }
        return $this->readFallback();
    }

    private function incrementFallback(): int
    {
        $dir = dirname($this->fallbackPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $count = $this->readFallback() + 1;
        file_put_contents($this->fallbackPath, (string)$count);
        return $count;
    }

    private function readFallback(): int
    {
        if (!is_file($this->fallbackPath)) {
            return 0;
        }
        $raw = trim((string)@file_get_contents($this->fallbackPath));
        return ctype_digit($raw) ? (int)$raw : 0;
    }
}

