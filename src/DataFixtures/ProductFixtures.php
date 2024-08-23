<?php

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;
use Faker;

class ProductFixtures extends Fixture
{
    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create('fr_FR');

        for ($i = 1; $i <= 5; $i++) {

            $name = ucfirst($faker->word()) . ' ' .  $faker->randomNumber(3, true) . ' ' . $faker->randomElement(['X', 'Pro', 'Max', 'Plus']) ;

            $product = new Product();
            $product->setName($name);
            $product->setSlug($this->slugger->slug($name)->lower());
            $product->setDescription($faker->paragraph(3));
            $product->setPrice($faker->randomFloat(2, 500, 2000));

            // get brand reference
            $brand = $this->getReference('brand-'.rand(1,5));
            $product->setBrand($brand);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
