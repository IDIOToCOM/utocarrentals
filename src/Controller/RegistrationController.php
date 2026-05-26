<?php

namespace App\Controller;

use App\Entity\Login;
use App\Entity\ActivityLog;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_car_catalog');
        }

        $user = new Login();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();
            
            // Manually log the user creation
            try {
                $log = new ActivityLog();
                $log->setUser($user->getUserIdentifier());
                $log->setRole('ROLE_USER');
                $log->setAction('CREATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('User');
                $log->setEntityId($user->getId());
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log user registration: ' . $e->getMessage());
            }

            $verificationUrl = $urlGenerator->generate(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            if ($emailVerificationService->sendVerificationEmail($user, $verificationUrl)) {
                $this->addFlash('success', 'Registration successful. Please verify your email before signing in. Check your inbox for the verification link.');
            } else {
                $this->addFlash('error', 'Registration succeeded, but we could not send the verification email right now. Please contact support or try again later.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
