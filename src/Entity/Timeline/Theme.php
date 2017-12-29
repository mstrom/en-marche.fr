<?php

namespace AppBundle\Entity\Timeline;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use AppBundle\Entity\EntityMediaInterface;
use AppBundle\Entity\EntityMediaTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="timeline_themes")
 * @ORM\Entity
 */
class Theme implements EntityMediaInterface
{
    use EntityMediaTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100)
     *
     * @Assert\Length(max=100)
     * @Assert\NotBlank
     *
     * @Algolia\Attribute
     */
    private $title;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100, unique=true)
     *
     * @Assert\Length(max=100)
     * @Assert\NotBlank
     *
     * @Algolia\Attribute
     */
    private $slug;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text")
     *
     * @Assert\NotBlank
     *
     * @Algolia\Attribute
     */
    private $description;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $featured = false;

    /**
     * @var ThemeMeasure[]|Collection
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Timeline\ThemeMeasure", mappedBy="theme", cascade={"all"})
     *
     * @Algolia\Attribute
     */
    private $measures;

    public function __construct()
    {
        $this->measures = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title ?: '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): void
    {
        $this->featured = $featured;
    }

    /**
     * @Algolia\Attribute
     */
    public function isFeatured(): ?string
    {
        return true === $this->featured ? $this->title : null;
    }

    public function getMeasures(): Collection
    {
        return $this->measures;
    }

    public function addMeasure(ThemeMeasure $measure): void
    {
        if (!$this->measures->contains($measure)) {
            $measure->setTheme($this);
            $this->measures->add($measure);
        }
    }

    public function removeMeasure(ThemeMeasure $measure): void
    {
        $this->measures->removeElement($measure);
    }

    public function setFeaturedMeasure(Measure $measure): void
    {
        foreach ($this->measures as $themeMeasure) {
            if ($themeMeasure->getMeasure()->getTitle() === $measure->getTitle()) {
                $themeMeasure->setFeatured(true);
                return;
            }
        }

        throw new \InvalidArgumentException(sprintf(
           'Theme "%s" has no measure "%s".',
           $this->title,
           $measure->getTitle()
        ));
    }
}
