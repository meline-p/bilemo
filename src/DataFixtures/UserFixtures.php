<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;
use Faker;

class UserFixtures extends Fixture
{
    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create('fr_FR');

        for ($i = 1; $i <= 10; $i++) {

            $first_name = $faker->firstName();
            $last_name = $faker->lastName();

            $user = new User();
            $user->setUsername(strtolower($this->slugger->slug($first_name)). rand(1,100));
            $user->setFirstName($first_name);
            $user->setLastName($last_name);
            $user->setEmail(strtolower($first_name).'.'.strtolower($last_name).'@gmail.com');

            // get customer reference
            $customer = $this->getReference('customer-'.rand(1,5));
            $user->setCustomer($customer);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
