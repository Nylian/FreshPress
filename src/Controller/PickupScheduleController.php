<?php

namespace App\Controller;

use App\Entity\PickupSchedule;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\CustomerRepository;
use App\Repository\LaundryTransactionRepository;
use App\Repository\PickupScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PickupScheduleController extends AbstractController
{
    private const STATUSES = [
        'Requested',
        'Confirmed',
        'Completed',
        'Cancelled',
    ];

    #[Route('/pickup/schedule', name: 'app_pickup_schedule', methods: ['GET'])]
    public function index(
        Request $request,
        PickupScheduleRepository $pickupScheduleRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $query = $pickupScheduleRepository->createQueryBuilder('p')
            ->leftJoin('p.customer', 'c')
            ->addSelect('c')
            ->leftJoin('p.laundryTransaction', 't')
            ->addSelect('t');

        if ($search !== '') {
            $query
                ->andWhere(
                    'c.firstName LIKE :search
                    OR c.lastName LIKE :search
                    OR c.phoneNumber LIKE :search
                    OR p.status LIKE :search
                    OR t.transactionNumber LIKE :search'
                )
                ->setParameter('search', '%' . $search . '%');
        }

        $schedules = $query
            ->orderBy('p.pickupDate', 'ASC')
            ->addOrderBy('p.pickupTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('pickup_schedule/index.html.twig', [
            'schedules' => $schedules,
            'search' => $search,
        ]);
    }

    #[Route('/pickup/schedule/new', name: 'app_pickup_schedule_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        LaundryTransactionRepository $transactionRepository,
        ActivityLogger $activityLogger
    ): Response {
        $schedule = new PickupSchedule();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'pickup_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $formData = $request->request->all();

            $customer = $customerRepository->find(
                $request->request->get('customer')
            );

            $transactionId = $request->request->get('laundryTransaction');

            $transaction = $transactionId
                ? $transactionRepository->find($transactionId)
                : null;

            $pickupDateInput = trim($request->request->get('pickupDate', ''));
            $pickupTimeInput = trim($request->request->get('pickupTime', ''));
            $notes = trim($request->request->get('notes', ''));

            $errors = [];

            $pickupDate = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $pickupDateInput
            );

            $pickupTime = \DateTimeImmutable::createFromFormat(
                '!H:i',
                $pickupTimeInput
            );

            if (!$customer) {
                $errors[] = 'Please select a valid customer.';
            }

            if ($transactionId !== '' && !$transaction) {
                $errors[] = 'Please select a valid laundry transaction.';
            }

            if ($transaction && $customer && $transaction->getCustomer()->getId() !== $customer->getId()) {
                $errors[] = 'The selected transaction does not belong to the selected customer.';
            }

            if (
                !$pickupDate ||
                $pickupDate->format('Y-m-d') !== $pickupDateInput
            ) {
                $errors[] = 'Please enter a valid pickup date.';
            } elseif ($pickupDate->format('Y-m-d') < (new \DateTimeImmutable('today'))->format('Y-m-d')) {
                $errors[] = 'Pickup date cannot be in the past.';
            }

            if (
                !$pickupTime ||
                $pickupTime->format('H:i') !== $pickupTimeInput
            ) {
                $errors[] = 'Please enter a valid pickup time.';
            }

            if (strlen($notes) > 5000) {
                $errors[] = 'Notes cannot exceed 5000 characters.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $schedule
                    ->setCustomer($customer)
                    ->setLaundryTransaction($transaction)
                    ->setPickupDate($pickupDate)
                    ->setPickupTime($pickupTime)
                    ->setStatus('Requested')
                    ->setNotes($notes !== '' ? $notes : null)
                    ->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($schedule);
                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'Pickup Schedule Created',
                    'Created pickup schedule for ' .
                    $customer->getFirstName() . ' ' . $customer->getLastName() .
                    ' on ' . $pickupDate->format('M d, Y') .
                    ' at ' . $pickupTime->format('h:i A') . '.'
                );

                $this->addFlash('success', 'Pickup schedule created successfully.');

                return $this->redirectToRoute('app_pickup_schedule');
            }
        } else {
            $formData = [];
        }

        return $this->render('pickup_schedule/form.html.twig', [
            'schedule' => $schedule,
            'customers' => $customerRepository->findBy([], ['firstName' => 'ASC']),
            'transactions' => $transactionRepository->findBy([], ['transactionNumber' => 'DESC']),
            'formData' => $formData,
            'page_title' => 'Schedule Pickup',
            'form_action' => $this->generateUrl('app_pickup_schedule_new'),
            'csrf_token' => 'pickup_create',
            'statuses' => self::STATUSES,
        ]);
    }

    #[Route('/pickup/schedule/{id}/edit', name: 'app_pickup_schedule_edit', methods: ['GET', 'POST'])]
    public function edit(
        PickupSchedule $schedule,
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        LaundryTransactionRepository $transactionRepository,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'pickup_edit_' . $schedule->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $formData = $request->request->all();

            $customer = $customerRepository->find(
                $request->request->get('customer')
            );

            $transactionId = $request->request->get('laundryTransaction');

            $transaction = $transactionId
                ? $transactionRepository->find($transactionId)
                : null;

            $pickupDateInput = trim($request->request->get('pickupDate', ''));
            $pickupTimeInput = trim($request->request->get('pickupTime', ''));
            $status = trim($request->request->get('status', ''));
            $notes = trim($request->request->get('notes', ''));

            $errors = [];

            $pickupDate = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $pickupDateInput
            );

            $pickupTime = \DateTimeImmutable::createFromFormat(
                '!H:i',
                $pickupTimeInput
            );

            if (!$customer) {
                $errors[] = 'Please select a valid customer.';
            }

            if ($transactionId !== '' && !$transaction) {
                $errors[] = 'Please select a valid laundry transaction.';
            }

            if ($transaction && $customer && $transaction->getCustomer()->getId() !== $customer->getId()) {
                $errors[] = 'The selected transaction does not belong to the selected customer.';
            }

            if (
                !$pickupDate ||
                $pickupDate->format('Y-m-d') !== $pickupDateInput
            ) {
                $errors[] = 'Please enter a valid pickup date.';
            } elseif (
                $status !== 'Completed' &&
                $pickupDate->format('Y-m-d') < (new \DateTimeImmutable('today'))->format('Y-m-d')
            ) {
                $errors[] = 'Pickup date cannot be in the past unless the pickup is completed.';
            }

            if (
                !$pickupTime ||
                $pickupTime->format('H:i') !== $pickupTimeInput
            ) {
                $errors[] = 'Please enter a valid pickup time.';
            }

            if (!in_array($status, self::STATUSES, true)) {
                $errors[] = 'Please select a valid pickup status.';
            }

            if (strlen($notes) > 5000) {
                $errors[] = 'Notes cannot exceed 5000 characters.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $oldStatus = $schedule->getStatus();
                $oldDate = $schedule->getPickupDate()?->format('Y-m-d');
                $oldTime = $schedule->getPickupTime()?->format('H:i');

                $schedule
                    ->setCustomer($customer)
                    ->setLaundryTransaction($transaction)
                    ->setPickupDate($pickupDate)
                    ->setPickupTime($pickupTime)
                    ->setStatus($status)
                    ->setNotes($notes !== '' ? $notes : null);

                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'Pickup Schedule Updated',
                    'Updated pickup schedule #' . $schedule->getId() .
                    ' for ' . $customer->getFirstName() . ' ' . $customer->getLastName() .
                    '. Status: ' . $oldStatus . ' to ' . $status .
                    '. Previous schedule: ' . $oldDate . ' ' . $oldTime .
                    '. New schedule: ' . $pickupDate->format('Y-m-d') .
                    ' ' . $pickupTime->format('H:i') . '.'
                );

                $this->addFlash('success', 'Pickup schedule updated successfully.');

                return $this->redirectToRoute('app_pickup_schedule');
            }
        } else {
            $formData = [];
        }

        return $this->render('pickup_schedule/form.html.twig', [
            'schedule' => $schedule,
            'customers' => $customerRepository->findBy([], ['firstName' => 'ASC']),
            'transactions' => $transactionRepository->findBy([], ['transactionNumber' => 'DESC']),
            'formData' => $formData,
            'page_title' => 'Edit Pickup Schedule',
            'form_action' => $this->generateUrl('app_pickup_schedule_edit', [
                'id' => $schedule->getId(),
            ]),
            'csrf_token' => 'pickup_edit_' . $schedule->getId(),
            'statuses' => self::STATUSES,
        ]);
    }
}