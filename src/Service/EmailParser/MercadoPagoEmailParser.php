<?php

namespace CViniciusSDias\RecargaTvExpress\Service\EmailParser;

use CViniciusSDias\RecargaTvExpress\Model\Sale;
use CViniciusSDias\RecargaTvExpress\Model\VO\Email;
use PhpImap\IncomingMail;

class MercadoPagoEmailParser extends EmailParser
{
    protected function parseEmail(IncomingMail $email): ?Sale
    {
        $domDocument = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($email->textHtml);
        $xPath = new \DOMXPath($domDocument);

        $query = $this->isSaleWithTwoCreditCards($xPath)
            ? '/html/body/table/tr/td/table[3]/tr/td/table/tr/td/table[4]/tr/td/table[last()]'
            : '/html/body/table[3]/tr/td/div[2]/p';
        $dataNodes = $xPath->query($query);
        $emailAddress = trim($dataNodes->item($dataNodes->length - 1)->textContent);
        $productNumber = trim(str_replace('Você recebeu um pagamento por P ', '', $email->subject));
        $product = ($productNumber === '2' || $productNumber === '5') ? 'anual' : 'mensal';

        return new Sale(new Email($emailAddress), $product);
    }

    protected function canParse(IncomingMail $email): bool
    {
        $emailIsFromMercadoPago = $email->fromAddress === 'info@mercadopago.com';
        $emailSubjectHasProductType = preg_match('/recebeu um pagamento por P [1-5]/', $email->subject) === 1;

        return $emailIsFromMercadoPago && $emailSubjectHasProductType;
    }

    private function isSaleWithTwoCreditCards(\DOMXPath $xPath): bool
    {
        return $xPath->query('/html/body/table')->length === 1;
    }
}
