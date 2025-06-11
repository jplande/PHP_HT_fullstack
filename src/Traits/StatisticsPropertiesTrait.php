<?php
namespace App\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait StatisticsPropertiesTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['stats'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['stats'])]
    private ?\DateTimeInterface $updatedAt = null;

    // CORRIGÉ : nullable: false et valeur par défaut
    #[ORM\Column(length: 10, nullable: false)]
    #[Groups(['stats'])]
    private string $status = 'active';

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\PrePersist]
    public function intializeTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->setCreatedAt(new \DateTime());
        }
        $this->setUpdatedAt(new \DateTime());

        // Initialiser le status s'il n'est pas défini
        if (empty($this->status)) {
            $this->setStatus('active');
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->setUpdatedAt(new \DateTime());
    }
}
