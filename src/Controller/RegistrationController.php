<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
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
        UserEmailService $emailService
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->processValidForm($form, $user, $userPasswordHasher, $entityManager, $emailService)) {
                return $this->redirectToRoute('app_login');
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
        UserEmailService $emailService
    ): bool {
        /** @var string $plainPassword */
        $plainPassword = $form->get('plainPassword')->getData();

        $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $user->setIsActive(true);

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            // Send welcome email
            $emailService->sendRegistrationConfirmation($user);

            $this->addFlash('success', 'Registration successful! Please check your email for confirmation. You can now log in.');

            return true;
        } catch (UniqueConstraintViolationException $e) {
            $this->addFlash('error', 'This email address is already registered.');

            return false;
        } catch (\Exception $e) {
            // Handle email sending errors gracefully
            $this->addFlash('warning', 'Registration successful, but we couldn\'t send the confirmation email. You can still log in.');

            return true;
        }
    }
}
