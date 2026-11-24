<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\LaundryTransaction;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\LaundryTransactionRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PaymentController extends AbstractController
{
    private const METHODS = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];
    private const STATUSES = ['Completed', 'Pending', 'Refunded'];

    #[Route('/payment', name: 'app_payment', methods: ['GET'])]
    public function index(
        Request $request,
        PaymentRepository $paymentRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $query = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.laundryTransaction', 't')
            ->leftJoin('t.customer', 'c')
            ->addSelect('t', 'c');

        if ($search !== '') {
            $query
                ->where('p.paymentNumber LIKE :search')
                ->orWhere('t.transactionNumber LIKE :search')
                ->orWhere('c.firstName LIKE :search')
                ->orWhere('c.lastName LIKE :search')
                ->orWhere('p.paymentMethod LIKE :search')
                ->orWhere('p.paymentStatus LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $payments = $query
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'search' => $search,
        ]);
    }

    #[Route('/payment/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        LaundryTransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $payment = new Payment();
        $transactions = $transactionRepository->findBy([], ['id' => 'DESC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'payment_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $transaction = $transactionRepository->find(
                $request->request->get('laundryTransaction')
            );

            $amount = trim($request->request->get('amount', ''));
            $method = trim($request->request->get('paymentMethod', ''));
            $status = trim($request->request->get('paymentStatus', ''));
            $date = trim($request->request->get('paymentDate', ''));
            $reference = trim($request->request->get('referenceNumber', ''));

            $errors = [];

            if (!$transaction instanceof LaundryTransaction) {
                $errors[] = 'Please select a valid laundry transaction.';
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = 'Please enter a valid payment amount greater than zero.';
            }

            if (!in_array($method, self::METHODS, true)) {
                $errors[] = 'Please select a valid payment method.';
            }

            if (!in_array($status, self::STATUSES, true)) {
                $errors[] = 'Please select a valid payment status.';
            }

            if ($date === '') {
                $errors[] = 'Please provide the payment date.';
            }

            $paymentDate = null;

            if ($date !== '') {
                try {
                    $paymentDate = new \DateTimeImmutable($date);
                } catch (\Exception $e) {
                    $errors[] = 'Please enter a valid payment date.';
                }
            }

            if (
                empty($errors)
                && $transaction instanceof LaundryTransaction
                && $status === 'Completed'
            ) {
                $alreadyPaid = (float) $paymentRepository
                    ->createQueryBuilder('p')
                    ->select('COALESCE(SUM(p.amount), 0)')
                    ->where('p.laundryTransaction = :transaction')
                    ->andWhere('p.paymentStatus = :status')
                    ->setParameter('transaction', $transaction)
                    ->setParameter('status', 'Completed')
                    ->getQuery()
                    ->getSingleScalarResult();

                $remaining = (float) $transaction->getTotalAmount() - $alreadyPaid;

                if ((float) $amount > $remaining + 0.001) {
                    $errors[] = 'Payment exceeds the remaining balance of ₱'
                        . number_format(max(0, $remaining), 2) . '.';
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $payment->setPaymentNumber(
                    'PAY-' . strtoupper(bin2hex(random_bytes(4)))
                );
                $payment->setLaundryTransaction($transaction);
                $payment->setAmount(number_format((float) $amount, 2, '.', ''));
                $payment->setPaymentMethod($method);
                $payment->setPaymentStatus($status);
                $payment->setPaymentDate($paymentDate);
                $payment->setReferenceNumber($reference !== '' ? $reference : null);

                $entityManager->persist($payment);
                $entityManager->flush();

                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Payment Created',
                'Recorded payment ' . $payment->getPaymentNumber() .
                ' for transaction ' . $transaction->getTransactionNumber() .
                ' with amount ₱' . number_format((float) $amount, 2) .
                ' via ' . $method . '. Status: ' . $status . '.'
                );


                $this->addFlash('success', 'Payment recorded successfully.');

                return $this->redirectToRoute('app_payment');
            }
        }

        return $this->render('payment/form.html.twig', [
            'payment' => $payment,
            'transactions' => $transactions,
            'methods' => self::METHODS,
            'statuses' => self::STATUSES,
            'page_title' => 'Record Payment',
            'form_action' => $this->generateUrl('app_payment_new'),
        ]);
    }
}
