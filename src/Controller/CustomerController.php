<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CustomerController extends AbstractController
{
    #[Route('/customer', name: 'app_customer', methods: ['GET'])]
    public function index(
        Request $request,
        CustomerRepository $customerRepository
    ): Response {
        $search = trim($request->query->get('search', ''));

        if ($search !== '') {
            $customers = $customerRepository->createQueryBuilder('c')
                ->where('c.firstName LIKE :search')
                ->orWhere('c.lastName LIKE :search')
                ->orWhere('c.phoneNumber LIKE :search')
                ->orWhere('c.email LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('c.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $customers = $customerRepository->findBy(
                [],
                ['id' => 'DESC']
            );
        }

        return $this->render('customer/index.html.twig', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    #[Route('/customer/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $customer = new Customer();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'customer_create',
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $firstName = trim($request->request->get('firstName', ''));
            $lastName = trim($request->request->get('lastName', ''));
            $phoneNumber = trim($request->request->get('phoneNumber', ''));
            $email = trim($request->request->get('email', ''));
            $address = trim($request->request->get('address', ''));

            if ($firstName === '' || $lastName === '' || $phoneNumber === '') {
                $this->addFlash('error', 'First name, last name, and phone number are required.');
            } else {
                $customer->setFirstName($firstName);
                $customer->setLastName($lastName);
                $customer->setPhoneNumber($phoneNumber);
                $customer->setEmail($email !== '' ? $email : null);
                $customer->setAddress($address !== '' ? $address : null);

                $entityManager->persist($customer);
                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'Customer Created',
                    'Registered customer: ' . $firstName . ' ' . $lastName . '.'
                );

                $this->addFlash('success', 'Customer registered successfully.');

                return $this->redirectToRoute('app_customer');
            }
        }

        return $this->render('customer/form.html.twig', [
            'customer' => $customer,
            'page_title' => 'Add Customer',
            'form_action' => $this->generateUrl('app_customer_new'),
            'csrf_token' => 'customer_create',
        ]);
    }

    #[Route('/customer/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(
        Customer $customer,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'customer_edit_' . $customer->getId(),
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException();
            }

            $firstName = trim($request->request->get('firstName', ''));
            $lastName = trim($request->request->get('lastName', ''));
            $phoneNumber = trim($request->request->get('phoneNumber', ''));
            $email = trim($request->request->get('email', ''));
            $address = trim($request->request->get('address', ''));

            if ($firstName === '' || $lastName === '' || $phoneNumber === '') {
                $this->addFlash('error', 'First name, last name, and phone number are required.');
            } else {
                $customer->setFirstName($firstName);
                $customer->setLastName($lastName);
                $customer->setPhoneNumber($phoneNumber);
                $customer->setEmail($email !== '' ? $email : null);
                $customer->setAddress($address !== '' ? $address : null);

                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'Customer Updated',
                    'Updated customer: ' . $firstName . ' ' . $lastName . '.'
                );

                $this->addFlash('success', 'Customer information updated successfully.');

                return $this->redirectToRoute('app_customer');
            }
        }

        return $this->render('customer/form.html.twig', [
            'customer' => $customer,
            'page_title' => 'Edit Customer',
            'form_action' => $this->generateUrl('app_customer_edit', [
                'id' => $customer->getId(),
            ]),
            'csrf_token' => 'customer_edit_' . $customer->getId(),
        ]);
    }

    #[Route('/customer/{id}/delete', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(
        Customer $customer,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete_customer_' . $customer->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException();
        }

        $deletedName = $customer->getFirstName() . ' ' . $customer->getLastName();

        $entityManager->remove($customer);
        $entityManager->flush();

        $activityLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'Customer Deleted',
            'Deleted customer: ' . $deletedName . '.'
        );

        $this->addFlash('success', 'Customer deleted successfully.');

        return $this->redirectToRoute('app_customer');
    }
}
