<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/activity-logs', name: 'app_activity_logs', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $queryBuilder = $activityLogRepository
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($search !== '') {
            $queryBuilder
                ->where('a.userName LIKE :search')
                ->orWhere('a.userEmail LIKE :search')
                ->orWhere('a.action LIKE :search')
                ->orWhere('a.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $logs = $queryBuilder->getQuery()->getResult();

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'search' => $search,
        ]);
    }
}
