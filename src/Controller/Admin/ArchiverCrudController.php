<?php

namespace App\Controller\Admin;

use App\Admin\Field\YamlField;
use App\Entity\Archiver;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ArchiverCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Archiver::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        yield YamlField::new('configuration')->hideOnIndex()
        ->setFormTypeOptions([
            'schema' => $this->getParameter('kernel.project_dir').'/config/schema/archiver.configuration.schema.yaml',
            'attr' => [
                'rows' => 20,
                'cols' => 80,
            ],
        ]);
        yield ChoiceField::new('type')
            ->setChoices([
                Archiver::TYPE_SHAREFILE2GETORGANIZED => Archiver::TYPE_SHAREFILE2GETORGANIZED,
                Archiver::TYPE_PDF_COMBINE => Archiver::TYPE_PDF_COMBINE,
                Archiver::TYPE_HEARING_OVERVIEW => Archiver::TYPE_HEARING_OVERVIEW,
            ]);
        yield BooleanField::new('enabled');
    }
}
