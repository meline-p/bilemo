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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

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

        if($customer){
            $jsonCustomer = $serializer->serialize($customer, 'json', ['groups' => 'getCustomerUsers']);
            return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/customers/{customer_id}/users/{user_id}', name: 'app_customers_users_detail', methods:['GET'])]
    public function getCustomerUserDetail(
        int $customer_id, 
        int $user_id,
        CustomerRepository $customerRepository, 
        UserRepository $userRepository,
        SerializerInterface $serializer
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if($customer){

            $user = $userRepository->find($user_id);

            if($user){
                $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomerUsersDetail']);
                return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
            }
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
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
        ): JsonResponse
    {
        $customer = $customerRepository->find($customer_id);

        if($customer){

            $user = new User();
            $user = $serializer->deserialize($request->getContent(), User::class, 'json');
            $user->setCustomer($customer);

            $em->persist($user);
            $em->flush();

            $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomerUsers']);

            $location = $urlGenerator->generate('app_customers_users_detail', ['customer_id' => $customer->getId(),'user_id' => $user->getId()], UrlGenerator::ABSOLUTE_URL);

            return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
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

        if($customer){

            $user = $userRepository->find($user_id);

            if($user){
                $em->remove($user);
                $em->flush();
                
                return new JsonResponse(null, Response::HTTP_NO_CONTENT);
            }
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
