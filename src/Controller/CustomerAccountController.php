<?php

namespace App\Controller;

use App\Entity\Login;
use App\Form\ChangePasswordType;
use App\Form\CustomerAccountType;
use App\Form\PasswordFormHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/account')]
final class CustomerAccountController extends AbstractController
{
    #[Route('', name: 'app_customer_account', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($response = $this->redirectStaffToProfile()) {
            return $response;
        }

        $user = $this->requireLogin();
        $form = $this->createForm(CustomerAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Your account details were saved.');

            return $this->redirectToRoute('app_customer_account', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'accountForm' => $form,
        ]);
    }

    #[Route('/change-password', name: 'app_customer_account_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($response = $this->redirectStaffToProfile()) {
            return $response;
        }

        $user = $this->requireLogin();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = PasswordFormHelper::getPlainPassword($form);
            if ($plainPassword === '') {
                $this->addFlash('error', 'Please enter a new password.');

                return $this->render('account/change_password.html.twig', [
                    'user' => $user,
                    'passwordForm' => $form,
                ]);
            }

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your password was updated.');

            return $this->redirectToRoute('app_customer_account', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/change_password.html.twig', [
            'user' => $user,
            'passwordForm' => $form,
        ]);
    }

    private function requireLogin(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function redirectStaffToProfile(): ?Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_profile');
        }

        return null;
    }
}
