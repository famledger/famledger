<?php

namespace App\Constant;

enum DocumentType: string
{
    case ACCOUNT_STATEMENT = 'account-statement';
    case ATTACHMENT = 'attachment';
    case EXPENSE = 'expense';
    case INCOME = 'income';
    case PAYMENT = 'payment'; // used for "recibos de pago"
    case ANNOTATION = 'annotation';
    case TAX = 'tax';
    case TAX_NOTICE = 'tax-notice';

    public static function getPriority(DocumentType $type): int
    {
        return match ($type) {
            self::ACCOUNT_STATEMENT => 1,
            self::TAX               => 2,
            self::TAX_NOTICE        => 3,
            self::EXPENSE           => 4,
            self::ATTACHMENT        => 5,
            self::INCOME            => 6,
            self::ANNOTATION        => 7,
            default                 => 99,
        };
    }

    static public function getOptions(): array
    {
        return [
            self::ACCOUNT_STATEMENT->value => self::ACCOUNT_STATEMENT->value,
            self::ATTACHMENT->value        => self::ATTACHMENT->value,
            self::EXPENSE->value           => self::EXPENSE->value,
            self::INCOME->value            => self::INCOME->value,
            self::ANNOTATION->value        => self::ANNOTATION->value,
            self::TAX->value               => self::TAX->value,
            self::TAX_NOTICE->value        => self::TAX_NOTICE->value,
        ];
    }
}