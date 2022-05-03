<?php

namespace App\Controller\Admin\GetOrganized;

use App\Admin\Field\JsonField;
use App\Entity\GetOrganized\Document;
use Doctrine\Common\Collections\Criteria;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
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
        return $crud->showEntityActionsInlined()
            ->setDefaultSort(['updatedAt' => Criteria::DESC])
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('archiver');
        yield TextField::new('caseId');
        yield TextField::new('docId');
        yield TextField::new('shareFileItemId');
        yield DateField::new('createdAt')
            ->setFormat('yyyy-MM-dd hh:mm:ss');
        yield DateField::new('updatedAt')
            ->setFormat('yyyy-MM-dd hh:mm:ss');
        yield JsonField::new('data')
            ->onlyOnDetail();
    }
}
