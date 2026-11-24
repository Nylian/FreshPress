<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ExpenseController extends AbstractController
{
    #[Route('/expense', name: 'app_expense', methods: ['GET'])]
    public function index(
        Request $request,
        ExpenseRepository $expenseRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        $query = $expenseRepository->createQueryBuilder('e');

        if ($search !== '') {
            $query
                ->where('e.expenseName LIKE :search')
                ->orWhere('e.category LIKE :search')
                ->orWhere('e.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $expenses = $query
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('expense/index.html.twig', [
            'expenses' => $expenses,
            'search' => $search,
        ]);
    }

    #[Route('/expense/new', name: 'app_expense_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $expense = new Expense();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'expense_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $expenseName = trim($request->request->get('expenseName', ''));
            $category = trim($request->request->get('category', ''));
            $amount = $request->request->get('amount', '');
            $expenseDate = $request->request->get('expenseDate', '');
            $description = trim($request->request->get('description', ''));

            $errors = [];

            if ($expenseName === '' || $category === '') {
                $errors[] = 'Expense name and category are required.';
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = 'Please enter a valid expense amount greater than zero.';
            }

            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $expenseDate);

            if (!$date || $date->format('Y-m-d') !== $expenseDate) {
                $errors[] = 'Please enter a valid expense date.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $expense->setExpenseName($expenseName);
                $expense->setCategory($category);
                $expense->setAmount(number_format((float) $amount, 2, '.', ''));
                $expense->setExpenseDate($date);
                $expense->setDescription($description !== '' ? $description : null);

                $entityManager->persist($expense);
                $entityManager->flush();
 
                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Expense Created',
                'Recorded expense: ' . $expense->getExpenseName() .
                ', amount: ₱' . number_format((float) $amount, 2) .
                ', category: ' . $category . '.'
                );


                $this->addFlash('success', 'Expense recorded successfully.');

                return $this->redirectToRoute('app_expense');
            }
        }

        return $this->render('expense/form.html.twig', [
            'expense' => $expense,
            'page_title' => 'Record Expense',
            'form_action' => $this->generateUrl('app_expense_new'),
            'csrf_token' => 'expense_create',
        ]);
    }

    #[Route('/expense/{id}/edit', name: 'app_expense_edit', methods: ['GET', 'POST'])]
    public function edit(
        Expense $expense,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'expense_edit_' . $expense->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $expenseName = trim($request->request->get('expenseName', ''));
            $category = trim($request->request->get('category', ''));
            $amount = $request->request->get('amount', '');
            $expenseDate = $request->request->get('expenseDate', '');
            $description = trim($request->request->get('description', ''));

            $errors = [];

            if ($expenseName === '' || $category === '') {
                $errors[] = 'Expense name and category are required.';
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = 'Please enter a valid expense amount greater than zero.';
            }

            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $expenseDate);

            if (!$date || $date->format('Y-m-d') !== $expenseDate) {
                $errors[] = 'Please enter a valid expense date.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $expense->setExpenseName($expenseName);
                $expense->setCategory($category);
                $expense->setAmount(number_format((float) $amount, 2, '.', ''));
                $expense->setExpenseDate($date);
                $expense->setDescription($description !== '' ? $description : null);

                $entityManager->flush();

                $activityLogger->log(
                $this->getUser() instanceof User ? $this->getUser() : null,
                'Expense Updated',
                'Updated expense: ' . $expense->getExpenseName() .
                ', amount: ₱' . number_format((float) $amount, 2) .
                ', category: ' . $category . '.'
                );


                $this->addFlash('success', 'Expense updated successfully.');

                return $this->redirectToRoute('app_expense');
            }
        }

        return $this->render('expense/form.html.twig', [
            'expense' => $expense,
            'page_title' => 'Edit Expense',
            'form_action' => $this->generateUrl('app_expense_edit', [
                'id' => $expense->getId(),
            ]),
            'csrf_token' => 'expense_edit_' . $expense->getId(),
        ]);
    }

    #[Route('/expense/{id}/delete', name: 'app_expense_delete', methods: ['POST'])]
    public function delete(
        Expense $expense,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete_expense_' . $expense->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException();
        }
  
        $deletedExpenseName = $expense->getExpenseName();
        $deletedAmount = $expense->getAmount();
        $deletedCategory = $expense->getCategory();

        $entityManager->remove($expense);
        $entityManager->flush();
        
        $activityLogger->log(
        $this->getUser() instanceof User ? $this->getUser() : null,
        'Expense Deleted',
        'Deleted expense: ' . $deletedExpenseName .
        ', amount: ₱' . number_format((float) $deletedAmount, 2) .
        ', category: ' . $deletedCategory . '.'
        );

        $this->addFlash('success', 'Expense deleted successfully.');

        return $this->redirectToRoute('app_expense');
    }
}
