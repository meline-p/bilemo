<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\SerializerInterface;

class CustomerController extends AbstractController
{
    private VersioningService $versioningService;

    public function __construct(VersioningService $versioningService)
    {
        $this->versioningService = $versioningService;
    }

     /**
     * Users list
     *
     * @OA\Response(
     *     response=200,
     *     description="Return users list",
     *     @Model(type=User::class, groups={"getCustomerUsers"})
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of items we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param CustomerRepository $customerRepository
     * @param SerializerInterface $serializer
     * @param Security $security
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse 
     * 
     */
    #[Route('/api/users', name: 'app_customers_users_list', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUsers( 
        CustomerRepository $customerRepository, 
        SerializerInterface $serializer,
        Security $security,
        Request $request,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getCustomerUsers-" . $page . "-" . $limit;

        $customerId = $customer->getId();

        $jsonCustomerUsers = $cachePool->get($idCache, function(ItemInterface $item) use ($customerRepository, $page, $customerId, $limit, $serializer){
            echo('pas encore en cache');
            $item->tag('customerUsersCache');
            $customersUsersList = $customerRepository->findAllCustomerUsersWithPagination($page, $customerId, $limit);
           
            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getCustomerUsers']);
            $context->setVersion($version);
            return $serializer->serialize($customersUsersList, 'json', $context);
        });
        
        return new JsonResponse($jsonCustomerUsers, Response::HTTP_OK, [], true);
        
    }

    /**
     * Users details
     *
     * @OA\Response(
     *     response=200,
     *     description="Return user details",
     *     @Model(type=User::class, groups={"getCustomerUsersDetails"})
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of items we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse 
     * 
     */
    #[Route('/api/users/{id}', name: 'app_customers_users_details', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUserDetail(
        int $id, 
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Security $security,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $user = $userRepository->find($id);

        if(!$user){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        /** @var Customer $customer  */
        $customer = $security->getUser();

        $idCache = "getCustomerUsersDetails-" . $id . "-" . $customer->getId();

        $jsonCustomerUsersDetails = $cachePool->get($idCache, function(ItemInterface $item) use ($userRepository, $id, $serializer){
            echo('pas encore en cache');
            $item->tag('customerUsersDetailsCache');
            $user = $userRepository->find($id);
            
            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getCustomerUsersDetails']);
            $context->setVersion($version);
            return $serializer->serialize($user, 'json', $context);
        });

        return new JsonResponse($jsonCustomerUsersDetails, Response::HTTP_OK, [], true);
    }

     /**
     * Add user
     *
     * @OA\Response(
     *     response=200,
     *     description="Add user",
     *     @Model(type=User::class)
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of items we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param Request $request
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse 
     * 
     */
    #[Route('/api/users', name: 'app_customers_users_add', methods:['POST'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour créer un utilisateur")]
    public function addCustomerUser( 
        Request $request, 
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cachePool,
        Security $security,
        ): JsonResponse
    {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $userData = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($userData);

        if ($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $existingUser = $userRepository->findByEmail($userData->getEmail());

        if($existingUser){
            throw new HttpException(JsonResponse::HTTP_CONFLICT, "Un utilisateur avec cette adresse mail existe déjà.");
        }

        $user = new User();
        $user->setUsername(strtolower($userData->getUsername()));
        $user->setFirstName(ucfirst($userData->getFirstName()));
        $user->setLastName(ucfirst($userData->getLastName()));
        $user->setEmail(strtolower($userData->getEmail()));
        $user->setCustomer($customer->getId());

        $em->persist($user);
        $em->flush();

        $cachePool->invalidateTags(["customerUsersCache"]);

        $context = SerializationContext::create()->setGroups(['getCustomerUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('app_customers_users_details', ['user_id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true); 
    }

    /**
     * Delete user
     *
     * @OA\Response(
     *     response=200,
     *     description="Delete user",
     *     @Model(type=User::class)
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of items we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $em
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse 
     * 
     */
    #[Route('/api/users/{id}', name: 'app_customers_users_delete', methods:['DELETE'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour supprimer un utilisateur")]
    public function deleteCustomerUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        Security $security,
        ): JsonResponse
    {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $user = $userRepository->find($id);

        if(!$user){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        $cachePool->invalidateTags(["customerUsersCache"]);
        $em->remove($user);
        $em->flush();
        
        return new JsonResponse("Success : L'utilisateur a été supprimé.", Response::HTTP_NO_CONTENT);
    }
}
