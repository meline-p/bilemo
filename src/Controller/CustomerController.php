<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
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
    #[Route('/api/customers/{customer_id}/users', name: 'app_customers_users_list', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUsers(
        int $customer_id, 
        CustomerRepository $customerRepository, 
        SerializerInterface $serializer,
        Security $security,
        Request $request,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {

        $customer = $customerRepository->find($customer_id);

        if(!$customer){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Le client n'existe pas.");
        }

        $customerAuthenticate = $security->getUser();
        
        if($customerAuthenticate !== $customer){
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, "Accès refusé : vous n'avez pas les droits pour accèder aux utilisateurs");
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getCustomerUsers-" . $page . "-" . $limit;

        $jsonCustomerUsers = $cachePool->get($idCache, function(ItemInterface $item) use ($customerRepository, $page, $customer_id, $limit, $serializer){
            echo('pas encore en cache');
            $item->tag('customerUsersCache');
            $customersUsersList = $customerRepository->findAllCustomerUsersWithPagination($page, $customer_id, $limit);
           
            $context = SerializationContext::create()->setGroups(['getCustomerUsers']);
            return $serializer->serialize($customersUsersList, 'json', $context);
        });
        
        return new JsonResponse($jsonCustomerUsers, Response::HTTP_OK, [], true);
        
    }

    #[Route('/api/customers/{customer_id}/users/{user_id}', name: 'app_customers_users_details', methods:['GET'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour accèder aux utilisateurs")]
    public function getCustomerUserDetail(
        int $customer_id, 
        int $user_id,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if(!$customer){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Le client n'existe pas.");
        }

        $user = $userRepository->find($user_id);

        if(!$user){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        $idCache = "getCustomerUsersDetails-" . $user_id . "-" . $customer_id;

        $jsonCustomerUsersDetails = $cachePool->get($idCache, function(ItemInterface $item) use ($userRepository, $user_id, $serializer){
            echo('pas encore en cache');
            $item->tag('customerUsersDetailsCache');
            $user = $userRepository->find($user_id);
            
            $context = SerializationContext::create()->setGroups(['getCustomerUsersDetails']);
            return $serializer->serialize($user, 'json', $context);
        });

        return new JsonResponse($jsonCustomerUsersDetails, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers/{customer_id}/users', name: 'app_customers_users_add', methods:['POST'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour créer un utilisateur")]
    public function addCustomerUser(
        int $customer_id, 
        Request $request,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if(!$customer){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Le client n'existe pas.");
        }

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
        $user->setCustomer($customer);

        $em->persist($user);
        $em->flush();

        $cachePool->invalidateTags(["customerUsersCache"]);

        $context = SerializationContext::create()->setGroups(['getCustomerUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('app_customers_users_details', ['customer_id' => $customer->getId(),'user_id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true); 
    }

    #[Route('/api/customers/{customer_id}/users/{user_id}', name: 'app_customers_users_delete', methods:['DELETE'])]
    #[IsGranted('ROLE_CUSTOMER', message: "Vous n'avez pas les droits pour supprimer un utilisateur")]
    public function deleteCustomerUser(
        int $customer_id, 
        int $user_id,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if(!$customer){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Le client n'existe pas.");
        }

        $user = $userRepository->find($user_id);

        if(!$user){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "L'utilisateur n'existe pas.");
        }

        $cachePool->invalidateTags(["customerUsersCache"]);
        $em->remove($user);
        $em->flush();
        
        return new JsonResponse("Success : L'utilisateur a été supprimé.", Response::HTTP_NO_CONTENT);
    }
}
