<?php

namespace App\Command;

use App\Car\CarFleetStatus;
use App\Entity\CarInventory;
use App\Repository\CarInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-fleet-vehicles',
    description: 'Add the standard Uto fleet vehicles (skips any brand+model already in the database)',
)]
final class SeedFleetVehiclesCommand extends Command
{
    /**
     * @var list<array{
     *   brand: string,
     *   model: string,
     *   type: string,
     *   pricePerDay: int,
     *   description: string,
     *   transmission: string,
     *   passengerSeats: int,
     * }>
     */
    private const VEHICLES = [
        [
            'brand' => 'Toyota',
            'model' => 'Fortuner',
            'type' => 'SUV',
            'pricePerDay' => 5200,
            'description' => 'A spacious and powerful SUV perfect for family trips, out-of-town adventures, and business travel with premium comfort and reliability.',
            'transmission' => 'Automatic',
            'passengerSeats' => 7,
        ],
        [
            'brand' => 'Ford',
            'model' => 'Everest',
            'type' => 'SUV',
            'pricePerDay' => 4800,
            'description' => 'A stylish and rugged SUV that offers smooth driving, advanced safety features, and excellent performance on long drives.',
            'transmission' => 'Automatic',
            'passengerSeats' => 7,
        ],
        [
            'brand' => 'Toyota',
            'model' => 'Vios',
            'type' => 'Sedan',
            'pricePerDay' => 1800,
            'description' => 'A fuel-efficient and comfortable sedan ideal for city driving, daily rentals, and affordable travel.',
            'transmission' => 'Automatic',
            'passengerSeats' => 5,
        ],
        [
            'brand' => 'Mitsubishi',
            'model' => 'Montero Sport',
            'type' => 'SUV',
            'pricePerDay' => 4500,
            'description' => 'A modern SUV with strong road presence, spacious interiors, and a comfortable ride for groups or families.',
            'transmission' => 'Automatic',
            'passengerSeats' => 7,
        ],
        [
            'brand' => 'Honda',
            'model' => 'City',
            'type' => 'Sedan',
            'pricePerDay' => 2200,
            'description' => 'A sleek and reliable sedan known for its smooth handling, fuel economy, and comfortable interior.',
            'transmission' => 'Automatic',
            'passengerSeats' => 5,
        ],
        [
            'brand' => 'Ford',
            'model' => 'Mustang',
            'type' => 'Sports Car',
            'pricePerDay' => 8500,
            'description' => 'A legendary muscle car that delivers thrilling performance, bold styling, and an unforgettable driving experience.',
            'transmission' => 'Automatic',
            'passengerSeats' => 4,
        ],
        [
            'brand' => 'Toyota',
            'model' => 'Hiace',
            'type' => 'SUV',
            'pricePerDay' => 5500,
            'description' => 'A dependable passenger van perfect for group tours, airport transfers, and family outings with plenty of space.',
            'transmission' => 'Automatic',
            'passengerSeats' => 14,
        ],
        [
            'brand' => 'Toyota',
            'model' => 'Innova',
            'type' => 'SUV',
            'pricePerDay' => 3500,
            'description' => 'A versatile MPV that combines comfort, space, and reliability for family vacations and business trips.',
            'transmission' => 'Automatic',
            'passengerSeats' => 7,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CarInventoryRepository $carInventoryRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $added = 0;
        $skipped = 0;

        foreach (self::VEHICLES as $data) {
            if ($this->findExisting($data['brand'], $data['model']) !== null) {
                ++$skipped;
                $io->writeln(sprintf('  <comment>Skip</comment> %s %s (already exists)', $data['brand'], $data['model']));

                continue;
            }

            $car = new CarInventory();
            $car->setBrand($data['brand']);
            $car->setModel($data['model']);
            $car->setType($data['type']);
            $car->setPricePerDay($data['pricePerDay']);
            $car->setDescription($data['description']);
            $car->setTransmission($data['transmission']);
            $car->setPassengerSeats($data['passengerSeats']);
            $car->setStatus(CarFleetStatus::AVAILABLE);

            $this->entityManager->persist($car);
            ++$added;
            $io->writeln(sprintf(
                '  <info>Added</info> %s %s — ₱%s/day (%s, %d seats)',
                $data['brand'],
                $data['model'],
                number_format($data['pricePerDay']),
                $data['type'],
                $data['passengerSeats'],
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf('Fleet seed done: %d added, %d skipped.', $added, $skipped));

        if ($added > 0) {
            $io->note('Upload photos in Admin → Car inventory → Edit each vehicle. Until then, type placeholder images show on the customer site.');
        }

        return Command::SUCCESS;
    }

    private function findExisting(string $brand, string $model): ?CarInventory
    {
        return $this->carInventoryRepository->createQueryBuilder('c')
            ->andWhere('c.Brand = :brand')
            ->andWhere('c.Model = :model')
            ->setParameter('brand', $brand)
            ->setParameter('model', $model)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
