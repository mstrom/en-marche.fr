<?php

namespace AppBundle\Admin;

use AppBundle\Entity\Timeline\Measure;
use AppBundle\Timeline\MeasureManager;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TimelineMeasureAdmin extends AbstractAdmin
{
    private $measureManager;

    public function __construct($code, $class, $baseControllerName, MeasureManager $measureManager)
    {
        parent::__construct($code, $class, $baseControllerName);

        $this->measureManager = $measureManager;
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->with('Méta-données', ['class' => 'col-md-6'])
                ->add('title', TextType::class, [
                    'label' => 'Titre',
                    'filter_emojis' => true,
                ])
                ->add('link', null, [
                    'label' => 'Lien',
                    'required' => false,
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Statut',
                    'choices' => Measure::STATUSES,
                ])
            ->end()
            ->with('Tags', ['class' => 'col-md-6'])
                ->add('profiles', null, [
                    'label' => 'Profils',
                ])
                ->add('themes', null, [
                    'label' => 'Thèmes',
                ])
                ->add('major', CheckboxType::class, [
                    'label' => 'Mise en avant (32)',
                    'required' => false,
                ])
            ->end()
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('title', null, [
                'label' => 'Titre',
                'show_filter' => true,
            ])
        ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title', null, [
                'label' => 'Nom',
            ])
            ->add('profiles', TextType::class, [
                'label' => 'Profils',
            ])
            ->add('themes', TextType::class, [
                'label' => 'Thèmes',
            ])
            ->add('updated', null, [
                'label' => 'Date de modification',
            ])
            ->add('status', TextType::class, [
                'label' => 'Statut',
                'template' => 'admin/timeline/measure/list_status.html.twig',
            ])
            ->add('_action', null, [
                'virtual_field' => true,
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                ],
            ])
        ;
    }

    /**
     * @param Measure $object
     */
    public function preUpdate($object)
    {
        $this->measureManager->preUpdate($object);
    }
}
