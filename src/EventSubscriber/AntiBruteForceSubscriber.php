<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anti brute-force: délai fixe + rate limit (5 req/min)
 * - Clé primaire: IP + route (connexion/inscription) via Redis si disponible
 * - Fallback par session si Redis indisponible (et si la session est initialisée)
 */
class AntiBruteForceSubscriber implements EventSubscriberInterface
{
    private ?\Predis\Client $redis = null;

    public static function getSubscribedEvents(): array
    {
        return [ KernelEvents::REQUEST => ['onKernelRequest', 1024] ];
    }

    private function getRedis(): ?\Predis\Client
    {
        if ($this->redis !== null) return $this->redis;
        $url = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: null;
        if (!$url) { $this->redis = null; return null; }
        try { $this->redis = new \Predis\Client($url); }
        catch (\Throwable) { $this->redis = null; }
        return $this->redis;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $req = $event->getRequest();
        if (strtoupper($req->getMethod()) !== 'POST') { return; }
        $route = (string)$req->attributes->get('_route');
        $path = $req->getPathInfo();
        $isLogin = $route === 'app_login' || $path === '/connexion';
        $isRegister = $route === 'app_register' || $path === '/inscription';
        $isContact = $route === 'contact' || $path === '/contact';
        $isFamilyPost = $route === 'family_home' || $path === '/famille';
        if (!($isLogin || $isRegister || $isContact || $isFamilyPost)) { return; }

        // Délai anti brute-force (2s)
        usleep(2_000_000);

        $which = $isLogin ? 'login' : ($isRegister ? 'register' : ($isContact ? 'contact' : 'family'));
        $ip = (string)($req->getClientIp() ?? 'unknown');

        // Rate limit 5 req/min par IP via Redis (si dispo)
        if ($redis = $this->getRedis()) {
            try {
                $rkey = sprintf('rl:%s:%s', $which, $ip);
                $count = (int)$redis->incr($rkey);
                if ($count === 1) { $redis->expire($rkey, 60); }
                if ($count > 5) {
                    $event->setResponse(new \Symfony\Component\HttpFoundation\Response(
                        '<!DOCTYPE html><meta charset="utf-8"><title>Trop de tentatives</title><body style="font-family:sans-serif; padding:2rem; text-align:center;">Trop de tentatives. Réessayez dans une minute.</body>',
                        429,
                        ['Retry-After' => '60']
                    ));
                    return;
                }
            } catch (\Throwable) { /* ignore and fallback */ }
        }

        // Fallback: 5 req/min par session si Redis indisponible et session présente
        if (method_exists($req, 'hasSession') && $req->hasSession()) {
            try { $sess = $req->getSession(); } catch (\Throwable) { $sess = null; }
            if ($sess) {
                $key = 'rl:' . $which;
                $now = time();
                $window = (int)($sess->get($key.'.t') ?? 0);
                $count = (int)($sess->get($key.'.c') ?? 0);
                if ($now - $window >= 60) { $window = $now; $count = 0; }
                $count++;
                $sess->set($key.'.t', $window);
                $sess->set($key.'.c', $count);
                if ($count > 5) {
                    $event->setResponse(new \Symfony\Component\HttpFoundation\Response(
                        '<!DOCTYPE html><meta charset="utf-8"><title>Trop de tentatives</title><body style="font-family:sans-serif; padding:2rem; text-align:center;">Trop de tentatives. Réessayez dans une minute.</body>',
                        429,
                        ['Retry-After' => '60']
                    ));
                    return;
                }
            }
        }
    }
}
