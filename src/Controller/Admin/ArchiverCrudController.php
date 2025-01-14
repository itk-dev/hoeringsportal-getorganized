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
use Symfony\Component\Translation\TranslatableMessage;

class ArchiverCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Archiver::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular(new TranslatableMessage('Archiver'))
            ->setEntityLabelInPlural(new TranslatableMessage('Archivers'));
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DELETE);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', new TranslatableMessage('Id'))
            // Show the full id on index.
            ->setMaxLength(36)
            ->setDisabled();
        yield TextField::new('name', new TranslatableMessage('Name'));
        yield ChoiceField::new('type', new TranslatableMessage('Type'))
            ->setChoices([
                Archiver::TYPE_SHAREFILE2GETORGANIZED => Archiver::TYPE_SHAREFILE2GETORGANIZED,
                Archiver::TYPE_PDF_COMBINE => Archiver::TYPE_PDF_COMBINE,
                Archiver::TYPE_HEARING_OVERVIEW => Archiver::TYPE_HEARING_OVERVIEW,
            ]);
        yield DateField::new('lastRunAt', new TranslatableMessage('Last run at'))
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'))
            ->hideOnForm();
        yield BooleanField::new('enabled', new TranslatableMessage('Enabled'))
            ->renderAsSwitch(false);
        yield CodeEditorField::new('configuration', new TranslatableMessage('Configuration'))
            ->hideOnIndex()
            ->setLanguage('yaml')
            ->setFormType(YamlEditorType::class)
        ;
    }
}
