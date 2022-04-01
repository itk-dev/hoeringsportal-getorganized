<?php

namespace App\Controller\Admin;

use App\Entity\Archiver;
use App\Entity\GetOrganized\Document;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Sharefile2go');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToCrud(new TranslatableMessage('Archiver'), 'fas fa-list', Archiver::class);

        yield MenuItem::section('GetOrganized');
        yield MenuItem::linkToCrud(new TranslatableMessage('Document'), 'fas fa-list', Document::class);
    }
}
