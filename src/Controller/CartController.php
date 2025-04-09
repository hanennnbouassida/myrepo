<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart')]
    public function index(SessionInterface $session, ProductRepository $productRepository): Response
    {
        $sessionCart = $session->get('cart', []);
        $cart = [];
        $total = 0;

        foreach ($sessionCart as $productId => $item) {
            $product = $productRepository->find($productId);
            if (!$product) {
                unset($sessionCart[$productId]); // Remove invalid product
                continue;
            }

            $cart[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                // Price calculations are handled in Twig (as per your template)
            ];

            // Calculate total with discount (if needed for backend logic)
            $discountedPrice = $product->getDiscountPercentage() > 0 
                ? $product->getPrice() * (1 - $product->getDiscountPercentage() / 100)
                : $product->getPrice();
            $total += $discountedPrice * $item['quantity'];
        }

        $session->set('cart', $sessionCart); // Update session in case of invalid products

        return $this->render('product/cart.html.twig', [
            'cart' => $cart, // Matches your Twig template variable
            'total' => $total
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add')]
    public function addToCart(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity']++;
        } else {
            $cart[$productId] = [
                'quantity' => 1,
                // Store only minimal data (price calculations are in Twig)
            ];
        }

        $session->set('cart', $cart);
        $this->addFlash('success', 'Product added to cart!');
        return $this->redirectToRoute('shop');
    }

    #[Route('/cart/increase/{id}', name: 'cart_increase')]
    public function increase(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity']++;
            $session->set('cart', $cart);
        }

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/decrease/{id}', name: 'cart_decrease')]
    public function decrease(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            if ($cart[$productId]['quantity'] > 1) {
                $cart[$productId]['quantity']--;
            } else {
                unset($cart[$productId]);
            }
            $session->set('cart', $cart);
        }

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function remove(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            $session->set('cart', $cart);
            $this->addFlash('success', 'Product removed from cart!');
        }

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/clear', name: 'cart_clear')]
    public function clear(SessionInterface $session): Response
    {
        $session->remove('cart');
        $this->addFlash('success', 'Cart cleared successfully!');
        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/checkout', name: 'cart_checkout')]
    public function checkout(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $sessionCart = $session->get('cart', []);
        if (empty($sessionCart)) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('cart');
        }

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to place an order.');
            return $this->redirectToRoute('app_login');
        }

        foreach ($sessionCart as $productId => $item) {
            $product = $entityManager->getRepository(Product::class)->find($productId);
            if (!$product) {
                continue;
            }

            $order = new Order();
            $order->setClient($user);
            $order->setBusiness($product->getBusiness());
            $order->setProduct($product);
            $order->setQuantity($item['quantity']);
            $order->setStatus('Pending');

            $entityManager->persist($order);
        }

        $entityManager->flush();
        $session->remove('cart');

        $this->addFlash('success', 'Your order has been placed successfully. You will pay upon delivery.');
        return $this->redirectToRoute('cart');
    }

    #[Route('/client/orders', name: 'client_orders')]
    public function viewClientOrders(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to view your orders.');
        }

        $orders = $entityManager->getRepository(Order::class)->findBy(['client' => $user]);

        return $this->render('client/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}