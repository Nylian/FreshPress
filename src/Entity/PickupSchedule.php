<?php

namespace App\Entity;

use App\Repository\PickupScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PickupScheduleRepository::class)]
class PickupSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pickupSchedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(inversedBy: 'pickupSchedules')]
    private ?LaundryTransaction $laundryTransaction = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $pickupDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $pickupTime = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getLaundryTransaction(): ?LaundryTransaction
    {
        return $this->laundryTransaction;
    }

    public function setLaundryTransaction(?LaundryTransaction $laundryTransaction): static
    {
        $this->laundryTransaction = $laundryTransaction;

        return $this;
    }

    public function getPickupDate(): ?\DateTimeImmutable
    {
        return $this->pickupDate;
    }

    public function setPickupDate(\DateTimeImmutable $pickupDate): static
    {
        $this->pickupDate = $pickupDate;

        return $this;
    }

    public function getPickupTime(): ?\DateTimeImmutable
    {
        return $this->pickupTime;
    }

    public function setPickupTime(\DateTimeImmutable $pickupTime): static
    {
        $this->pickupTime = $pickupTime;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
