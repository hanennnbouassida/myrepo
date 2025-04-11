<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Business;
use App\Entity\Product;
use App\Entity\Order;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[IsGranted('ROLE_BUSINESS')]
class BusinessDashboardController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route("/dashboard_business", name:"dashboard_business")]
    public function businessDashboard(): Response
    {
        /** @var Business $business */
        $business = $this->security->getUser();
        
        if (!$business) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

        if ($business->getStatus() !== 'approved') {
            throw new AccessDeniedHttpException('Your business is not approved yet.');
        }

        $products = $business->getProducts();
        $orders = $business->getOrders(); // Fetch orders associated with the business

        return $this->render('dashboards/dashboard_business.html.twig', [
            'products' => $products,
            'business' => $business,
            'orders' => $orders, // Pass orders to the template
        ]);
    }

    //------------------
    #[Route("/product/{id}/delete", name:"product_delete")]
    public function deleteProduct($id, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $entityManager->remove($product);
        $entityManager->flush();

        return $this->redirectToRoute('dashboard_business', ['business_id' => $product->getBusiness()->getId()]);
    }

//----------------------------------------------------------------
    #[Route("/product/{id}", name:"product_show")]
    public function showProduct($id, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
        //----------------------------------
    }
    #[Route("/product/{id}/edit", name:"product_edit")]
    public function editProduct(Request $request, $id, EntityManagerInterface $entityManager): Response
    {
      // Récupérer le produit
      $product = $entityManager->getRepository(Product::class)->find($id);
      if (!$product) {
          throw $this->createNotFoundException('Product not found');
      }

      // Créer le formulaire
      $form = $this->createForm(ProductType::class, $product);
      $form->handleRequest($request);

      // Traitement du formulaire
      if ($form->isSubmitted() && $form->isValid()) {
          // Récupérer le fichier image
          $photoFile = $form->get('photoFile')->getData();

          // Si un fichier est téléchargé, on le déplace et on met à jour le nom du fichier
          if ($photoFile) {
              $newFilename = uniqid() . '.' . $photoFile->guessExtension();
              $photoFile->move(
                  $this->getParameter('kernel.project_dir') . '/public/uploads/products',
                  $newFilename
              );
              $product->setImageproduct($newFilename);
          }

          // Sauvegarder les modifications
          $entityManager->flush();

          // Message flash de succès
          $this->addFlash('success', 'Product updated successfully!');

          // Redirection vers la page de détail du produit
          return $this->redirectToRoute('product_show', ['id' => $product->getId()]);
        }      

      return $this->render('product/edit.html.twig', [
          'form' => $form->createView(),
          'product' => $product,
      ]);
    }
    //----------------------------------------------------------
    
    #[Route("/business/{business_id}/add-product", name:"add_product_route")]
    public function addProduct(Request $request, $business_id, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $business = $entityManager->getRepository(Business::class)->find($business_id);
            if (!$business) {
                throw $this->createNotFoundException('Business not found');
            }

            // Handle file upload manually
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('uploads_directory'), // Ensure this is set in services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                    return $this->redirectToRoute('add_product_route', ['business_id' => $business_id]);
                }

                $product->setImageProduct($newFilename); // Save filename in DB
                $product->setUpdatedAt(new \DateTime());
            }

            // Associate product with business
            $product->setBusiness($business);

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product added successfully.');
            return $this->redirectToRoute('product_show', ['id' => $product->getId()]);
        }

        return $this->render('product/add_product.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    //----------------------------------------
    // ORDER MANAGEMENT for BUSINESS
#[Route('/business/orders', name: 'business_orders')]
public function viewOrders(EntityManagerInterface $entityManager): Response
{
    /** @var Business $business */
    $business = $this->getUser();
    if (!$business) {
        throw $this->createAccessDeniedException('User not authenticated');
    }

    $orders = $entityManager->getRepository(Order::class)->findBy(['business' => $business]);

    return $this->render('business/orders.html.twig', [
        'orders' => $orders,
    ]);
}#[Route('/business/order/{id}/update', name: 'update_order_status', methods: ['POST'])]
public function updateOrderStatus(Request $request, int $id, EntityManagerInterface $entityManager): Response
{
    $order = $entityManager->getRepository(Order::class)->find($id);
    if (!$order) {
        throw $this->createNotFoundException('Order not found');
    }

    /** @var Business|null $business */
    $business = $this->getUser();
    if (!$business || !$business instanceof Business) {
        throw $this->createAccessDeniedException('Logged-in user is not a business');
    }

    if ($order->getBusiness() === null || $order->getBusiness()->getId() !== $business->getId()) {
        throw $this->createAccessDeniedException('You are not authorized to update this order');
    }

    $status = $request->request->get('status');
    if (!in_array($status, ['Confirmed', 'Denied', 'Canceled', 'Delayed'])) {
        $this->addFlash('error', 'Invalid status.');
        return $this->redirectToRoute('business_orders');
    }

    $order->setStatus($status);

    // ✅ Reduce product quantity only if status is confirmed
    if ($status === 'Confirmed') {
        $product = $order->getProduct();
        if ($product !== null) {
            $currentStock = $product->getQte();
            $orderedQuantity = $order->getQuantity();

            if ($currentStock >= $orderedQuantity) {
                $product->setQte($currentStock - $orderedQuantity);
            } else {
                $this->addFlash('error', 'Not enough stock to confirm this order.');
                return $this->redirectToRoute('business_orders');
            }
        }
    }

    $entityManager->flush();

    $this->addFlash('success', 'Order status updated successfully.');
    return $this->redirectToRoute('business_orders');
}

// STOCK MANAGEMENT for BUSINESS

#[Route('/stock-management/{business_id}', name: 'stock_management')]
public function index(EntityManagerInterface $em, int $business_id): Response
{
    // Get the logged-in business
    /** @var Business|null $loggedInBusiness */
    $loggedInBusiness = $this->getUser();
    if (!$loggedInBusiness || !$loggedInBusiness instanceof Business) {
        throw $this->createAccessDeniedException('You must be logged in as a business to access this page.');
    }

    // Ensure the logged-in business matches the requested business_id
    if ($loggedInBusiness->getId() !== $business_id) {
        throw $this->createAccessDeniedException('You are not authorized to access this stock management page.');
    }

    // Get products for this business
    $products = $em->getRepository(Product::class)->findBy(['business' => $loggedInBusiness]);

    return $this->render('business/stockmanagement.html.twig', [
        'products' => $products,
        'business' => $loggedInBusiness,
    ]);
}

#[Route('/stock-management/update/{id}', name: 'stock_management_update', methods: ['POST'])]
public function updateStock(Request $request, EntityManagerInterface $em, int $id): Response
{
    // Get the logged-in business
    /** @var Business|null $loggedInBusiness */
    $loggedInBusiness = $this->getUser();
    if (!$loggedInBusiness || !$loggedInBusiness instanceof Business) {
        throw $this->createAccessDeniedException('You must be logged in as a business to update stock.');
    }

    // Get the product
    $product = $em->getRepository(Product::class)->find($id);
    if (!$product) {
        throw $this->createNotFoundException('Product not found');
    }

    // Ensure the product belongs to the logged-in business
    if ($product->getBusiness()->getId() !== $loggedInBusiness->getId()) {
        throw $this->createAccessDeniedException('You are not authorized to update stock for this product.');
    }

    // Update the stock
    $stockChange = (int)$request->request->get('stock_change');
    $product->setQte($product->getQte() + $stockChange);

    $em->flush();

    $this->addFlash('success', 'Stock updated successfully!');
    return $this->redirectToRoute('stock_management', [
        'business_id' => $loggedInBusiness->getId(),
    ]);
}
//report problemuse App\Entity\Business;

#[Route('/business/report', name: 'business_report_problem')]
public function reportProblem(Request $request, MailerInterface $mailer): Response
{
    /** @var Business $business */
    $business = $this->getUser();
    
    if (!$business) {
        throw $this->createAccessDeniedException('You must be logged in.');
    }

    if ($request->isMethod('POST')) {
        $subject = $request->request->get('subject');
        $message = $request->request->get('message');

        $email = (new Email())
            ->from($business->getEmail()) // Now recognized
            ->to('hanenbouassida456@gmail.com')
            ->subject("Problem Report: $subject")
            ->text("From: {$business->getBusinessName()} ({$business->getEmail()})\n\n$message");

        $mailer->send($email);

        $this->addFlash('success', 'Your report has been sent to the admin.');
        return $this->redirectToRoute('business_dashboard');
    }

    return $this->render('business/report_problem.html.twig');
}
}
