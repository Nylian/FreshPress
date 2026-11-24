<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SalesController extends AbstractController
{
    #[Route('/sales', name: 'app_sales', methods: ['GET'])]
    public function index(
        Request $request,
        PaymentRepository $paymentRepository,
        ExpenseRepository $expenseRepository
    ): Response {
        $period = $request->query->get('period', 'all');
        $startDateInput = $request->query->get('startDate', '');
        $endDateInput = $request->query->get('endDate', '');

        $startDate = null;
        $endDate = null;

        if ($period === 'today') {
            $startDate = new \DateTimeImmutable('today');
            $endDate = $startDate->modify('+1 day');
        } elseif ($period === 'month') {
            $startDate = new \DateTimeImmutable('first day of this month');
            $endDate = $startDate->modify('+1 month');
        } elseif ($period === 'year') {
            $startDate = new \DateTimeImmutable('first day of January this year');
            $endDate = $startDate->modify('+1 year');
        } elseif ($period === 'custom') {
            $startDate = $startDateInput !== ''
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $startDateInput)
                : null;

            $endDate = $endDateInput !== ''
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $endDateInput)
                : null;

            if ($startDate && $endDate) {
                $endDate = $endDate->modify('+1 day');
            }
        } else {
            $period = 'all';
        }

        $paymentQuery = $paymentRepository->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->where('p.paymentStatus = :status')
            ->setParameter('status', 'Completed');

        $paymentCountQuery = $paymentRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.paymentStatus = :status')
            ->setParameter('status', 'Completed');

        $expenseQuery = $expenseRepository->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.amount), 0)');

        $expenseCountQuery = $expenseRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        if ($startDate) {
            $paymentQuery->andWhere('p.paymentDate >= :startDate')
                ->setParameter('startDate', $startDate);

            $paymentCountQuery->andWhere('p.paymentDate >= :startDate')
                ->setParameter('startDate', $startDate);

            $expenseQuery->andWhere('e.expenseDate >= :startDate')
                ->setParameter('startDate', $startDate);

            $expenseCountQuery->andWhere('e.expenseDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $paymentQuery->andWhere('p.paymentDate < :endDate')
                ->setParameter('endDate', $endDate);

            $paymentCountQuery->andWhere('p.paymentDate < :endDate')
                ->setParameter('endDate', $endDate);

            $expenseQuery->andWhere('e.expenseDate < :endDate')
                ->setParameter('endDate', $endDate);

            $expenseCountQuery->andWhere('e.expenseDate < :endDate')
                ->setParameter('endDate', $endDate);
        }

        $totalSales = (float) $paymentQuery->getQuery()->getSingleScalarResult();
        $totalExpenses = (float) $expenseQuery->getQuery()->getSingleScalarResult();

        $completedPaymentCount = (int) $paymentCountQuery->getQuery()->getSingleScalarResult();
        $expenseCount = (int) $expenseCountQuery->getQuery()->getSingleScalarResult();

        $netProfit = $totalSales - $totalExpenses;

        return $this->render('sales/index.html.twig', [
            'totalSales' => $totalSales,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
            'completedPaymentCount' => $completedPaymentCount,
            'expenseCount' => $expenseCount,
            'period' => $period,
            'startDate' => $startDateInput,
            'endDate' => $endDateInput,
        ]);
    }
}
