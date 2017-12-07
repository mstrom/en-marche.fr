<?php

namespace AppBundle\Entity\Timeline;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="timeline_measures")
 * @ORM\Entity
 */
class Measure
{
    const TITLE_MAX_LENGTH = 100;

    const STATUS_UPCOMING = 'UPCOMING';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_DONE = 'DONE';

    const STATUSES = [
        'À venir' => self::STATUS_UPCOMING,
        'En cours' => self::STATUS_IN_PROGRESS,
        'Fait' => self::STATUS_DONE,
    ];

    /**
     * @var int
     *
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @Algolia\Attribute
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100)
     *
     * @Assert\Length(max = Measure::TITLE_MAX_LENGTH)
     * @Assert\NotBlank
     *
     * @Algolia\Attribute
     */
    private $title;

    /**
     * @var string|null
     *
     * @ORM\Column(nullable=true)
     *
     * @Assert\Url
     *
     * @Algolia\Attribute
     */
    private $link;

    /**
     * @var string|null
     *
     * @ORM\Column(length=50)
     *
     * @Assert\NotBlank
     * @Assert\Choice(
     *      callback={"AppBundle\Entity\Timeline\Measure", "getStatuses"},
     *      strict=true
     * )
     *
     * @Algolia\Attribute
     */
    private $status;

    /**
     * @var \DateTime|null
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updated;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $global = false;

    /**
     * @var Profile[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Timeline\Profile")
     * @ORM\JoinTable(
     *     name="timeline_measures_profiles",
     *     joinColumns={
     *         @ORM\JoinColumn(name="measure_id", referencedColumnName="id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="profile_id", referencedColumnName="id")
     *     }
     * )
     *
     * @Algolia\Attribute
     */
    private $profiles;

    public function __construct()
    {
        $this->profiles = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title ?: '';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    public function getUpdated(): ?\DateTime
    {
        return $this->updated;
    }

    /**
     * @Algolia\Attribute
     */
    public function getUpdatedAt(): ?string
    {
        if (!$this->updated) {
            return null;
        }

        return $this->updated->format('Y-m-d H:i:s');
    }

    public function getGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    /**
     * @Algolia\Attribute
     */
    public function isGlobal(): ?string
    {
        return true === $this->global ? $this->title : null;
    }

    public function getProfiles(): Collection
    {
        return $this->profiles;
    }

    public function addProfile(Profile $profile): void
    {
        if (!$this->profiles->contains($profile)) {
            $this->profiles->add($profile);
        }
    }

    public function removeProfile(Profile $profile): void
    {
        $this->profiles->removeElement($profile);
    }

    public function isUpcoming(): bool
    {
        return self::STATUS_UPCOMING === $this->status;
    }

    public function isInProgress(): bool
    {
        return self::STATUS_IN_PROGRESS === $this->status;
    }

    public function isDone(): bool
    {
        return self::STATUS_DONE === $this->status;
    }
}
