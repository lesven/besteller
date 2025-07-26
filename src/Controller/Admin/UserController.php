<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');

            if (strlen($password) < 16) {
                $this->addFlash('error', 'Das Passwort muss mindestens 16 Zeichen lang sein.');
            } elseif ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Ein Benutzer mit dieser E-Mail existiert bereits.');
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_ADMIN']);
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Benutzer wurde erfolgreich erstellt.');

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin/user/new.html.twig');
    }

    public function edit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');

            if ($password !== '' && strlen($password) < 16) {
                $this->addFlash('error', 'Das Passwort muss mindestens 16 Zeichen lang sein.');
            } else {
                $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing && $existing->getId() !== $user->getId()) {
                    $this->addFlash('error', 'Ein Benutzer mit dieser E-Mail existiert bereits.');
                } else {
                    $user->setEmail($email);

                    if ($password !== '') {
                        $user->setPassword(
                            $this->passwordHasher->hashPassword($user, $password)
                        );
                    }

                    $this->entityManager->flush();

                    $this->addFlash('success', 'Benutzer wurde erfolgreich aktualisiert.');

                    return $this->redirectToRoute('admin_users');
                }
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    public function delete(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Benutzer wurde erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_users');
    }
}
