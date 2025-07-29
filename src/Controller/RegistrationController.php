<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use App\Service\UserEmailService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function showRegistrationForm(): Response
    {
        $form = $this->createForm(RegistrationFormType::class);

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register', name: 'app_register_process', methods: ['POST'])]
    public function processRegistration(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->processValidForm($form, $user, $userPasswordHasher, $entityManager, $emailVerificationService)) {
                return $this->redirectToRoute('app_verification_pending');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    private function processValidForm(
        FormInterface $form,
        User $user,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): bool {
        /** @var string $plainPassword */
        $plainPassword = $form->get('plainPassword')->getData();

        $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        // User starts as inactive and unverified
        $user->setIsActive(false);
        $user->setIsEmailVerified(false);

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            // Send verification email
            $emailVerificationService->sendVerificationEmail($user);

            // No flash message needed - we redirect to a dedicated verification pending page
            return true;
        } catch (UniqueConstraintViolationException $e) {
            $this->addFlash('error', 'This email address is already registered.');

            return false;
        } catch (\Exception $e) {
            // Handle email sending errors gracefully
            $this->addFlash('warning', 'Registration successful, but we couldn\'t send the verification email. Please contact support.');

            return true;
        }
    }
}
