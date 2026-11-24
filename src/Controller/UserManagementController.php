<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    #[Route('/user-management', name: 'app_user_management', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user_management/index.html.twig', [
            'users' => $userRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/user-management/new', name: 'app_user_management_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_user', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $fullName = trim($request->request->get('fullName', ''));
            $email = strtolower(trim($request->request->get('email', '')));
            $password = $request->request->get('password', '');
            $role = $request->request->get('role', 'ROLE_EMPLOYEE');

            if ($fullName === '' || $email === '' || $password === '') {
                $this->addFlash('error', 'Please complete all required fields.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
            } elseif (strlen($password) < 8) {
                $this->addFlash('error', 'Password must contain at least 8 characters.');
            } elseif (!in_array($role, ['ROLE_ADMIN', 'ROLE_EMPLOYEE'], true)) {
                $this->addFlash('error', 'Invalid user role.');
            } elseif ($entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'That email address is already registered.');
            } else {
                $user = new User();
                $user->setFullName($fullName);
                $user->setEmail($email);
                $user->setRoles([$role]);
                $user->setPassword($passwordHasher->hashPassword($user, $password));

                $entityManager->persist($user);
                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'User Created',
                'Created account: ' . $fullName . '.'                );

                $this->addFlash('success', 'User account created successfully.');

                return $this->redirectToRoute('app_user_management');
            }
        }

        return $this->render('user_management/form.html.twig', [
            'user' => null,
            'formTitle' => 'Add User Account',
        ]);
    }

    #[Route('/user-management/{id}/edit', name: 'app_user_management_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ): Response {
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot edit your own account here.');
            return $this->redirectToRoute('app_user_management');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_user_' . $user->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $fullName = trim($request->request->get('fullName', ''));
            $email = strtolower(trim($request->request->get('email', '')));
            $password = $request->request->get('password', '');
            $role = $request->request->get('role', 'ROLE_EMPLOYEE');

            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($fullName === '' || $email === '') {
                $this->addFlash('error', 'Name and email are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
            } elseif ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'That email address is already registered.');
            } elseif (!in_array($role, ['ROLE_ADMIN', 'ROLE_EMPLOYEE'], true)) {
                $this->addFlash('error', 'Invalid user role.');
            } elseif ($password !== '' && strlen($password) < 8) {
                $this->addFlash('error', 'New password must contain at least 8 characters.');
            } else {
                $user->setFullName($fullName);
                $user->setEmail($email);
                $user->setRoles([$role]);

                if ($password !== '') {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                }

                $entityManager->flush();

                $activityLogger->log(
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    'User Updated',
                    'Updated account: ' . $fullName . '.'
                );

                $this->addFlash('success', 'User account updated successfully.');

                return $this->redirectToRoute('app_user_management');
            }
        }

        return $this->render('user_management/form.html.twig', [
            'user' => $user,
            'formTitle' => 'Edit User Account',
        ]);
    }

    #[Route('/user-management/{id}/delete', name: 'app_user_management_delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_management');
        }

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $deletedName = $user->getFullName();
        $deletedEmail = $user->getEmail();

        $entityManager->remove($user);
        $entityManager->flush();

        $activityLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'User Deleted',
            'Deleted account: ' . $deletedName . '.'        );

        $this->addFlash('success', 'User account deleted successfully.');

        return $this->redirectToRoute('app_user_management');
    }
}
