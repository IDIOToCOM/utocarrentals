<?php

namespace App\Form;

use App\Car\CarFleetStatus;
use App\Car\VehicleTypeChoices;
use App\Entity\CarInventory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Validator\Constraints\SafeImageFile;

class CarInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Brand', null, [
                'required' => true,
                'attr' => ['required' => 'required', 'class' => 'form-control'],
            ])
            ->add('Model', null, [
                'required' => true,
                'attr' => ['required' => 'required', 'class' => 'form-control'],
            ])
            ->add('Type', ChoiceType::class, [
                'choices' => VehicleTypeChoices::forForm(),
                'placeholder' => 'Select type',
                'required' => true,
                'attr' => ['class' => 'form-control', 'required' => 'required'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Features, comfort, ideal use — shown to customers browsing vehicles.',
                ],
            ])
            ->add('transmission', ChoiceType::class, [
                'label' => 'Transmission',
                'choices' => [
                    'Automatic' => 'Automatic',
                    'Manual' => 'Manual',
                ],
                'placeholder' => 'Select transmission',
                'required' => true,
                'attr' => ['class' => 'form-control', 'required' => 'required'],
            ])
            ->add('passengerSeats', IntegerType::class, [
                'label' => 'Passenger capacity',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'required' => 'required',
                    'min' => 1,
                    'max' => 99,
                    'placeholder' => 'e.g. 5',
                ],
            ])
            ->add('PricePerDay', null, [
                'required' => true,
                'attr' => ['required' => 'required', 'class' => 'form-control'],
            ])
            ->add('Status', ChoiceType::class, [
                'label' => 'Fleet status',
                'choices' => CarFleetStatus::forForm(),
                'required' => true,
                'help' => 'Bookings control which dates are taken. Use Out of service only to hide this vehicle from the customer site (maintenance, sold, etc.).',
                'attr' => ['class' => 'form-control', 'required' => 'required'],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Vehicle photo',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
                ],
                'constraints' => [
                    new SafeImageFile(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CarInventory::class,
        ]);
    }
}
