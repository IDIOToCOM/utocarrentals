<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\CarInventory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Payment Name',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a payment name',
                    ]),
                ],
            ])
            ->add('car', EntityType::class, [
                'class' => CarInventory::class,
                'choice_label' => function (CarInventory $car) {
                    return $car->getBrand() . ' ' . $car->getModel() . ' (ID: ' . $car->getId() . ')';
                },
                'label' => 'Car',
                'placeholder' => 'Select a car (optional)',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Completed' => 'Completed',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                ],
                'label' => 'Status',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a status',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
