<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Form\PasswordFormHelper;
use App\Form\ResetPasswordRequestType;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, PasswordResetService $passwordResetService): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
                $this->addFlash('info', 'You are already signed in. Change your password from your profile.');

                return $this->redirectToRoute('app_profile_change_password');
            }

            $this->addFlash('info', 'You are already signed in. Use the form below to set a new password.');

            return $this->redirectToRoute('app_customer_account_change_password');
        }

        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $passwordResetService->requestReset($email);

            return $this->redirectToRoute('app_forgot_password_check_email');
        }

        return $this->render('security/forgot_password_request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/forgot-password/check-email', name: 'app_forgot_password_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        if ($this->getUser()) {
            return $this->redirectAfterLogin();
        }

        return $this->render('security/forgot_password_check_email.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        PasswordResetService $passwordResetService,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $passwordResetService->findValidUserByToken($token);
        if ($user === null) {
            $this->addFlash('error', 'This password reset link is invalid or has expired. Please request a new one.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = PasswordFormHelper::getPlainPassword($form);
            if ($plainPassword === '') {
                $this->addFlash('error', 'Please enter a new password.');

                return $this->render('security/reset_password.html.twig', [
                    'resetForm' => $form,
                    'token' => $token,
                ]);
            }

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $passwordResetService->clearResetToken($user);
            $entityManager->persist($user);
            $entityManager->flush();

            $loginHint = $user->getUsername() ?? '';
            $this->addFlash(
                'success',
                'Your password has been reset. Sign in with username "'.$loginHint.'" or your email address.',
            );

            return $this->redirectToRoute('app_logout');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form,
            'token' => $token,
        ]);
    }

    private function redirectAfterLogin(): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_admin');
        }

        return $this->redirectToRoute('app_car_catalog');
    }
}
