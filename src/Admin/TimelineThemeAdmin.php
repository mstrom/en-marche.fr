<?php

namespace AppBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class TimelineThemeAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->with('Méta-données', ['class' => 'col-md-8'])
                ->add('title', TextType::class, [
                    'label' => 'Titre',
                    'filter_emojis' => true,
                ])
                ->add('media', null, [
                    'label' => 'Image principale',
                    'required' => false,
                ])
            ->end()
            ->with('Publication', ['class' => 'col-md-4'])
                ->add('slug', null, [
                    'label' => 'URL de publication',
                    'help' => 'Ne spécifier que la fin : http://en-marche.fr/timeline/theme/[votre-valeur]<br />Doit être unique',
                ])
                ->add('featured', CheckboxType::class, [
                    'label' => 'Mise en avant',
                    'required' => false,
                ])
            ->end()
            ->with('Contenu', ['class' => 'col-md-12'])
                ->add('description', TextareaType::class, [
                    'label' => 'Contenu',
                    'required' => false,
                    'filter_emojis' => true,
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
            ->add('featured', null, [
                'label' => 'Mise en avant',
                'show_filter' => true,
            ])
        ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('_thumbnail', null, [
                'label' => 'Image',
                'virtual_field' => true,
                'template' => 'admin/timeline/theme/list_image.html.twig',
            ])
            ->addIdentifier('title', null, [
                'label' => 'Nom',
            ])
            ->add('featured', null, [
                'label' => 'Mise en avant',
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
}
