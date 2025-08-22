<?php

namespace App\Tests\Command;

use App\Command\CreateUserCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserCommandTest extends TestCase
{
    private const MIN_PASSWORD_LENGTH = 16;

    public function testExecuteCreatesUser(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $command = new CreateUserCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'new@example.com',
            'password' => str_repeat('a', self::MIN_PASSWORD_LENGTH),
        ]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('erfolgreich', $tester->getDisplay());
        $this->assertStringContainsString('erstellt', $tester->getDisplay());
    }

    public function testExecuteFailsIfUserExists(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->never())->method('persist');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $command = new CreateUserCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'existing@example.com',
            'password' => str_repeat('b', self::MIN_PASSWORD_LENGTH),
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('existiert', $tester->getDisplay());
        $this->assertStringContainsString('bereits', $tester->getDisplay());
    }

    public function testExecuteFailsForShortPassword(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $command = new CreateUserCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'short@example.com',
            'password' => 'short',
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('mindestens 16 Zeichen', $tester->getDisplay());
    }
}