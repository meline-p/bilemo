<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

class BrandFixtures extends Fixture
{
    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $brands = [
            1 => "Apple",
            2 => "Samsung",
            3 => "Huawei",
            4 => "Xiaomi",
            5 => "LG"
        ];

        for ($i = 1; $i <= 5; $i++) {

            $name = $brands[$i];

            $brand = new Brand();
            $brand->setName($name);
            $brand->setSlug($this->slugger->slug($name)->lower());
            $manager->persist($brand);

            // add brand reference
            $this->addReference('brand-'.$i, $brand);
        }

        $manager->flush();
    }
}
