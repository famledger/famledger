<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Address;
use App\Entity\Customer;

class AddressService
{
    public function __construct(
        public readonly EntityManagerInterface $em,
    ) {
    }

    public function createAddressIfNotExists(array $domicilioFiscal, Customer $customer): ?Address
    {
        $checksum = $this->getChecksum($domicilioFiscal);
        if (null !== $this->em->getRepository(Address::class)->findOneBy(['checksum' => $checksum])) {
            return null;
        }

        return $this->createAddress($domicilioFiscal, $customer)
            ->setChecksum($checksum);
    }

    public function createAddress(mixed $domicilioFiscal, ?Customer $customer): Address
    {
        $identifier = sprintf('%s-%s', $customer->getRfc(), $this->getChecksum($domicilioFiscal));
        $address    = (new Address())
            ->setIdentifier($identifier)
            ->setCalle($domicilioFiscal['calle'])
            ->setCP($domicilioFiscal['cp'])
            ->setColonia($domicilioFiscal['colonia'] ?? '')
            ->setEstado($domicilioFiscal['estado'])
            ->setMunicipio($domicilioFiscal['municipio'])
            ->setNoExterior($domicilioFiscal['noExterior'])
            ->setNoInterior($domicilioFiscal['noInterior'])
            ->setPais($domicilioFiscal['pais'])
            ->setCustomer($customer);

        $this->em->persist($address);

        return $address;
    }

    public function getChecksum(mixed $domicilioFiscal): string
    {
        ksort($domicilioFiscal);

        return hash('sha256', json_encode($domicilioFiscal));
    }
}