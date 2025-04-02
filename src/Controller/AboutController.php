<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class AboutController extends AbstractController
{
    
    #[Route("/about", name:"about")]

    public function index(): Response
    {
        return $this->render('pages/about.html.twig');
    }
}