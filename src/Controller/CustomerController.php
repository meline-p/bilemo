<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Customer;
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
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

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
    * @param CustomerRepository $customerRepository
    * @param SerializerInterface $serializer
    * @param Security $security
    * @param Request $request
    * @param TagAwareCacheInterface $cachePool
    * @return JsonResponse
    *
    */
    #[OA\Response(
        response: 200,
        description: "Return users list",
        content: new OA\JsonContent(
            type: "array",
            items:new OA\Items(
                ref: new Model(
                    type:User::class,
                    groups:["getCustomerUsers"]
                )
            )
        )
    )]
    #[OA\Tag(name:"Users")]
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
    #[Route('/api/users', name: 'app_customers_users_list', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUsers(
        CustomerRepository $customerRepository,
        SerializerInterface $serializer,
        Security $security,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {

        /** @var Customer $customer  */
        $customer = $security->getUser();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getCustomerUsers-". $customer->getId() . '-' . $page . "-" . $limit;

        $customerId = $customer->getId();

        $jsonCustomerUsers = $cachePool->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $customerId, $limit, $serializer) {
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
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     *
     */
    #[OA\Response(
        response: 200,
        description: "Return user details",
        content: new OA\JsonContent(
            type: "array",
            items: new OA\Items(
                ref: new Model(
                    type:User::class,
                    groups:["getCustomerUsersDetails"]
                )
            )
        )
    )]
    #[OA\Tag(name:"Users")]
    #[Route('/api/users/{id}', name: 'app_customers_users_details', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUserDetail(
        int $id,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Security $security,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $user = $userRepository->find($id);

        if(!$user) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        /** @var Customer $customer  */
        $customer = $security->getUser();

        if ($user->getCustomer() !== $customer) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Vous n'avez pas les droits pour accéder à ces détails.");
        }

        $idCache = "getCustomerUsersDetails-" . $id . "-" . $customer->getId();

        $jsonCustomerUsersDetails = $cachePool->get($idCache, function (ItemInterface $item) use ($user, $serializer) {
            echo('pas encore en cache');
            $item->tag('customerUsersDetailsCache');

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
    #[OA\Post(
        path: '/api/users',
        summary: "Create a new user",
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "username", type: "string", example: "Test"),
                    new OA\Property(property: "first_name", type: "string", example: "test"),
                    new OA\Property(property: "last_name", type: "string", example: "test"),
                    new OA\Property(property: "email", type: "string", example: "test.test@gmail.com")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User successfully created",
                content: new OA\JsonContent(ref: new Model(type: User::class, groups: ["getCustomerUsers"]))
            ),
            new OA\Response(
                response: 400,
                description: "Validation failed"
            ),
            new OA\Response(
                response: 409,
                description: "User already exists"
            )
        ]
    )]
    #[OA\Tag(name:"Users")]
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
    ): JsonResponse {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $userData = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($userData);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $existingUser = $userRepository->findByEmail($userData->getEmail());

        if($existingUser) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, "Un utilisateur avec cette adresse mail existe déjà.");
        }

        $user = new User();
        $user->setUsername(strtolower($userData->getUsername()));
        $user->setFirstName(ucfirst($userData->getFirstName()));
        $user->setLastName(ucfirst($userData->getLastName()));
        $user->setEmail(strtolower($userData->getEmail()));
        $user->setCustomer($customer);

        $em->persist($user);
        $em->flush();

        $cachePool->invalidateTags(["customerUsersCache"]);

        $context = SerializationContext::create()->setGroups(['getCustomerUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('app_customers_users_details', ['id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * Delete user
     *
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $em
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     *
     */
    #[OA\Response(
        response: 204,
        description: "User successfully deleted",
    )]
    #[OA\Response(
        response: 404,
        description: "User not found"
    )]
    #[OA\Tag(name:"Users")]
    #[Route('/api/users/{id}', name: 'app_customers_users_delete', methods:['DELETE'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour supprimer un utilisateur")]
    public function deleteCustomerUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        Security $security,
    ): JsonResponse {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $user = $userRepository->find($id);

        if ($user->getCustomer() !== $customer) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Vous n'avez pas les droits pour supprimer cet utilisateur.");
        }

        if(!$user) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        $cachePool->invalidateTags(["customerUsersCache"]);
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
