<?php

namespace App\Controller\Admin;

use App\Entity\Archiver;
use App\Form\YamlEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ArchiverCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Archiver::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            // Show the full id on index.
            ->setMaxLength(36)
            ->setDisabled();
        yield TextField::new('name');
        yield ChoiceField::new('type')
            ->setChoices([
                Archiver::TYPE_SHAREFILE2GETORGANIZED => Archiver::TYPE_SHAREFILE2GETORGANIZED,
                Archiver::TYPE_PDF_COMBINE => Archiver::TYPE_PDF_COMBINE,
                Archiver::TYPE_HEARING_OVERVIEW => Archiver::TYPE_HEARING_OVERVIEW,
            ]);
        yield DateField::new('lastRunAt')
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'))
            ->hideOnForm();
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield CodeEditorField::new('configuration')
            ->hideOnIndex()
            ->setLanguage('yaml')
            ->setFormType(YamlEditorType::class)
        ;
    }
}
