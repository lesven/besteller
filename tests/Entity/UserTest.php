<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testRolesAndIdentifier(): void
    {
        $u = new User();
        $u->setEmail('user@example.com');
        $u->setRoles(['ROLE_ADMIN']);
        $this->assertSame('user@example.com', $u->getUserIdentifier());
        $roles = $u->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);

        $u->setPassword('secret');
        $this->assertSame('secret', $u->getPassword());
    }
}
