<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

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
            $item->tag('productsCache');
            $productsList = $productRepository->findAllProductsWithPagination($page, $limit);
            return $serializer->serialize($productsList, 'json', ['groups' => 'getProducts']);
        });
        
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
        
    }

    #[Route('/api/products/{id}', name: 'app_products_detail', methods:['GET'])]
    public function getDetailProduct(int $id, ProductRepository $productRepository, SerializerInterface $serializer): JsonResponse
    {
        $product = $productRepository->find($id);

        if(!$product){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Aucun produit disponible");
        }

        $jsonProduct = $serializer->serialize($product, 'json', ['groups' => 'getProducts']);

        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }
}
