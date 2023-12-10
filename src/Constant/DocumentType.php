<?php

namespace App\Constant;

enum DocumentType: string
{
    case ACCOUNT_STATEMENT = 'account-statement';
    case ATTACHMENT = 'attachment';
    case EXPENSE = 'expense';
    case INCOME = 'income';
    case PAYMENT = 'payment';
    case ANNOTATION = 'annotation';
    case TAX = 'tax';

    public static function getPriority(DocumentType $type): int
    {
        return match ($type) {
            self::ACCOUNT_STATEMENT => 1,
            self::EXPENSE           => 2,
            self::ATTACHMENT        => 3,
            self::INCOME            => 4,
            self::ANNOTATION        => 5,
            self::TAX               => 6,
            default                 => 7,
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
        ];
    }
}