<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payment')]
final class ApiPaymentController extends AbstractController
{
    #[Route('', name: 'api_payment_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(PaymentRepository $paymentRepository): JsonResponse
    {
        $payments = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.car', 'car')
            ->addSelect('car')
            ->leftJoin('p.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(static function ($payment): array {
            return [
                'id' => $payment->getId(),
                'name' => $payment->getName(),
                'status' => $payment->getStatus(),
                'car' => $payment->getCar() ? [
                    'id' => $payment->getCar()->getId(),
                    'brand' => $payment->getCar()->getBrand(),
                    'model' => $payment->getCar()->getModel(),
                ] : null,
                'createdBy' => $payment->getCreatedBy()?->getUserIdentifier(),
            ];
        }, $payments);

        return $this->json($data);
    }
}
