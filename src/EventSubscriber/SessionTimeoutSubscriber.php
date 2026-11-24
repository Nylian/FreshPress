<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SessionTimeoutSubscriber implements EventSubscriberInterface
{
    private const TIMEOUT = 900;

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (in_array($route, ['app_login', 'app_logout'], true)) {
            return;
        }

        if ($this->security->getUser() === null || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $now = time();
        $lastActivity = $session->get('last_activity');

        if (
            is_int($lastActivity) &&
            ($now - $lastActivity) > self::TIMEOUT
        ) {
            $session->invalidate();

            $event->setResponse(
                new RedirectResponse(
                    $this->urlGenerator->generate('app_login')
                )
            );

            return;
        }

        $session->set('last_activity', $now);
    }
}