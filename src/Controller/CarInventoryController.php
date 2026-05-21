<?php

namespace App\Controller;

use App\Entity\CarInventory;
use App\Entity\Login;
use App\Entity\ActivityLog;
use App\Form\CarInventoryType;
use App\Repository\BookingRepository;
use App\Repository\CarInventoryRepository;
use App\Repository\CarReviewRepository;
use App\Service\CarPhotoUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/car/inventory')]
final class CarInventoryController extends AbstractController
{
    public function __construct(
        private readonly CarPhotoUploadService $photoUploadService,
    ) {
    }

    #[Route(name: 'app_car_inventory_index', methods: ['GET'])]
    public function index(CarInventoryRepository $carInventoryRepository, BookingRepository $bookingRepository): Response
    {
        $carInventories = $carInventoryRepository->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->setMaxResults(1000)
            ->getResult();

        $carIds = array_map(static fn (CarInventory $c) => $c->getId(), $carInventories);
        $bookingCounts = $bookingRepository->countByCarIds($carIds);

        $hasPhoto = [];
        foreach ($carInventories as $car) {
            $hasPhoto[$car->getId()] = $this->photoUploadService->hasUploadedPhoto($car);
        }

        return $this->render('car_inventory/index.html.twig', [
            'car_inventories' => $carInventories,
            'booking_counts' => $bookingCounts,
            'has_photo' => $hasPhoto,
        ]);
    }

    #[Route('/new', name: 'app_car_inventory_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $carInventory = new CarInventory();
        $form = $this->createForm(CarInventoryType::class, $carInventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUser = $this->getUser();
            if ($currentUser instanceof Login) {
                $loginEntity = $entityManager->getRepository(Login::class)->find($currentUser->getId());
                $carInventory->setCreatedBy($loginEntity ?: $currentUser);
            }

            $entityManager->persist($carInventory);
            $entityManager->flush();

            $this->processPhotoUpload($form, $carInventory);

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
                $log->setEntityType('Car Inventory');
                $log->setEntityId($carInventory->getId());

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log car creation: '.$e->getMessage());
            }

            $this->addFlash('success', 'Car created successfully.');

            return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('car_inventory/new.html.twig', [
            'car_inventory' => $carInventory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/photo', name: 'app_car_inventory_photo', methods: ['GET'])]
    public function photo(CarInventory $carInventory): Response
    {
        return $this->render('car_inventory/photo.html.twig', [
            'car_inventory' => $carInventory,
            'image_url' => $this->photoUploadService->resolvePublicUrl($carInventory),
            'has_uploaded_photo' => $this->photoUploadService->hasUploadedPhoto($carInventory),
        ]);
    }

    #[Route('/{id}/photo/remove', name: 'app_car_inventory_photo_remove', methods: ['POST'])]
    public function removePhoto(Request $request, CarInventory $carInventory): Response
    {
        if (!$this->isCsrfTokenValid('remove_photo'.$carInventory->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->photoUploadService->removePhoto($carInventory)) {
            $this->addFlash('success', 'Vehicle photo removed.');
        } else {
            $this->addFlash('warning', 'No custom photo to remove.');
        }

        return match ($request->request->getString('redirect')) {
            'edit' => $this->redirectToRoute('app_car_inventory_edit', ['id' => $carInventory->getId()], Response::HTTP_SEE_OTHER),
            'index' => $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER),
            default => $this->redirectToRoute('app_car_inventory_photo', ['id' => $carInventory->getId()], Response::HTTP_SEE_OTHER),
        };
    }

    #[Route('/{id}', name: 'app_car_inventory_show', methods: ['GET'])]
    public function show(CarInventory $carInventory, BookingRepository $bookingRepository, CarReviewRepository $carReviewRepository): Response
    {
        $carId = $carInventory->getId();

        return $this->render('car_inventory/show.html.twig', [
            'car_inventory' => $carInventory,
            'car_has_bookings' => $bookingRepository->countByCarId($carId) > 0,
            'review_summary' => $carReviewRepository->getSummaryForCar($carId),
            'customer_reviews' => $carReviewRepository->findForCarAdmin($carId),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_car_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CarInventory $carInventory, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): Response
    {
        $form = $this->createForm(CarInventoryType::class, $carInventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $carId = $carInventory->getId();
            $entityManager->flush();

            $this->processPhotoUpload($form, $carInventory);

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
                $log->setEntityType('Car Inventory');
                $log->setEntityId($carId);

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log car update: '.$e->getMessage());
            }

            $this->addFlash('success', 'Car updated successfully.');

            return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('car_inventory/edit.html.twig', [
            'car_inventory' => $carInventory,
            'form' => $form,
            'car_has_bookings' => $bookingRepository->countByCarId($carInventory->getId()) > 0,
            'car_image_url' => $this->photoUploadService->resolvePublicUrl($carInventory),
            'has_uploaded_photo' => $this->photoUploadService->hasUploadedPhoto($carInventory),
        ]);
    }

    #[Route('/{id}', name: 'app_car_inventory_delete', methods: ['POST'])]
    public function delete(Request $request, CarInventory $carInventory, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            if (!$carInventory->getCreatedBy() || $carInventory->getCreatedBy()->getId() !== $currentUser->getId()) {
                $this->addFlash('error', 'Access Denied');

                return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        if ($this->isCsrfTokenValid('delete'.$carInventory->getId(), $request->getPayload()->getString('_token'))) {
            if ($bookingRepository->countByCarId($carInventory->getId()) > 0) {
                $this->addFlash('error', 'This car is booked and cannot be deleted. Remove or reassign the bookings first.');

                return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
            }

            $carId = $carInventory->getId();

            $this->photoUploadService->removePhoto($carInventory);

            $entityManager->remove($carInventory);
            $entityManager->flush();

            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';

                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('DELETE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('Car Inventory');
                $log->setEntityId($carId);

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log car deletion: '.$e->getMessage());
            }

            $this->addFlash('success', 'Car deleted successfully!');
        }

        return $this->redirectToRoute('app_car_inventory_index', [], Response::HTTP_SEE_OTHER);
    }

    private function processPhotoUpload(FormInterface $form, CarInventory $car): void
    {
        $file = $form->get('photoFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        try {
            $this->photoUploadService->upload($car, $file);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Photo could not be saved: '.$e->getMessage());
        }
    }
}
