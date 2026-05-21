<?php

namespace App\Controller;

use App\Entity\Login;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class ApiRegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON body',
            ], 400);
        }

        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!\is_string($username) || !\is_string($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Username and password are required',
            ], 400);
        }

        $username = trim($username);

        if (\strlen($username) < 3) {
            return $this->json(['success' => false, 'message' => 'Username must be at least 3 characters long'], 400);
        }
        if (\strlen($password) < 6) {
            return $this->json(['success' => false, 'message' => 'Password must be at least 6 characters long'], 400);
        }

        $existingUser = $this->entityManager->getRepository(Login::class)->findOneBy(['username' => $username]);
        if ($existingUser instanceof Login) {
            return $this->json(['success' => false, 'message' => 'Username already exists'], 409);
        }

        $user = new Login();
        $user->setUsername($username);

        if (\is_string($email) && trim($email) !== '') {
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['success' => false, 'message' => 'Invalid email address'], 400);
            }
            $existingEmail = $this->entityManager->getRepository(Login::class)->findOneBy(['email' => $email]);
            if ($existingEmail instanceof Login) {
                return $this->json(['success' => false, 'message' => 'Email already registered'], 409);
            }
            $user->setEmail($email);
        }
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Mobile / API registration: no email verification step (sign in immediately).
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. You can sign in now.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }
}

