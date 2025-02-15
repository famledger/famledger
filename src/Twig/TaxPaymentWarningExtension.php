<?php

namespace App\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use App\Entity\TaxNotice;
use App\Entity\TaxPayment;

class TaxPaymentWarningExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tax_payment_warning', [$this, 'taxWarning'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @throws Exception
     */
    public function taxWarning(): string
    {
        // get the last tax payment
        /** @var TaxPayment $taxPayment */
        $taxPayment = $this->em->getRepository(TaxPayment::class)->findOneBy([], ['year' => 'DESC', 'month' => 'DESC']);
        $taxNotice  = $this->em->getRepository(TaxNotice::class)->findOneBy([], ['year' => 'DESC', 'month' => 'DESC']);

        $paymentMonth      = $taxPayment->getYear() . str_pad($taxPayment->getMonth(), 2, '0', STR_PAD_LEFT);
        $noticeMonth       = $taxNotice->getYear() . str_pad($taxNotice->getMonth(), 2, '0', STR_PAD_LEFT);
        $lastPaidMonth     = $paymentMonth;
        $isZeroDeclaration = false;
        if ($noticeMonth > $paymentMonth and $taxNotice->getAmount() === 0) {
            $lastPaidMonth     = $noticeMonth;
            $isZeroDeclaration = true;
        }

        // Get the date of the last tax payment composing it from month and year
        // Calculate the 15th of the month following the payment's month
        $paymentDate = new DateTime(substr($lastPaidMonth, 0, 4) . '-' . substr($lastPaidMonth, 4, 2) . '-01');
        $warningDate = (clone $paymentDate)->modify('+2 month')->modify('+7 days');
        $currentDate = new DateTime();

        // we distinguish 4 situations based on 2 parameters
        // - the payment date limit has not been exceeded
        // - the last tax payment was actually a zero declaration
        $paymentMessage     = sprintf('%s <strong>%s</strong>',
            $isZeroDeclaration
                ? 'A zero-declaration has been issued for '
                : 'The last tax payment has been issued for',
            $paymentDate->format('F Y')
        );
        $nextPaymentMessage = sprintf('The next tax payment is due for <strong>%s</strong>.',
            $warningDate->format('F d, Y')
        );

        $isExpired = ($currentDate > $warningDate);
        $class     = $isExpired ? 'alert-danger' : 'alert-success';
        $caption   = $isExpired ? 'Warning' : 'Info';

        // Check if the current date is after the warning date
        return <<<EOT
<div class="alert $class py-1 px-3 rounded">
    <h4 class="alert-heading">$caption</h4>
    <div>$paymentMessage<br/>$nextPaymentMessage</div>
</div>
EOT;
    }
}