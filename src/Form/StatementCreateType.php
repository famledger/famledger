<?php

namespace App\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotNull;

use App\Entity\Account;

class StatementCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', EntityType::class, [
                'placeholder' => '',
                'required'    => true,
                'class'       => Account::class,
                'mapped'      => false,
                'constraints' => [
                    new NotNull()
                ],
            ])
            ->add('file', FileType::class, [
                'mapped' => false,
            ]);
    }
}
