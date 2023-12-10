<?php

namespace App\EventListener;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

use App\Entity\Receipt;
use App\Event\Invoice\InvoiceUpdatedEvent;
use App\Repository\InvoiceRepository;
use App\Service\StatementService;

#[AsEventListener(event: InvoiceUpdatedEvent::class, method: 'onInvoiceUpdated')]
class PaymentEventListener
{
    public function __construct(
        private readonly LoggerInterface   $logger,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly StatementService  $statementService,
    ) {
    }

    public function onInvoiceUpdated(InvoiceUpdatedEvent $event): void
    {
        $payment = $event->getInvoice();
        if (!$payment instanceof Receipt) {
            return;
        }
        $peticion = json_decode($payment->getData('peticion'), true);
        $pago     = $peticion['CFDi']['ComplementoPago'][0]['Pago'][0]
                    ?? $peticion['CFDi']['ComplementoPagos'][0]['Pagos'][0]
                       ?? null;
        if (null === $pago) {
            return;
        }

        $relatedInvoices = $pago['DocumentosRelacionados'];
        $amount          = $pago['monto'];

        $payment->setAmount($amount * 100);
        $transactions = [];
        $invoices     = [];
        $invoiceTotal = 0;
        foreach ($relatedInvoices as $relatedInvoice) {
            $series = $relatedInvoice['serie'];
            $number = $relatedInvoice['folioInterno'];

            $invoice    = $this->invoiceRepository->findOneBy(['series' => $series, 'number' => $number]);
            $invoices[] = $invoice;
            $payment->addInvoice($invoice);
            if (null !== ($transaction = $invoice->getDocument()?->getTransaction())) {
                $transactions[] = $transaction;
                $invoiceTotal   += $invoice->getAmount();
            }
        }

        // create a document for the payment and associate it with the transaction
        try {
            $this->validate($transactions, $invoices, (int)($amount * 100), $invoiceTotal);
            $this->statementService->linkInvoice($transactions[0], $payment);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function validate(array $transactions, array $invoices, int $amount, int $invoiceTotal): void
    {
        // we cannot link the payment to a transaction

        // - if the invoices to not all belong to the same transaction
        $transactionIds = array_unique(array_map(fn($transaction) => $transaction->getId(), $transactions));
        if (1 !== count($transactionIds)) {
            throw new Exception(
                'The invoices referenced in the payment do not belong to the same transaction.'
                . $this->getInvoiceInfo($transactions, $invoices)
            );
        }

        // - not all invoices have a transaction
        if (count($transactions) !== count($invoices)) {
            throw new Exception(
                'Not all invoices are associated with a transaction.'
                . $this->getInvoiceInfo($transactions, $invoices)
            );
        }

        // - if the total amount of the invoices does not match the payment amount
        if (abs($invoiceTotal - $amount) > 1) {
            throw new Exception(sprintf('The sum of all invoice amounts does not match the payment amount: %.2f vs %.2f',
                $invoiceTotal / 100,
                $amount / 100
            ));
        }
    }

    private function getInvoiceInfo(array $transactions, array $invoices): string
    {
        $transactionInfo = join(' | ', array_map(fn($transaction) => sprintf('%s: sequence %s',
            $transaction->getStatement(),
            $transaction->getSequenceNo()
        ), $transactions));

        $invoiceInfo = join(' | ', array_map(fn($invoice) => sprintf('%s-%s',
            $invoice->getSeries(),
            $invoice->getNumber()
        ), $invoices));

        return "\nInvoices: $invoiceInfo\nTransactions: $transactionInfo";
    }
}