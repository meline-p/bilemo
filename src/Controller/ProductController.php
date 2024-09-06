<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/api/products', name: 'app_products_list', methods:['GET'])]
    public function getProductList(
        ProductRepository $productRepository, 
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getProductList-" . $page . "-" . $limit;

        $jsonProductList = $cachePool->get($idCache, function(ItemInterface $item) use ($productRepository, $page, $limit, $serializer){
            echo('pas encore en cache');
            $item->tag('productsListCache');
            $productsList = $productRepository->findAllProductsWithPagination($page, $limit);

            $context = SerializationContext::create()->setGroups(['getProducts']);
            return $serializer->serialize($productsList, 'json', $context);
        });
        
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
        
    }

    #[Route('/api/products/{id}', name: 'app_products_detail', methods:['GET'])]
    public function getDetailProduct(
        int $id, 
        ProductRepository $productRepository, 
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {

        $product = $productRepository->find($id);
        
        if(!$product){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Aucun produit disponible");
        }

        $idCache = "getProductDetails-" . $id;

        $jsonProductDetails = $cachePool->get($idCache, function(ItemInterface $item) use ($product, $serializer){
            echo('pas encore en cache');
            $item->tag('productsDetailsCache');

            $context = SerializationContext::create()->setGroups(['getProducts']);
            return $serializer->serialize($product, 'json', $context);
        });
    
        return new JsonResponse($jsonProductDetails, Response::HTTP_OK, [], true);
    }
}
