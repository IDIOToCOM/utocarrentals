<?php

namespace App\Service;

use App\Booking\BookingStatus;
use App\Car\CarReviewSummary;
use App\Entity\CarInventory;
use App\Entity\CarReview;
use App\Entity\Login;
use App\Repository\BookingRepository;
use App\Repository\CarInventoryRepository;
use App\Repository\CarReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CarReviewService
{
    public function __construct(
        private readonly CarReviewRepository $reviewRepository,
        private readonly CarInventoryRepository $carInventoryRepository,
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    public function getSummaryForCar(int $carId): CarReviewSummary
    {
        return $this->reviewRepository->getSummaryForCar($carId);
    }

    /**
     * @param list<int> $carIds
     *
     * @return array<int, CarReviewSummary>
     */
    public function getSummariesForCarIds(array $carIds): array
    {
        return $this->reviewRepository->getSummariesForCarIds($carIds);
    }

    /**
     * @return list<CarReview>
     */
    public function getReviewsForCar(int $carId): array
    {
        return $this->reviewRepository->findForCarOrdered($carId);
    }

    public function findUserReview(Login $customer, int $carId): ?CarReview
    {
        return $this->reviewRepository->findOneByAuthorAndCar($customer->getId(), $carId);
    }

    public function canCustomerReviewCar(Login $customer, int $carId): bool
    {
        foreach ($this->bookingRepository->findForCustomer($customer) as $booking) {
            if ($booking->getCar()?->getId() !== $carId) {
                continue;
            }
            if ($booking->getStatus() === BookingStatus::CONFIRMED || $booking->getStatus() === BookingStatus::COMPLETED) {
                return true;
            }
        }

        return false;
    }

    public function canCustomerSubmitOrEdit(Login $customer, int $carId): bool
    {
        $existing = $this->findUserReview($customer, $carId);
        if ($existing !== null) {
            return true;
        }

        return $this->canCustomerReviewCar($customer, $carId);
    }

    /**
     * @return 'created'|'updated'
     */
    public function submitReview(Login $customer, int $carId, int $rating, ?string $comment, EntityManagerInterface $em): string
    {
        $car = $this->carInventoryRepository->findAvailableForCustomer($carId);
        if ($car === null) {
            throw new \InvalidArgumentException('This vehicle is not available for reviews.');
        }

        $existing = $this->findUserReview($customer, $carId);
        if ($existing === null && !$this->canCustomerReviewCar($customer, $carId)) {
            throw new \InvalidArgumentException('You can review a vehicle after you have a confirmed booking for it.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Please choose a rating from 1 to 5 stars.');
        }

        if ($existing instanceof CarReview) {
            $existing->setRating($rating);
            $existing->setComment($comment);
            $existing->touchUpdatedAt();
            $em->flush();

            return 'updated';
        }

        $review = new CarReview();
        $review->setAuthor($customer);
        $review->setCar($car);
        $review->setRating($rating);
        $review->setComment($comment);
        $em->persist($review);
        $em->flush();

        return 'created';
    }
}
