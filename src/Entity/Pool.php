<?php

namespace App\Entity;

use App\Repository\PoolRepository;
use App\Traits\StatisticsPropertiesTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: PoolRepository::class)]
class Pool
{

    use StatisticsPropertiesTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["song"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["song"])]

    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(["song", "tata"])]
    private ?string $code = null;

    /**
     * @var Collection<int, Song>
     */
    // #[Groups(["tata"])]
    #[ORM\ManyToMany(targetEntity: Song::class, mappedBy: 'pools')]
    private Collection $songs;

    public function __construct()
    {
        $this->songs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection<int, Song>
     */
    public function getSongs(): Collection
    {
        return $this->songs;
    }

    public function addSong(Song $song): static
    {
        if (!$this->songs->contains($song)) {
            $this->songs->add($song);
            $song->addPool($this);
        }

        return $this;
    }

    public function removeSong(Song $song): static
    {
        if ($this->songs->removeElement($song)) {
            $song->removePool($this);
        }

        return $this;
    }
}
