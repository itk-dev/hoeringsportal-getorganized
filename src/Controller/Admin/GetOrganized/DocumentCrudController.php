<?php

namespace App\Controller\Admin\GetOrganized;

use App\Admin\Field\JsonField;
use App\Controller\Admin\AbstractCrudController;
use App\Entity\GetOrganized\Document;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class DocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->showEntityActionsInlined()
            ->setDefaultSort(['updatedAt' => Criteria::DESC])
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
        yield AssociationField::new('archiver');
        yield TextField::new('fileInfo', 'File')
            ->onlyOnIndex();
        yield TextField::new('caseId');
        yield TextField::new('docId');
        yield TextField::new('shareFileItemId');
        yield DateField::new('createdAt')
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield DateField::new('updatedAt')
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield JsonField::new('data')
            ->onlyOnDetail();
    }
}
