<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // @see https://symfony.com/bundles/EasyAdminBundle/3.x/dashboards.html#login-form-template
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'csrf_token_intention' => 'authenticate',
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}
