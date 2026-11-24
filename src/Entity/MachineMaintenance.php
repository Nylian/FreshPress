<?php

namespace App\Entity;

use App\Repository\MachineMaintenanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineMaintenanceRepository::class)]
class MachineMaintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $machineName = null;

    #[ORM\Column(length: 100)]
    private ?string $maintenanceType = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $maintenanceDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextMaintenanceDate = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMachineName(): ?string
    {
        return $this->machineName;
    }

    public function setMachineName(string $machineName): static
    {
        $this->machineName = $machineName;

        return $this;
    }

    public function getMaintenanceType(): ?string
    {
        return $this->maintenanceType;
    }

    public function setMaintenanceType(string $maintenanceType): static
    {
        $this->maintenanceType = $maintenanceType;

        return $this;
    }

    public function getMaintenanceDate(): ?\DateTimeImmutable
    {
        return $this->maintenanceDate;
    }

    public function setMaintenanceDate(\DateTimeImmutable $maintenanceDate): static
    {
        $this->maintenanceDate = $maintenanceDate;

        return $this;
    }

    public function getNextMaintenanceDate(): ?\DateTimeImmutable
    {
        return $this->nextMaintenanceDate;
    }

    public function setNextMaintenanceDate(?\DateTimeImmutable $nextMaintenanceDate): static
    {
        $this->nextMaintenanceDate = $nextMaintenanceDate;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
