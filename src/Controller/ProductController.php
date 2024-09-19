<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\VersioningService;
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
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class ProductController extends AbstractController
{
    private VersioningService $versioningService;

    public function __construct(VersioningService $versioningService)
    {
        $this->versioningService = $versioningService;
    }

    /**
     * Products list
     *
     * @param ProductRepository $productRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     *
     */
    #[OA\Response(
        response: 200,
        description: "Return products list",
        content: new OA\JsonContent(
            type: "array",
            items:new OA\Items(
                ref: new Model(
                    type:Product::class,
                    groups:["getProducts"]
                )
            )
        )
    )]
    #[OA\Tag(name:"Products")]
    #[OA\Parameter(
        name:"page",
        in:"query",
        description:"The page we want to retrieve",
        schema: new OA\Schema(type:'int')
    )]
    #[OA\Parameter(
        name:"limit",
        in:"query",
        description:"The number of items we want to retrieve",
        schema: new OA\Schema(type:'int')
    )]
    #[Route('/api/products', name: 'app_products_list', methods: ['GET'])]
    public function getProductList(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 3);

        $idCache = "getProductList-" . $page . "-" . $limit;

        $jsonProductList = $cachePool->get($idCache, function (ItemInterface $item) use ($productRepository, $page, $limit, $serializer) {
            echo('pas encore en cache');
            $item->tag('productsListCache');
            $productsList = $productRepository->findAllProductsWithPagination($page, $limit);

            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getProducts']);
            $context->setVersion($version);
            return $serializer->serialize($productsList, 'json', $context);
        });

        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }

    /**
     * Product Details
     *
     * @param in $id
     * @param ProductRepository $productRepository
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     *
     */
    #[OA\Response(
        response: 200,
        description: "Return product details",
        content: new OA\JsonContent(
            type: "array",
            items:new OA\Items(
                ref: new Model(
                    type:Product::class,
                    groups:["getProducts"]
                )
            )
        )
    )]
    #[OA\Tag(name:"Products")]
    #[Route('/api/products/{id}', name: 'app_products_detail', methods: ['GET'])]
    public function getDetailProduct(
        int $id,
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $product = $productRepository->find($id);

        if (!$product) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Aucun produit disponible");
        }

        $idCache = "getProductDetails-" . $id;

        $jsonProductDetails = $cachePool->get($idCache, function (ItemInterface $item) use ($product, $serializer) {
            echo('pas encore en cache');
            $item->tag('productsDetailsCache');

            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getProducts']);
            $context->setVersion($version);
            return $serializer->serialize($product, 'json', $context);
        });

        return new JsonResponse($jsonProductDetails, Response::HTTP_OK, [], true);
    }
}
