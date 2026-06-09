<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterListener implements EventSubscriberInterface
{
    // Naming this variable $apiLimiter tells Symfony to look up your "api_limiter" YAML bucket
    public function __construct(
        private RateLimiterFactory $apiLimiter
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Extract the remote client user address handle
        $ip = $request->getClientIp() ?? 'anonymous';

        // Use the factory to build a state check bucket bound to this individual IP address
        $limiter = $this->apiLimiter->create($ip);

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Too many requests. Please slow down.');
        }
    }
}
