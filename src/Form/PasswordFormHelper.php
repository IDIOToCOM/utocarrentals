<?php

namespace App\Form;

use Symfony\Component\Form\FormInterface;

final class PasswordFormHelper
{
    /**
     * Read submitted plain password from a RepeatedType field (e.g. ChangePasswordType).
     */
    public static function getPlainPassword(FormInterface $form, string $fieldName = 'plainPassword'): string
    {
        if (!$form->has($fieldName)) {
            return '';
        }

        $field = $form->get($fieldName);
        if ($field->has('first')) {
            $first = $field->get('first')->getData();
            if (\is_string($first) && $first !== '') {
                return $first;
            }
        }

        $data = $field->getData();

        return \is_string($data) ? $data : '';
    }
}
