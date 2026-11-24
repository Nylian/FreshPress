<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogger;
use App\Entity\Customer;
use App\Entity\LaundryTransaction;
use App\Repository\CustomerRepository;
use App\Repository\LaundryTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class LaundryTransactionController extends AbstractController
{
    #[Route('/laundry/transaction', name: 'app_laundry_transaction', methods: ['GET'])]
    public function index(
        Request $request,
        LaundryTransactionRepository $transactionRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        if ($search !== '') {
            $transactions = $transactionRepository->createQueryBuilder('t')
                ->leftJoin('t.customer', 'c')
                ->addSelect('c')
                ->where('t.transactionNumber LIKE :search')
                ->orWhere('c.firstName LIKE :search')
                ->orWhere('c.lastName LIKE :search')
                ->orWhere('t.serviceType LIKE :search')
                ->orWhere('t.status LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('t.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $transactions = $transactionRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->render('laundry_transaction/index.html.twig', [
            'transactions' => $transactions,
            'search' => $search,
        ]);
    }

    #[Route('/laundry/transaction/new', name: 'app_laundry_transaction_new', methods: ['GET', 'POST'])]
   public function new(
    Request $request,
    CustomerRepository $customerRepository,
    EntityManagerInterface $entityManager,
    LaundryTransactionRepository $transactionRepository,
    ActivityLogger $activityLogger
        ): Response {
        $transaction = new LaundryTransaction();
        $customers = $customerRepository->findBy([], ['firstName' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'transaction_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $customer = $customerRepository->find(
                $request->request->get('customer')
            );

            $serviceType = trim($request->request->get('serviceType', ''));
            $weight = trim($request->request->get('weight', ''));
            $pricePerKg = trim($request->request->get('pricePerKg', ''));
            $dateReceived = trim($request->request->get('dateReceived', ''));
            $expectedPickupDate = trim($request->request->get('expectedPickupDate', ''));

            $errors = [];

            if (!$customer instanceof Customer) {
                $errors[] = 'Please select a valid customer.';
            }

            if ($serviceType === '') {
                $errors[] = 'Please select a service type.';
            }

            if (!is_numeric($weight) || (float) $weight <= 0) {
                $errors[] = 'Please enter a valid laundry weight.';
            }

            if (!is_numeric($pricePerKg) || (float) $pricePerKg < 0) {
                $errors[] = 'Please enter a valid price per kilogram.';
            }

            if ($dateReceived === '' || $expectedPickupDate === '') {
                $errors[] = 'Please provide both transaction dates.';
            }

            if (empty($errors)) {
                try {
                    $receivedDate = new \DateTimeImmutable($dateReceived);
                    $pickupDate = new \DateTimeImmutable($expectedPickupDate);

                    if ($pickupDate->format('Y-m-d') < $receivedDate->format('Y-m-d')) {
                        $errors[] = 'Expected pickup date cannot be earlier than the received date.';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Please enter valid transaction dates.';
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $totalAmount = (float) $weight * (float) $pricePerKg;

                $transaction->setTransactionNumber(
                    'TRX-' . strtoupper(bin2hex(random_bytes(4)))
                );
                $transaction->setCustomer($customer);
                $transaction->setServiceType($serviceType);
                $transaction->setWeight($weight);
                $transaction->setPricePerKg($pricePerKg);
                $transaction->setTotalAmount(number_format($totalAmount, 2, '.', ''));
                $transaction->setStatus('Pending');
                $transaction->setDateReceived($receivedDate);
                $transaction->setExpectedPickupDate($pickupDate);

                $entityManager->persist($transaction);
                $entityManager->flush();
                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Laundry Transaction Created',
                'Created transaction: ' . $transaction->getTransactionNumber() . '.'
                    );

                $this->addFlash('success', 'Laundry transaction created successfully.');

                return $this->redirectToRoute('app_laundry_transaction');
            }
        }

        return $this->render('laundry_transaction/form.html.twig', [
            'transaction' => $transaction,
            'customers' => $customers,
            'page_title' => 'New Laundry Transaction',
            'form_action' => $this->generateUrl('app_laundry_transaction_new'),
            'csrf_token' => 'transaction_create',
        ]);
    }

    #[Route('/laundry/transaction/{id}/edit', name: 'app_laundry_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(
        LaundryTransaction $transaction,
        Request $request,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $customers = $customerRepository->findBy([], ['firstName' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'transaction_edit_' . $transaction->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $customer = $customerRepository->find(
                $request->request->get('customer')
            );

            $serviceType = trim($request->request->get('serviceType', ''));
            $weight = trim($request->request->get('weight', ''));
            $pricePerKg = trim($request->request->get('pricePerKg', ''));
            $status = trim($request->request->get('status', ''));
            $dateReceived = trim($request->request->get('dateReceived', ''));
            $expectedPickupDate = trim($request->request->get('expectedPickupDate', ''));

            $errors = [];

            if (!$customer instanceof Customer) {
                $errors[] = 'Please select a valid customer.';
            }

            if ($serviceType === '') {
                $errors[] = 'Please select a service type.';
            }

            if (!is_numeric($weight) || (float) $weight <= 0) {
                $errors[] = 'Please enter a valid laundry weight.';
            }

            if (!is_numeric($pricePerKg) || (float) $pricePerKg < 0) {
                $errors[] = 'Please enter a valid price per kilogram.';
            }

            if (!in_array($status, ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled'], true)) {
                $errors[] = 'Please select a valid transaction status.';
            }

            if ($dateReceived === '' || $expectedPickupDate === '') {
                $errors[] = 'Please provide both transaction dates.';
            }

            if (empty($errors)) {
                try {
                    $receivedDate = new \DateTimeImmutable($dateReceived);
                    $pickupDate = new \DateTimeImmutable($expectedPickupDate);

                    if ($pickupDate->format('Y-m-d') < $receivedDate->format('Y-m-d')) {
                        $errors[] = 'Expected pickup date cannot be earlier than the received date.';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Please enter valid transaction dates.';
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $totalAmount = (float) $weight * (float) $pricePerKg;

                $transaction->setCustomer($customer);
                $transaction->setServiceType($serviceType);
                $transaction->setWeight($weight);
                $transaction->setPricePerKg($pricePerKg);
                $transaction->setTotalAmount(number_format($totalAmount, 2, '.', ''));
                $transaction->setStatus($status);
                $transaction->setDateReceived($receivedDate);
                $transaction->setExpectedPickupDate($pickupDate);

                $entityManager->flush();

                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Laundry Transaction Updated',
                'Updated transaction: ' . $transaction->getTransactionNumber() .
                ' to status ' . $transaction->getStatus() . '.'
                );

                $this->addFlash('success', 'Laundry transaction updated successfully.');

                return $this->redirectToRoute('app_laundry_transaction');
            }
        }

        return $this->render('laundry_transaction/form.html.twig', [
            'transaction' => $transaction,
            'customers' => $customers,
            'page_title' => 'Edit Laundry Transaction',
            'form_action' => $this->generateUrl('app_laundry_transaction_edit', [
                'id' => $transaction->getId(),
            ]),
            'csrf_token' => 'transaction_edit_' . $transaction->getId(),
        ]);
    }

    #[Route('/laundry/transaction/{id}/delete', name: 'app_laundry_transaction_delete', methods: ['POST'])]
    public function delete(
        LaundryTransaction $transaction,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete_transaction_' . $transaction->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException();
        }

        $deletedTransactionNumber = $transaction->getTransactionNumber();

        $entityManager->remove($transaction);
        $entityManager->flush();

        $activityLogger->log(
        $this->getUser() instanceof User ? $this->getUser() : null,
        'Laundry Transaction Deleted',
        'Deleted transaction: ' . $deletedTransactionNumber . '.'
        );

$this->addFlash('success', 'Laundry transaction deleted successfully.');

return $this->redirectToRoute('app_laundry_transaction');
    }
}
