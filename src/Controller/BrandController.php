<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\BusinessRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Business;

class BrandController extends AbstractController
{
    #[Route("/brands", name:"brands")]
    public function brandsList(BusinessRepository $businessRepository): Response
    {
        // Fetch all businesses from the database
        $businesses = $businessRepository->findAll();

        // Return the rendered template with the data
        return $this->render('pages/brands.html.twig', [
            'businesses' => $businesses,
        ]);
    }

    #[Route('/business/{id}', name: 'business_profile')]
    public function viewBusinessProfile(BusinessRepository $businessRepository, $id): Response
    {
        // Debug the $id to ensure it is being passed correctly
        dump($id);

        // Cast the $id to an integer
        $id = (int) $id;

        // Fetch the business by ID
        $business = $businessRepository->find($id);

        // Debug the retrieved business
        dump($business);

        if (!$business) {
            throw $this->createNotFoundException('Business not found');
        }

        // Render the business profile page
        return $this->render('pages/business_profile.html.twig', [
            'business' => $business,
        ]);
    }

    #[Route('/brand/{id}', name: 'brand_show')]
    public function showBrand(int $id, EntityManagerInterface $entityManager): Response
    {
        // Fetch the business by ID
        $business = $entityManager->getRepository(Business::class)->find($id);

        if (!$business) {
            throw $this->createNotFoundException('Business not found');
        }

        // Render the brand profile page
        return $this->render('brand/show.html.twig', [
            'business' => $business,
        ]);
    }
}