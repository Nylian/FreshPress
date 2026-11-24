<?php

namespace App\Controller;

use App\Entity\MachineMaintenance;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\MachineMaintenanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MachineMaintenanceController extends AbstractController
{
    #[Route('/machine-maintenance', name: 'app_machine_maintenance', methods: ['GET'])]
    public function index(
        Request $request,
        MachineMaintenanceRepository $repository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $qb = $repository->createQueryBuilder('m')
            ->orderBy('m.maintenanceDate', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere(
                'm.machineName LIKE :search
                OR m.maintenanceType LIKE :search
                OR m.status LIKE :search
                OR m.description LIKE :search'
            )
            ->setParameter('search', '%' . $search . '%');
        }

        $records = $qb->getQuery()->getResult();

        $upcomingCount = $repository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.nextMaintenanceDate >= :today')
            ->andWhere('m.status != :completed')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('completed', 'Completed')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('machine_maintenance/index.html.twig', [
            'records' => $records,
            'search' => $search,
            'upcomingCount' => (int) $upcomingCount,
        ]);
    }

    #[Route('/machine-maintenance/new', name: 'app_machine_maintenance_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('maintenance_create', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $machineName = trim($request->request->get('machineName', ''));
            $maintenanceType = trim($request->request->get('maintenanceType', ''));
            $maintenanceDateInput = $request->request->get('maintenanceDate', '');
            $nextMaintenanceDateInput = $request->request->get('nextMaintenanceDate', '');
            $status = $request->request->get('status', 'Scheduled');
            $description = trim($request->request->get('description', ''));

            $maintenanceDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $maintenanceDateInput);
            $nextMaintenanceDate = $nextMaintenanceDateInput !== ''
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $nextMaintenanceDateInput)
                : null;

            $allowedStatuses = ['Scheduled', 'In Progress', 'Completed'];

            if (
                $machineName === '' ||
                strlen($machineName) > 100 ||
                $maintenanceType === '' ||
                strlen($maintenanceType) > 100 ||
                !$maintenanceDate ||
                $maintenanceDate->format('Y-m-d') !== $maintenanceDateInput ||
                ($nextMaintenanceDateInput !== '' && (
                    !$nextMaintenanceDate ||
                    $nextMaintenanceDate->format('Y-m-d') !== $nextMaintenanceDateInput
                )) ||
                !in_array($status, $allowedStatuses, true)
            ) {
                $this->addFlash('error', 'Please provide valid maintenance information.');
                return $this->redirectToRoute('app_machine_maintenance_new');
            }

            if ($nextMaintenanceDate && $nextMaintenanceDate < $maintenanceDate) {
                $this->addFlash('error', 'Next maintenance date cannot be earlier than the maintenance date.');
                return $this->redirectToRoute('app_machine_maintenance_new');
            }

            $record = new MachineMaintenance();
            $record->setMachineName($machineName);
            $record->setMaintenanceType($maintenanceType);
            $record->setMaintenanceDate($maintenanceDate);
            $record->setNextMaintenanceDate($nextMaintenanceDate);
            $record->setStatus($status);
            $record->setDescription($description !== '' ? $description : null);

            $entityManager->persist($record);
            $entityManager->flush();

            $activityLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'Machine Maintenance Created',
            'Added maintenance record for ' . $record->getMachineName() .
            ', type: ' . $record->getMaintenanceType() .
            ', status: ' . $record->getStatus() . '.'
            );

            $this->addFlash('success', 'Maintenance record added successfully.');

            return $this->redirectToRoute('app_machine_maintenance');
        }

        return $this->render('machine_maintenance/form.html.twig', [
            'record' => null,
            'formTitle' => 'Add Maintenance Record',
        ]);
    }

    #[Route('/machine-maintenance/{id}/edit', name: 'app_machine_maintenance_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        MachineMaintenanceRepository $repository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $record = $repository->find($id);

        if (!$record) {
            throw $this->createNotFoundException('Maintenance record not found.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('maintenance_edit_' . $id, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $machineName = trim($request->request->get('machineName', ''));
            $maintenanceType = trim($request->request->get('maintenanceType', ''));
            $maintenanceDateInput = $request->request->get('maintenanceDate', '');
            $nextMaintenanceDateInput = $request->request->get('nextMaintenanceDate', '');
            $status = $request->request->get('status', '');
            $description = trim($request->request->get('description', ''));

            $maintenanceDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $maintenanceDateInput);
            $nextMaintenanceDate = $nextMaintenanceDateInput !== ''
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $nextMaintenanceDateInput)
                : null;

            $allowedStatuses = ['Scheduled', 'In Progress', 'Completed'];

            if (
                $machineName === '' ||
                strlen($machineName) > 100 ||
                $maintenanceType === '' ||
                strlen($maintenanceType) > 100 ||
                !$maintenanceDate ||
                $maintenanceDate->format('Y-m-d') !== $maintenanceDateInput ||
                ($nextMaintenanceDateInput !== '' && (
                    !$nextMaintenanceDate ||
                    $nextMaintenanceDate->format('Y-m-d') !== $nextMaintenanceDateInput
                )) ||
                !in_array($status, $allowedStatuses, true)
            ) {
                $this->addFlash('error', 'Please provide valid maintenance information.');
                return $this->redirectToRoute('app_machine_maintenance_edit', ['id' => $id]);
            }

            if ($nextMaintenanceDate && $nextMaintenanceDate < $maintenanceDate) {
                $this->addFlash('error', 'Next maintenance date cannot be earlier than the maintenance date.');
                return $this->redirectToRoute('app_machine_maintenance_edit', ['id' => $id]);
            }

            $record->setMachineName($machineName);
            $record->setMaintenanceType($maintenanceType);
            $record->setMaintenanceDate($maintenanceDate);
            $record->setNextMaintenanceDate($nextMaintenanceDate);
            $record->setStatus($status);
            $record->setDescription($description !== '' ? $description : null);

            $entityManager->flush();

            $activityLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'Machine Maintenance Updated',
            'Updated maintenance record for ' . $record->getMachineName() .
            ', type: ' . $record->getMaintenanceType() .
            ', status: ' . $record->getStatus() . '.'
            );


            $this->addFlash('success', 'Maintenance record updated successfully.');

            return $this->redirectToRoute('app_machine_maintenance');
        }

        return $this->render('machine_maintenance/form.html.twig', [
            'record' => $record,
            'formTitle' => 'Edit Maintenance Record',
        ]);
    }

    #[Route('/machine-maintenance/{id}/delete', name: 'app_machine_maintenance_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        MachineMaintenanceRepository $repository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $record = $repository->find($id);

        if (!$record) {
            throw $this->createNotFoundException('Maintenance record not found.');
        }

        if (!$this->isCsrfTokenValid('delete_maintenance_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $deletedMachineName = $record->getMachineName();
        $deletedMaintenanceType = $record->getMaintenanceType();

        $entityManager->remove($record);
        $entityManager->flush();

        $activityLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'Machine Maintenance Deleted',
            'Deleted maintenance record for ' . $deletedMachineName .
            ', type: ' . $deletedMaintenanceType . '.'
        );

        $this->addFlash('success', 'Maintenance record deleted successfully.');

        return $this->redirectToRoute('app_machine_maintenance');
    }
}
