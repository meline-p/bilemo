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
use Psr\Log\LoggerInterface;

class UserController extends AbstractController
{
    private VersioningService $versioningService;

    public function __construct(VersioningService $versioningService)
    {
        $this->versioningService = $versioningService;
    }

    /**
     * Get users list
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Security $security
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @param LoggerInterface $logger
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
                    groups:["getUsers"]
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
    #[Route('/api/users', name: 'app_users_list', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Accès refusé")]
    public function getUsers(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Security $security,
        Request $request,
        TagAwareCacheInterface $cachePool,
        LoggerInterface $logger
    ): JsonResponse {

        /** @var Customer $customer  */
        $customer = $security->getUser();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getUsers-". $customer->getId() . '-' . $page . "-" . $limit;

        $customerId = $customer->getId();

        $jsonUsers = $cachePool->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $customerId, $limit, $serializer, $logger) {
            $logger->notice('cache miss');
            $item->tag('UsersCache');
            $usersList = $userRepository->findAllUsersWithPagination($page, $customerId, $limit);

            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $context->setVersion($version);
            return $serializer->serialize($usersList, 'json', $context);
        });

        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    /**
     * Get user details
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Security $security
     * @param TagAwareCacheInterface $cachePool
     * @param LoggerInterface $logger
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
                    groups:["getUsers"]
                )
            )
        )
    )]
    #[OA\Response(
        response: 404,
        description: "User not found"
    )]
    #[OA\Response(
        response: 403,
        description: "Access Denied"
    )]
    #[OA\Tag(name:"Users")]
    #[Route('/api/users/{id}', name: 'app_users_details', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Accès refusé")]
    public function getUserDetail(
        int $id,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        Security $security,
        TagAwareCacheInterface $cachePool,
        LoggerInterface $logger
    ): JsonResponse {
        $user = $userRepository->find($id);

        if(!$user) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Utilisateur inconnu.");
        }

        /** @var Customer $customer  */
        $customer = $security->getUser();

        if ($user->getCustomer() !== $customer) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Accès refusé");
        }

        $idCache = "getUsersDetails-" . $id . "-" . $customer->getId();

        $jsonUsersDetails = $cachePool->get($idCache, function (ItemInterface $item) use ($user, $serializer, $logger) {
            $logger->notice('cache miss');
            $item->tag('usersDetailsCache');

            $version = $this->versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $context->setVersion($version);
            return $serializer->serialize($user, 'json', $context);
        });

        return new JsonResponse($jsonUsersDetails, Response::HTTP_OK, [], true);
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
            required:true,
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
                content: new OA\JsonContent(ref: new Model(type: User::class, groups: ["getUsers"]))
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
    #[Route('/api/users', name: 'app_users_add', methods:['POST'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Accès refusé")]
    public function addUser(
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

        $cachePool->invalidateTags(["usersCache"]);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('app_users_details', ['id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * Delete a user
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
        response: 403,
        description: "Access Denied"
    )]
    #[OA\Response(
        response: 404,
        description: "User not found"
    )]
    #[OA\Tag(name:"Users")]
    #[Route('/api/users/{id}', name: 'app_users_delete', methods:['DELETE'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Accès refusé")]
    public function deleteUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        Security $security,
    ): JsonResponse {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $user = $userRepository->find($id);

        if(!$user) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Utilisateur inconnu.");
        }


        if ($user->getCustomer() !== $customer) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Accès refusé");
        }


        $cachePool->invalidateTags(["usersCache"]);
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
    * Edit a user
    *
    * @param Request $request
    * @param UserRepository $userRepository
    * @param SerializerInterface $serializer
    * @param EntityManagerInterface $em
    * @param ValidatorInterface $validator
    * @param Security $security
    * @param TagAwareCacheInterface $cachePool
    * @return JsonResponse
    *
    */
    #[OA\Put(
        path: '/api/users/{id}',
        summary: "Edit a user",
        requestBody: new OA\RequestBody(
            required:true,
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
                response: 200,
                description: "User successfully edited",
                content: new OA\JsonContent(ref: new Model(type: User::class, groups: ["getUsers"]))
            ),
            new OA\Response(
                response: 400,
                description: "Validation failed"
            ),
            new OA\Response(
                response: 403,
                description: "Access Denied"
            ),
            new OA\Response(
                response: 409,
                description: "User already exists"
            )
        ]
    )]
    #[OA\Tag(name:"Users")]
    #[Route('/api/users/{id}', name: 'app_users_edit', methods:['PUT'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Accès refusé")]
    public function editUser(
        int $id,
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cachePool,
        Security $security,
    ): JsonResponse {
        /** @var Customer $customer  */
        $customer = $security->getUser();

        $userData = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($userData);

        if ($userData->getCustomer() !== $customer) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Accès refusé");
        }

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $existingUser = $userRepository->findOneByEmail($userData->getEmail());

        if($existingUser && $existingUser->getId() !== $id) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, "Un utilisateur avec cette adresse mail existe déjà.");
        }

        $user = $userRepository->find($id);
        $user->setUsername(strtolower($userData->getUsername()));
        $user->setFirstName(ucfirst($userData->getFirstName()));
        $user->setLastName(ucfirst($userData->getLastName()));
        $user->setEmail(strtolower($userData->getEmail()));
        $user->setCustomer($customer);

        $em->persist($user);
        $em->flush();

        $cachePool->invalidateTags(["usersCache"]);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
