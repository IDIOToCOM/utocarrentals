<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Login;
use App\Form\ChangePasswordType;
use App\Form\PasswordFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Login $user */
        $user = $this->getUser();
        
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException('You must be logged in to change your password.');
        }
        
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = PasswordFormHelper::getPlainPassword($form);
            if ($plainPassword === '') {
                $this->addFlash('error', 'Please enter a new password.');

                return $this->render('profile/change_password.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $entityManager->persist($user);
            $entityManager->flush();
            try {
                $actor = $this->getUser();
                $username = $actor ? $actor->getUserIdentifier() : 'System';
                $roles = $actor ? $actor->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';

                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('UPDATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($user->getId());

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log profile password change: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Password changed successfully!');
            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/change_password.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}

