<?php

namespace App\Entity;

use App\Repository\LaundryTransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LaundryTransactionRepository::class)]
class LaundryTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $transactionNumber = null;

    #[ORM\Column(length: 100)]
    private ?string $serviceType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $weight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $pricePerKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateReceived = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $expectedPickupDate = null;

    #[ORM\ManyToOne(inversedBy: 'laundryTransactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'laundryTransaction')]
    private Collection $payments;

    /**
     * @var Collection<int, PickupSchedule>
     */
    #[ORM\OneToMany(targetEntity: PickupSchedule::class, mappedBy: 'laundryTransaction')]
    private Collection $pickupSchedules;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->pickupSchedules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionNumber(): ?string
    {
        return $this->transactionNumber;
    }

    public function setTransactionNumber(string $transactionNumber): static
    {
        $this->transactionNumber = $transactionNumber;

        return $this;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): static
    {
        $this->serviceType = $serviceType;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(string $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getPricePerKg(): ?string
    {
        return $this->pricePerKg;
    }

    public function setPricePerKg(string $pricePerKg): static
    {
        $this->pricePerKg = $pricePerKg;

        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

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

    public function getDateReceived(): ?\DateTimeImmutable
    {
        return $this->dateReceived;
    }

    public function setDateReceived(\DateTimeImmutable $dateReceived): static
    {
        $this->dateReceived = $dateReceived;

        return $this;
    }

    public function getExpectedPickupDate(): ?\DateTimeImmutable
    {
        return $this->expectedPickupDate;
    }

    public function setExpectedPickupDate(\DateTimeImmutable $expectedPickupDate): static
    {
        $this->expectedPickupDate = $expectedPickupDate;

        return $this;
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

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setLaundryTransaction($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getLaundryTransaction() === $this) {
                $payment->setLaundryTransaction(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PickupSchedule>
     */
    public function getPickupSchedules(): Collection
    {
        return $this->pickupSchedules;
    }

    public function addPickupSchedule(PickupSchedule $pickupSchedule): static
    {
        if (!$this->pickupSchedules->contains($pickupSchedule)) {
            $this->pickupSchedules->add($pickupSchedule);
            $pickupSchedule->setLaundryTransaction($this);
        }

        return $this;
    }

    public function removePickupSchedule(PickupSchedule $pickupSchedule): static
    {
        if ($this->pickupSchedules->removeElement($pickupSchedule)) {
            // set the owning side to null (unless already changed)
            if ($pickupSchedule->getLaundryTransaction() === $this) {
                $pickupSchedule->setLaundryTransaction(null);
            }
        }

        return $this;
    }
}
