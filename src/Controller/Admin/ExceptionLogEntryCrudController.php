<?php

namespace App\Controller\Admin;

use App\Entity\ExceptionLogEntry;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Translation\TranslatableMessage;

class ExceptionLogEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExceptionLogEntry::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular(new TranslatableMessage('Error log entry'))
            ->setEntityLabelInPlural(new TranslatableMessage('Error log entries'))
            ->setDefaultSort(['createdAt' => Criteria::DESC])
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('createdAt', new TranslatableMessage('Created at'))
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield TextareaField::new('message', new TranslatableMessage('Message'));
        yield CodeEditorField::new('traceYaml', new TranslatableMessage('Trace'))
            ->setLabel('Trace')
            ->onlyOnDetail()
        ;
        yield CodeEditorField::new('contextYaml', new TranslatableMessage('Context'))
            ->setLabel('Context')
            ->onlyOnDetail()
        ;
    }
}
