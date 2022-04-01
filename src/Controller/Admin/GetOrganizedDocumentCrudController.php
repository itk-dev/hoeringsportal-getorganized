<?php

namespace App\Controller\Admin;

use App\Entity\GetOrganized\Document;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GetOrganizedDocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('archiver');
        yield TextField::new('docId');
        yield TextField::new('shareFileItemId');
        yield DateField::new('createdAt')
            ->setFormat('yyyy-MM-dd hh:mm:ss');
        yield DateField::new('updatedAt')
            ->setFormat('yyyy-MM-dd hh:mm:ss');
    }
}
