<?php

namespace App\Entity;

use App\Repository\SongRepository;
use App\Traits\StatisticsPropertiesTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: SongRepository::class)]
class Song
{

    use StatisticsPropertiesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['song'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['song'])]
    #[Assert\Length(
        min: 3,
        max: 5,
        minMessage: 'Le nom de ton son doit est plus long que  {{ limit }} characters pelo !',
        maxMessage: 'Your first name cannot be longer than {{ limit }} characters',
    )]
    private ?string $name = null;

    #[ORM\Column(length: 55)]
    #[Groups(['song'])]
    private ?string $artiste = null;

    /**
     * @var Collection<int, Pool>
     */
    #[Groups(['song', 'tata'])]
    #[ORM\ManyToMany(targetEntity: Pool::class, inversedBy: 'songs')]
    private Collection $pools;

    public function __construct()
    {
        $this->pools = new ArrayCollection();
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

    public function getArtiste(): ?string
    {
        return $this->artiste;
    }

    public function setArtiste(string $artiste): static
    {
        $this->artiste = $artiste;

        return $this;
    }

    /**
     * @return Collection<int, Pool>
     */
    public function getPools(): Collection
    {
        return $this->pools;
    }

    public function addPool(Pool $pool): static
    {
        if (!$this->pools->contains($pool)) {
            $this->pools->add($pool);
        }

        return $this;
    }

    public function removePool(Pool $pool): static
    {
        $this->pools->removeElement($pool);

        return $this;
    }

}
