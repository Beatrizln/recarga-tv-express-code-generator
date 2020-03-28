<?php

namespace CViniciusSDias\RecargaTvExpress\Tests\Integration\Service;

use CViniciusSDias\RecargaTvExpress\Model\Sale;
use CViniciusSDias\RecargaTvExpress\Service\EmailParser\{EmailParser, MercadoPagoEmailParser, PayPalEmailParser};
use CViniciusSDias\RecargaTvExpress\Service\EmailSalesReader;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;
use PHPUnit\Framework\TestCase;

/**
 * Test class for integration between SalesFinder and EmailFinders
 */
class SalesFinderTest extends TestCase
{
    public function testSalesFinderShouldReturnEmptyArrayWhenNoParseableEmailIsFound()
    {
        $incomingMail = $this->createStub(IncomingMail::class);
        $incomingMail->fromAddress = 'info@mercadopago.com';
        $invalidEmailSubject = 'Você recebeu um pagamento por Combo MFC + TVE anual';
        $incomingMail->subject = $invalidEmailSubject;

        $mailbox = $this->createStub(Mailbox::class);
        $mailbox->method('searchMailbox')->willReturn([1]);
        $mailbox->method('getMail')->willReturn($incomingMail);

        $salesFinder = new EmailSalesReader($mailbox, $this->emailParser());

        $sales = $salesFinder->findSales();

        $this->assertEmpty($sales);
    }

    public function testSalesFinderShouldOnlyReturnSalesFromParseableEmails()
    {
        // arrange

        // valid mercado pago e-mail
        $incomingMailMock1 = $this->createStub(IncomingMail::class);
        $incomingMailMock1->subject = 'Você recebeu um pagamento por P 2';
        $incomingMailMock1->fromAddress = 'info@mercadopago.com';
        $incomingMailMock1->method('__get')
            ->willReturn(file_get_contents(__DIR__ . '/../../data/email-without-phone.html'));

        // valid paypal e-mail
        $incomingMailMock2 = $this->createStub(IncomingMail::class);
        $incomingMailMock2->subject = 'Item nº 12345';
        $incomingMailMock2->fromAddress = 'service@paypal.com.br';
        $incomingMailMock2->method('__get')
            ->willReturn(file_get_contents(__DIR__ . '/../../data/email-with-payment-from-paypal.html'));

        // invalid e-mail
        $incomingMailMock3 = $this->createStub(IncomingMail::class);
        $incomingMailMock3->fromAddress = 'wrong-email@example.com';
        $incomingMailMock3->subject = 'Você recebeu um pagamento por Combo MFC + TVE anual';

        $mailbox = $this->createStub(Mailbox::class);
        $mailbox->method('searchMailbox')->willReturn([1, 2, 3]);
        $mailbox->method('getMail')->willReturnOnConsecutiveCalls(
            $incomingMailMock1,
            $incomingMailMock2,
            $incomingMailMock3,
        );

        $salesFinder = new EmailSalesReader($mailbox, $this->emailParser());

        // act
        $sales = $salesFinder->findSales();

        // assert
        $this->assertCount(2, $sales);
        $this->assertContainsOnlyInstancesOf(Sale::class, $sales);
        $this->assertSame('anual', $sales[0]->product);
        $this->assertSame('anual', $sales[1]->product);
        $this->assertEquals('email@example.com', $sales[0]->costumerEmail);
        $this->assertEquals('email@example.com', $sales[1]->costumerEmail);
    }

    private function emailParser(): MercadoPagoEmailParser
    {
        $nullParser = new class extends EmailParser
        {
            protected function parseEmail(IncomingMail $email): ?Sale
            {
                return null;
            }

            protected function canParse(IncomingMail $email): bool
            {
                return true;
            }
        };

        return new MercadoPagoEmailParser(new PayPalEmailParser($nullParser));
    }
}
