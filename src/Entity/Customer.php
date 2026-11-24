<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20)]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    /**
     * @var Collection<int, LaundryTransaction>
     */
    #[ORM\OneToMany(targetEntity: LaundryTransaction::class, mappedBy: 'customer')]
    private Collection $laundryTransactions;

    /**
     * @var Collection<int, PickupSchedule>
     */
    #[ORM\OneToMany(targetEntity: PickupSchedule::class, mappedBy: 'customer')]
    private Collection $pickupSchedules;

    public function __construct()
    {
        $this->laundryTransactions = new ArrayCollection();
        $this->pickupSchedules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection<int, LaundryTransaction>
     */
    public function getLaundryTransactions(): Collection
    {
        return $this->laundryTransactions;
    }

    public function addLaundryTransaction(LaundryTransaction $laundryTransaction): static
    {
        if (!$this->laundryTransactions->contains($laundryTransaction)) {
            $this->laundryTransactions->add($laundryTransaction);
            $laundryTransaction->setCustomer($this);
        }

        return $this;
    }

    public function removeLaundryTransaction(LaundryTransaction $laundryTransaction): static
    {
        if ($this->laundryTransactions->removeElement($laundryTransaction)) {
            // set the owning side to null (unless already changed)
            if ($laundryTransaction->getCustomer() === $this) {
                $laundryTransaction->setCustomer(null);
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
            $pickupSchedule->setCustomer($this);
        }

        return $this;
    }

    public function removePickupSchedule(PickupSchedule $pickupSchedule): static
    {
        if ($this->pickupSchedules->removeElement($pickupSchedule)) {
            // set the owning side to null (unless already changed)
            if ($pickupSchedule->getCustomer() === $this) {
                $pickupSchedule->setCustomer(null);
            }
        }

        return $this;
    }
}
