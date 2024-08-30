<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "app_customers_users_details",
 *          parameters = { 
 *              "customer_id" = "expr(object.getId())",
 *              "user_id" = "expr(object.getUsers().getId())"
 *          },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getCustomerUsersDetails", excludeIf = "expr(not is_granted('ROLE_CUSTOMER'))")
 * )
 *  * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "app_customers_users_details",
 *          parameters = { 
 *              "customer_id" = "expr(object.getId())",
 *              "user_id" = "expr(object.getUsers().getId())"
 *          },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getCustomerUsersDetails", excludeIf = "expr(not is_granted('ROLE_CUSTOMER'))")
 * )
 *  * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "app_customers_users_details",
 *          parameters = { 
 *              "customer_id" = "expr(object.getId())",
 *              "user_id" = "expr(object.getUsers().getId())"
 *          },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getCustomerUsersDetails", excludeIf = "expr(not is_granted('ROLE_CUSTOMER'))")
 * )
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getCustomerUsers", "getCustomerUsersDetails"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getCustomerUsers", "getCustomerUsersDetails"])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getCustomerUsers", "getCustomerUsersDetails"])]
    private ?string $slug = null;

    #[ORM\Column(length: 180)]
    #[Groups(["getCustomerUsers", "getCustomerUsersDetails"])]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'customer', orphanRemoval: true)]
    #[Groups(["getCustomerUsers"])]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCustomer($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCustomer() === $this) {
                $user->setCustomer(null);
            }
        }

        return $this;
    }
}
