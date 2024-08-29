<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker;

class CustomerFixtures extends Fixture
{
    public function __construct(private SluggerInterface $slugger,private UserPasswordHasherInterface $passwordEncoder,)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create('fr_FR');

        for ($i = 1; $i <= 5; $i++) {

            $name = $faker->unique()->company();
            $slug = $this->slugger->slug($name)->lower();

            $customer = new Customer();
            $customer->setName($name);
            $customer->setSlug($slug);
            $customer->setEmail($slug . '@pro.com');
            $customer->setRoles(["ROLE_CUSTOMER"]);
            $customer->setPassword( $this->passwordEncoder->hashPassword($customer, 'secret'));

            $manager->persist($customer);

            // add customer reference
            $this->addReference('customer-'.$i, $customer);
        }

        $manager->flush();
    }
}
