<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use App\Entity\Email;

class EmailCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Email::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('email');
        yield ChoiceField::new('category')
            ->setChoices([
                'invoicing' => 'invoicing',
                'other'     => 'other',
            ]);;
        yield TextField::new('description');
    }
}
