<?php

namespace App\Form;

use App\Entity\CarInventory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Booking\BookingStatus;
use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('phone', null, [
                'required' => true,
                'label' => 'Phone number',
                'attr' => ['required' => 'required', 'placeholder' => 'e.g. 0917 123 4567'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => BookingStatus::forForm(),
                'required' => true,
                'attr' => ['class' => 'form-control', 'required' => 'required'],
            ])
            ->add('car', EntityType::class, [
                'class' => CarInventory::class,
                'choice_label' => function (CarInventory $car) {
                    return $car->getBrand() . ' ' . $car->getModel() . ' (ID: ' . $car->getId() . ')';
                },
                'label' => 'Car',
                'placeholder' => 'Select a car',
                'required' => true,
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.Brand', 'ASC')
                        ->addOrderBy('c.Model', 'ASC');
                },
                'attr' => ['class' => 'form-control', 'required' => 'required']
            ])
            ->add('pickupLocation', null, [
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('dropoffLocation', null, [
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('pickupDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('returnDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('pickupTime', TimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'required' => true,
                'attr' => ['required' => 'required']
            ])
            ->add('returnTime', TimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'required' => true,
                'attr' => ['required' => 'required']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
