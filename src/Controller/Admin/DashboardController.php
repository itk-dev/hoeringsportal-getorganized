<?php

namespace App\Controller\Admin;

use App\Entity\Archiver;
use App\Entity\ExceptionLogEntry;
use App\Entity\GetOrganized\Document;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    #[\Override]
    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(ArchiverCrudController::class)
            ->generateUrl());
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->getParameter('site_name'));
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToCrud(new TranslatableMessage('Archiver'), 'fas fa-list', Archiver::class);
        yield MenuItem::linkToCrud(new TranslatableMessage('Error log'), 'fas fa-list', ExceptionLogEntry::class);

        yield MenuItem::section(new TranslatableMessage('GetOrganized'));
        yield MenuItem::linkToCrud(new TranslatableMessage('Document'), 'fas fa-list', Document::class);

        yield MenuItem::section(new TranslatableMessage('PDF'));
        yield MenuItem::linkToRoute(new TranslatableMessage('Combine'), 'fas fa-list', 'admin_pdf_combine');
    }
}
