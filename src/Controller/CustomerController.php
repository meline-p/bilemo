<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomerController extends AbstractController
{
    #[Route('/api/customers/{customer_id}/users', name: 'app_customers_users_list', methods:['GET'])]
    public function getCustomerUsers(
        int $customer_id, 
        CustomerRepository $customerRepository, 
        SerializerInterface $serializer
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if(!$customer){
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, "Le client n'existe pas.");
        }

        $jsonCustomer = $serializer->serialize($customer, 'json', ['groups' => 'getCustomerUsers']);
        
        return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers/{customer_id}/users/{user_id}', name: 'app_customers_users_details', methods:['GET'])]
    public function getCustomerUserDetail(
        int $customer_id, 
        int $user_id,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        SerializerInterface $serializer
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

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomerUsersDetails']);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers/{customer_id}/users', name: 'app_customers_users_add', methods:['POST'])]
    public function addCustomerUser(
        int $customer_id, 
        Request $request,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator,
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
        $user = $userData;
        $user->setCustomer($customer);

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomerUsers']);

        $location = $urlGenerator->generate('app_customers_users_details', ['customer_id' => $customer->getId(),'user_id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true); 
    }

    #[Route('/api/customers/{customer_id}/users/{user_id}', name: 'app_customers_users_delete', methods:['DELETE'])]
    public function deleteCustomerUser(
        int $customer_id, 
        int $user_id,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        EntityManagerInterface $em,
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

        $em->remove($user);
        $em->flush();
        
        return new JsonResponse("Success : L'utilisateur a été supprimé.", Response::HTTP_NO_CONTENT);
    }
}
