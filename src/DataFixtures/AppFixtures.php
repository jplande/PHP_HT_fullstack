<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Pool;
use App\Entity\Song;
use App\Entity\User;
use Faker\Generator;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    private Generator $faker;

    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->faker = Factory::create('fr_FR');
        $this->userPasswordHasher = $userPasswordHasher;
    }
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);
        $pools = [];
        for ($i = 0; $i < 10; $i++) {
            # code...
            $pool = new Pool();
            $pool->setName($this->faker->name($i % 2 ? "male" : "female"));
            $pool->setCode('toto' . $i);
            $pool->setStatus('on');
            $manager->persist($pool);
            $pools[] = $pool;
        }
        for ($i = 0; $i < 100; $i++) {
            # code...
            $song = new Song();
            $song->setName($this->faker->name($i % 2 ? "male" : "female"))
                ->setArtiste("Kiss Husky" . $i)
                ->setStatus("on")
                ->addPool($pools[array_rand($pools, 1)]);
            $manager->persist($song);
        }

        $users = [];
        $user = new User();
        $password = $this->userPasswordHasher->hashPassword($user, "password");
        $user->setUsername('admin')
            ->setPassword($password)
            ->setRoles(["ROLE_ADMIN"]);

        $manager->persist($user);

        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            $password = $this->faker->password(2, 6);

            $user->setUsername($this->faker->name() . '@' . $password)
                ->setPassword($this->userPasswordHasher->hashPassword($user, $password))
                ->setRoles(["ROLE_USER"]);


            $users[] = $user;
            $manager->persist($user);
        }

        $manager->flush();
    }



}
