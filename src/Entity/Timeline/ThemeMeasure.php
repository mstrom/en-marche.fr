<?php

namespace AppBundle\Entity\Timeline;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Table(name="timeline_themes_measures")
 * @ORM\Entity
 *
 * @UniqueEntity(fields={"theme", "measure"})
 */
class ThemeMeasure
{
    /**
     * @var int
     *
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $featured = false;

    /**
     * @var Theme|null
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Timeline\Theme", inversedBy="measures")
     */
    private $theme;

    /**
     * @var Measure|null
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Timeline\Measure")
     *
     * @Algolia\Attribute
     */
    private $measure;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }

    public function getMeasure(): ?Measure
    {
        return $this->measure;
    }

    public function setMeasure(Measure $measure): void
    {
        $this->measure = $measure;
    }
}
