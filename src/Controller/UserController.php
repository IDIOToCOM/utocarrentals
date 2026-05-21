<?php

namespace App\Controller;

use App\Entity\Login;
use App\Entity\ActivityLog;
use App\Entity\Booking;
use App\Entity\CarInventory;
use App\Entity\Payment;
use App\Form\ChangePasswordType;
use App\Form\LoginEditType;
use App\Form\UserCreateType;
use App\Repository\LoginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(LoginRepository $loginRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'logins' => $loginRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $login = new Login();
        $form = $this->createForm(UserCreateType::class, $login);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedRoles = $form->get('roles')->getData();
            $normalizedRoles = array_values(array_unique(is_array($submittedRoles) ? $submittedRoles : []));
            if (empty($normalizedRoles)) {
                $normalizedRoles = ['ROLE_USER'];
            }
            $login->setRoles($normalizedRoles);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Encode the plain password
            $hashedPassword = $userPasswordHasher->hashPassword($login, $plainPassword);
            $login->setPassword($hashedPassword);
            
            $entityManager->persist($login);
            $entityManager->flush();
            
            // Manually log the user creation
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('CREATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($login->getId());
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log user creation: ' . $e->getMessage());
            }

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'login' => $login,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/change-password', name: 'app_user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        Login $login,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Encode the plain password
            $hashedPassword = $userPasswordHasher->hashPassword($login, $plainPassword);
            $login->setPassword($hashedPassword);
            
            $userId = $login->getId();
            $entityManager->flush();
            
            // Manually log the password change
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('UPDATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($userId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log password change: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Password changed successfully!');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/change_password.html.twig', [
            'login' => $login,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Login $login,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(LoginEditType::class, $login);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedRoles = $form->get('roles')->getData();
            $normalizedRoles = array_values(array_unique(is_array($submittedRoles) ? $submittedRoles : []));
            if (empty($normalizedRoles)) {
                $normalizedRoles = ['ROLE_USER'];
            }
            $login->setRoles($normalizedRoles);

            $userId = $login->getId();
            $entityManager->flush();
            
            // Manually log the update
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('UPDATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($userId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log user update: ' . $e->getMessage());
            }

            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'login' => $login,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        Login $login,
        EntityManagerInterface $entityManager
    ): Response {
        // Prevent disabling your own account
        $currentUser = $this->getUser();
        if ($currentUser && $currentUser->getId() === $login->getId()) {
            $this->addFlash('error', 'You cannot disable your own account.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('toggle-status'.$login->getId(), $request->getPayload()->getString('_token'))) {
            $login->setIsEnabled(!$login->isEnabled());
            $status = $login->isEnabled() ? 'enabled' : 'disabled';
            $userId = $login->getId();
            
            $entityManager->flush();
            
            // Manually log the status change
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('UPDATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($userId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log user status change: ' . $e->getMessage());
            }

            $this->addFlash('success', "User account has been {$status} successfully!");
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Login $login,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$login->getId(), $request->getPayload()->getString('_token'))) {
            $userId = $login->getId();
            $username = $login->getUserIdentifier();

            // Detach ownership links first to avoid FK violations on delete.
            $entityManager->createQueryBuilder()
                ->update(Payment::class, 'p')
                ->set('p.createdBy', ':nullUser')
                ->where('p.createdBy = :user')
                ->setParameter('nullUser', null)
                ->setParameter('user', $login)
                ->getQuery()
                ->execute();

            $entityManager->createQueryBuilder()
                ->update(Booking::class, 'b')
                ->set('b.createdBy', ':nullUser')
                ->where('b.createdBy = :user')
                ->setParameter('nullUser', null)
                ->setParameter('user', $login)
                ->getQuery()
                ->execute();

            $entityManager->createQueryBuilder()
                ->update(CarInventory::class, 'c')
                ->set('c.createdBy', ':nullUser')
                ->where('c.createdBy = :user')
                ->setParameter('nullUser', null)
                ->setParameter('user', $login)
                ->getQuery()
                ->execute();
            
            $entityManager->remove($login);
            $entityManager->flush();
            
            // Manually log the deletion
            try {
                $user = $this->getUser();
                $currentUsername = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($currentUsername);
                $log->setRole($role);
                $log->setAction('DELETE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($userId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log user deletion: ' . $e->getMessage());
            }
            
            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
