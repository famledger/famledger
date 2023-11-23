<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;

class SubstituteInvoiceType extends AbstractType
{
    private InvoiceRepository $invoiceRepository;

    public function __construct(InvoiceRepository $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $series = $options['series'];

        $invoices = null === $series
            ? $this->invoiceRepository->findAll()
            : $this->invoiceRepository->findBy(['series' => $series]);

        $builder->add('invoice', ChoiceType::class, [
            'choices'      => $invoices,
            'choice_label' => function (Invoice $invoice) {
                return $invoice->__toString(); // Adjust this as needed
            },
            'choice_value' => 'id',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'series' => null,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
