<?php
namespace App\Tests\Command;

use App\Command\ChangePasswordCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordCommandTest extends TestCase
{
    public function testExecuteChangesPassword(): void
    {
        $user = new User();

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->with($user, $this->anything())->willReturn('hashed');

        $command = new ChangePasswordCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'user@example.com',
            'password' => str_repeat('x', 16),
        ]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('erfolgreich geändert', $tester->getDisplay());
    }

    public function testExecuteFailsWhenUserNotFound(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->never())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $command = new ChangePasswordCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'missing@example.com',
            'password' => str_repeat('y', 16),
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('nicht gefunden', $tester->getDisplay());
    }

    public function testExecuteFailsForShortPassword(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $command = new ChangePasswordCommand($em, $hasher);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'email' => 'user@example.com',
            'password' => 'short',
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('mindestens 16 Zeichen', $tester->getDisplay());
    }
}
