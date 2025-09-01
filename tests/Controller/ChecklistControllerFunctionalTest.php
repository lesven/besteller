<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ChecklistControllerFunctionalTest extends KernelTestCase
{
    public function testChecklistRoutesAreRegistered(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        $collection = $router->getRouteCollection();

        $show = $collection->get('checklist_show');
        $this->assertNotNull($show, 'Route "checklist_show" should exist');
        $this->assertSame('/checklist/{id}', $show->getPath());
    // ID soll numerisch sein (requirements aus routes.yaml)
    $this->assertSame('\d+', $show->getRequirement('id'));

        $submit = $collection->get('checklist_submit');
        $this->assertNotNull($submit, 'Route "checklist_submit" should exist');
        $this->assertSame('/checklist/{id}/submit', $submit->getPath());
    // Submit-Route darf nur per POST angesprochen werden
    $this->assertSame(['POST'], $submit->getMethods());

        $form = $collection->get('checklist_form');
        $this->assertNotNull($form, 'Route "checklist_form" should exist');
        $this->assertSame('/form', $form->getPath());

        $selection = $collection->get('checklist_selection');
        $this->assertNotNull($selection, 'Route "checklist_selection" should exist');
        $this->assertSame('/auswahl', $selection->getPath());
    }
}
