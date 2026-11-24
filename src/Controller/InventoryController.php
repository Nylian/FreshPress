<?php

namespace App\Controller;

use App\Entity\InventoryItem;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class InventoryController extends AbstractController
{
    #[Route('/inventory', name: 'app_inventory', methods: ['GET'])]
    public function index(
        Request $request,
        InventoryItemRepository $inventoryRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $query = $inventoryRepository->createQueryBuilder('i');

        if ($search !== '') {
            $query
                ->where('i.itemName LIKE :search')
                ->orWhere('i.category LIKE :search')
                ->orWhere('i.unit LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $items = $query
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();

        $lowStockCount = $inventoryRepository->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.quantity <= i.reorderLevel')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('inventory/index.html.twig', [
            'items' => $items,
            'search' => $search,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    #[Route('/inventory/new', name: 'app_inventory_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $item = new InventoryItem();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'inventory_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $itemName = trim($request->request->get('itemName', ''));
            $category = trim($request->request->get('category', ''));
            $quantity = $request->request->get('quantity', '');
            $unit = trim($request->request->get('unit', ''));
            $reorderLevel = $request->request->get('reorderLevel', '');
            $unitCost = $request->request->get('unitCost', '');

            $errors = [];

            if ($itemName === '' || $category === '' || $unit === '') {
                $errors[] = 'Item name, category, and unit are required.';
            }

            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 0) {
                $errors[] = 'Quantity must be a non-negative whole number.';
            }

            if (filter_var($reorderLevel, FILTER_VALIDATE_INT) === false || (int) $reorderLevel < 0) {
                $errors[] = 'Reorder level must be a non-negative whole number.';
            }

            if (!is_numeric($unitCost) || (float) $unitCost < 0) {
                $errors[] = 'Please enter a valid unit cost.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $item->setItemName($itemName);
                $item->setCategory($category);
                $item->setQuantity((int) $quantity);
                $item->setUnit($unit);
                $item->setReorderLevel((int) $reorderLevel);
                $item->setUnitCost(number_format((float) $unitCost, 2, '.', ''));

                $entityManager->persist($item);
                $entityManager->flush();
                
                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Inventory Item Created',
                'Added inventory item: ' . $item->getItemName() .
                ', quantity: ' . $item->getQuantity() . ' ' . $item->getUnit() . '.'
                );


                $this->addFlash('success', 'Inventory item added successfully.');

                return $this->redirectToRoute('app_inventory');
            }
        }

        return $this->render('inventory/form.html.twig', [
            'item' => $item,
            'page_title' => 'Add Inventory Item',
            'form_action' => $this->generateUrl('app_inventory_new'),
            'csrf_token' => 'inventory_create',
        ]);
    }

    #[Route('/inventory/{id}/edit', name: 'app_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(
        InventoryItem $item,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'inventory_edit_' . $item->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $itemName = trim($request->request->get('itemName', ''));
            $category = trim($request->request->get('category', ''));
            $quantity = $request->request->get('quantity', '');
            $unit = trim($request->request->get('unit', ''));
            $reorderLevel = $request->request->get('reorderLevel', '');
            $unitCost = $request->request->get('unitCost', '');

            $errors = [];

            if ($itemName === '' || $category === '' || $unit === '') {
                $errors[] = 'Item name, category, and unit are required.';
            }

            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 0) {
                $errors[] = 'Quantity must be a non-negative whole number.';
            }

            if (filter_var($reorderLevel, FILTER_VALIDATE_INT) === false || (int) $reorderLevel < 0) {
                $errors[] = 'Reorder level must be a non-negative whole number.';
            }

            if (!is_numeric($unitCost) || (float) $unitCost < 0) {
                $errors[] = 'Please enter a valid unit cost.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $item->setItemName($itemName);
                $item->setCategory($category);
                $item->setQuantity((int) $quantity);
                $item->setUnit($unit);
                $item->setReorderLevel((int) $reorderLevel);
                $item->setUnitCost(number_format((float) $unitCost, 2, '.', ''));

                $entityManager->flush();
        
                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Inventory Item Updated',
                'Updated inventory item: ' . $item->getItemName() .
                ', quantity: ' . $item->getQuantity() . ' ' . $item->getUnit() . '.'
                );

                $this->addFlash('success', 'Inventory item updated successfully.');

                return $this->redirectToRoute('app_inventory');
            }
        }

        return $this->render('inventory/form.html.twig', [
            'item' => $item,
            'page_title' => 'Edit Inventory Item',
            'form_action' => $this->generateUrl('app_inventory_edit', [
                'id' => $item->getId(),
            ]),
            'csrf_token' => 'inventory_edit_' . $item->getId(),
        ]);
    }

    #[Route('/inventory/{id}/delete', name: 'app_inventory_delete', methods: ['POST'])]
    public function delete(
        InventoryItem $item,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete_inventory_' . $item->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException();
        }

        $deletedItemName = $item->getItemName();
        $deletedQuantity = $item->getQuantity();
        $deletedUnit = $item->getUnit();

        $entityManager->remove($item);
        $entityManager->flush();

        $activityLogger->log(
        $this->getUser() instanceof User ? $this->getUser() : null,
        'Inventory Item Deleted',
        'Deleted inventory item: ' . $item->getItemName() .
        ', quantity: ' . $item->getQuantity() . ' ' . $item->getUnit() . '.'
         );

        $this->addFlash('success', 'Inventory item deleted successfully.');

        return $this->redirectToRoute('app_inventory');
    }
}
