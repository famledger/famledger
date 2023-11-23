<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;

class InvoiceCancelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form    = $event->getForm();
            $invoice = $event->getData();

            if (null === $invoice->getSubstitutedByInvoice()) {
                $form
                    ->add('substitutedByInvoice', null, [
                        'label'         => 'Substituted by invoice',
                        'placeholder'   => '- select an invoice -',
                        'required'      => true,
                        'query_builder' => function (InvoiceRepository $er) use ($invoice) {
                            return $er->getSubstitutionInvoices($invoice);
                        },
                        'choice_label'  => function (?Invoice $invoice) {
                            return $invoice
                                ? sprintf('%s-%s | %s %s',
                                    $invoice->getSeries(),
                                    $invoice->getNumber(),
                                    $invoice->getMonth(),
                                    $invoice->getYear()
                                )
                                : '';
                        }
                    ]);
            } else {
                $form
                    ->add('substitutedByInvoice', HiddenType::class, [
                        'data' => $invoice->getSubstitutedByInvoice()->getId(),
                    ]);
            }
            $form
                ->add('cancellationReason', null, [
                    'required' => true,
                ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}
