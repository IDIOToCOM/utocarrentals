<?php

namespace App\Controller;

use App\Entity\Login;
use App\Service\CarReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CarReviewController extends AbstractController
{
    public function __construct(
        private readonly CarReviewService $reviewService,
    ) {
    }

    #[Route('/cars/{id}/review', name: 'app_car_review_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submit(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $customer = $this->requireCustomer();

        if (!$this->isCsrfTokenValid('car_review_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $rating = (int) $request->request->get('rating');
        $comment = $request->request->get('comment');
        $comment = \is_string($comment) ? $comment : null;

        try {
            $result = $this->reviewService->submitReview($customer, $id, $rating, $comment, $em);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_car_show', ['id' => $id]);
        }

        if ($result === 'created') {
            $this->addFlash('success', 'Thank you! Your review was posted for other renters to see.');
        } else {
            $this->addFlash('success', 'Your review was updated.');
        }

        return $this->redirectToRoute('app_car_show', ['id' => $id]);
    }

    private function requireCustomer(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException();
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_STAFF', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Sign in with a customer account to leave a review.');
        }

        return $user;
    }
}
