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

class ExceptionLogEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExceptionLogEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined()
            ->setDefaultSort(['createdAt' => Criteria::DESC])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('createdAt')
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield TextareaField::new('message');
        yield CodeEditorField::new('traceYaml')
            ->setLabel('Trace')
            ->onlyOnDetail()
        ;
        yield CodeEditorField::new('contextYaml')
            ->setLabel('Context')
            ->onlyOnDetail()
        ;
    }
}
