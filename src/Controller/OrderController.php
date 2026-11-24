<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogger;
use App\Entity\LaundryTransaction;
use App\Repository\LaundryTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    private const STATUSES = [
        'Pending',
        'Processing',
        'Ready for Pickup',
        'Completed',
        'Cancelled',
    ];

    #[Route('/order', name: 'app_order', methods: ['GET'])]
    public function index(
        Request $request,
        LaundryTransactionRepository $transactionRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $queryBuilder = $transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.customer', 'c')
            ->addSelect('c');

        if ($search !== '') {
            $queryBuilder
                ->where('t.transactionNumber LIKE :search')
                ->orWhere('c.firstName LIKE :search')
                ->orWhere('c.lastName LIKE :search')
                ->orWhere('t.serviceType LIKE :search')
                ->orWhere('t.status LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $orders = $queryBuilder
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'search' => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    
#[Route('/order/{id}/status', name: 'app_order_update_status', methods: ['POST'])]
public function updateStatus(
    LaundryTransaction $transaction,
    Request $request,
    EntityManagerInterface $entityManager,
    ActivityLogger $activityLogger
): Response {
    if (!$this->isCsrfTokenValid(
        'order_status_' . $transaction->getId(),
        $request->request->get('_token')
    )) {
        throw $this->createAccessDeniedException();
    }

    $status = $request->request->get('status', '');

    if (!in_array($status, self::STATUSES, true)) {
        $this->addFlash('error', 'Please select a valid order status.');

        return $this->redirectToRoute('app_order');
    }

    $transaction->setStatus($status);
    $entityManager->flush();

    $activityLogger->log(
        $this->getUser() instanceof User ? $this->getUser() : null,
        'Order Status Updated',
        'Updated order ' . $transaction->getTransactionNumber() .
        ' to status ' . $status . '.'
    );

    $this->addFlash('success', 'Order status updated successfully.');

    return $this->redirectToRoute('app_order');
    }

}
