<?php

namespace AppBundle\Form;

use AppBundle\Entity\BaseGroup;
use AppBundle\Entity\CoordinatorAreaInterface;
use AppBundle\Entity\Timeline\Measure;
use AppBundle\Entity\Timeline\ThemeMeasure;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TimelineThemeMeasureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('measure', EntityType::class, [
                'label' => 'Mesure',
                'class' => Measure::class,
                'required' => true,
            ])
            ->add('featured', CheckboxType::class, [
                'label' => 'Mise en avant',
                'required' => false,
            ]);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ThemeMeasure::class,
        ]);
    }
}
