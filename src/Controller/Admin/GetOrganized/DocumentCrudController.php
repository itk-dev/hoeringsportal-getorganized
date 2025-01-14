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
use Symfony\Component\Translation\TranslatableMessage;

class DocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular(new TranslatableMessage('Document'))
            ->setEntityLabelInPlural(new TranslatableMessage('Documents'))
            ->setDefaultSort(['updatedAt' => Criteria::DESC])
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
        yield AssociationField::new('archiver', new TranslatableMessage('Archiver'));
        yield TextField::new('fileInfo', new TranslatableMessage('File'))
            ->onlyOnIndex();
        yield TextField::new('caseId', new TranslatableMessage('Case id'));
        yield TextField::new('docId', new TranslatableMessage('Document id'));
        yield TextField::new('shareFileItemId', new TranslatableMessage('ShareFile item id'));
        yield DateField::new('createdAt', new TranslatableMessage('Created at'))
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield DateField::new('updatedAt', new TranslatableMessage('Updated at'))
            ->setFormat($this->getParameter('display_datetime_format'))
            ->setTimezone($this->getParameter('display_datetime_timezone'));
        yield JsonField::new('data', new TranslatableMessage('Data'))
            ->onlyOnDetail();
    }
}
