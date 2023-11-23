<?php

namespace App\Twig;

use App\Entity\InvoiceTask;
use App\Entity\Statement;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use App\Entity\Transaction;

class StatusLabelExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('invoice_status', [$this, 'invoiceStatus'], ['is_safe' => ['html']]),
            new TwigFilter('invoice_task_status', [$this, 'invoiceTaskStatus'], ['is_safe' => ['html']]),
            new TwigFilter('livemode_status', [$this, 'liveModeStatus'], ['is_safe' => ['html']]),
            new TwigFilter('statement_status', [$this, 'statementStatus'], ['is_safe' => ['html']]),
            new TwigFilter('transaction_status', [$this, 'transactionStatus'], ['is_safe' => ['html']])
        ];
    }

    public function getFunctions(): array
    {
        return [];
    }

    public function invoiceStatus(?string $status): string
    {
        $type = match (strtolower($status)) {
            'vigente'   => 'success',
            'cancelado' => 'danger',
            default     => 'default',
        };

        return $this->renderStatus($status, $type);
    }

    public function invoiceTaskStatus(?string $status): string
    {
        $type = match ($status) {
            InvoiceTask::STATUS_COMPLETED => 'success',
            InvoiceTask::STATUS_PENDING   => 'warning',
            InvoiceTask::STATUS_FAILED    => 'danger',
            default                       => 'default',
        };

        return $this->renderStatus($status, $type);
    }

    public function liveModeStatus(?string $status): string
    {
        return $this->renderStatus($status ? 'live' : 'debug', $status ? 'success' : 'danger');
    }

    public function statementStatus(?string $status): string
    {
        $type = match ($status) {
            Statement::STATUS_PENDING      => 'warning',
            Statement::STATUS_CONSOLIDATED => 'success',
            default                        => 'default',
        };

        return $this->renderStatus($status, $type);
    }

    public function transactionStatus(?string $status): string
    {
        $type = match ($status) {
            Transaction::STATUS_PENDING         => 'warning',
            Transaction::STATUS_CONSOLIDATED    => 'success',
            Transaction::STATUS_AMOUNT_MISMATCH => 'danger',
            default                             => 'default',
        };

        return $this->renderStatus($status, $type);
    }

    private function renderStatus(?string $status, string $type): string
    {
        return sprintf('<span class="badge badge-%s">%s</span>',
            $type,
            $status
        );
    }
}
