<?php
// src/Controller/SearchController.php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\BusinessRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function search(Request $request, ProductRepository $productRepo, BusinessRepository $businessRepo, CategoryRepository $categoryRepo)
    {
        $query = $request->query->get('q', '');
        $selectedBusiness = $request->query->get('business');
        $priceMin = $request->query->get('price-min');
        $priceMax = $request->query->get('price-max');
        $availability = $request->query->get('availability');
        $sort = $request->query->get('sort');
        $selectedCategory = $request->query->get('category');

        $results = [
            'products' => [],
            'businesses' => [],
        ];

        if (!empty($query)) {
            // Fetch products and businesses matching the query
            $products = $productRepo->searchByNameOrDescription($query);
            $businesses = $businessRepo->searchByNameOrDescription($query);

            // Apply filters to products
            if ($selectedBusiness) {
                $products = array_filter($products, function ($product) use ($selectedBusiness) {
                    return $product->getBusiness()->getId() == $selectedBusiness;
                });
            }

            if ($priceMin !== null) {
                $products = array_filter($products, function ($product) use ($priceMin) {
                    return $product->getPrice() >= $priceMin;
                });
            }

            if ($priceMax !== null) {
                $products = array_filter($products, function ($product) use ($priceMax) {
                    return $product->getPrice() <= $priceMax;
                });
            }

            if ($availability === 'available') {
                $products = array_filter($products, function ($product) {
                    return $product->getQte() > 0;
                });
            } elseif ($availability === 'out-of-stock') {
                $products = array_filter($products, function ($product) {
                    return $product->getQte() == 0;
                });
            }

            // Apply category filter to products
            if ($selectedCategory) {
                $products = array_filter($products, function ($product) use ($selectedCategory) {
                    return $product->getCategory()->getId() == $selectedCategory;
                });
            }

            // Apply sorting to products
            if ($sort === 'price-asc') {
                usort($products, fn($a, $b) => $a->getPrice() <=> $b->getPrice());
            } elseif ($sort === 'price-desc') {
                usort($products, fn($a, $b) => $b->getPrice() <=> $a->getPrice());
            } elseif ($sort === 'name-asc') {
                usort($products, fn($a, $b) => strcmp($a->getProductName(), $b->getProductName()));
            } elseif ($sort === 'name-desc') {
                usort($products, fn($a, $b) => strcmp($b->getProductName(), $a->getProductName()));
            }

            $results['products'] = $products;
            $results['businesses'] = $businesses;
        }

        // Fetch all businesses for the filter dropdown
        $allBusinesses = $businessRepo->findAll();

        // Fetch all categories for the filter dropdown
        $allCategories = $categoryRepo->findAll();

        return $this->render('pages/searchresult.html.twig', [
            'query' => $query,
            'results' => $results,
            'allBusinesses' => $allBusinesses,
            'selectedBusiness' => $selectedBusiness,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'availability' => $availability,
            'sort' => $sort,
            'allCategories' => $allCategories,
            'selectedCategory' => $selectedCategory,
        ]);
    }
}
