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

/**
 * Handles the linking of invoices to newly created or updated payments (Receipts)
 */
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
        $invoices = [];
        foreach ($relatedInvoices as $relatedInvoice) {
            $invoice    = $this->invoiceRepository->findOneBy(['uuid' => $relatedInvoice['idDocumento']]);
            $invoices[] = $invoice;
            $payment->addInvoice($invoice);
        }

        // if all paid invoices are already linked to the same transaction, we link the payment to that transaction
        $transaction  = null;
        $invoiceTotal = 0;
        foreach ($invoices as $invoice) {
            $_transaction = $invoice->getDocument()?->getTransaction();

            // If first iteration or same transaction, add amount and continue
            if ($transaction === null or ($_transaction && $_transaction->getId() === $transaction->getId())) {
                $transaction  = $_transaction;
                $invoiceTotal += $invoice->getAmount();
                continue;
            }

            $transaction = null;
            break;
        }

        // create a document for the payment and associate it with the transaction
        try {
            if (null !== $transaction) {
                if ($invoiceTotal !== $amount) {
                    throw new Exception(sprintf('The sum of all invoice amounts does not match the payment amount: %.2f vs %.2f',
                        $invoiceTotal / 100,
                        $amount / 100
                    ));
                }
                $this->statementService->linkInvoice($transaction, $payment);
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }
}