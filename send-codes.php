<?php

require_once __DIR__ . '/bootstrap.php';

use CViniciusSDias\RecargaTvExpress\Exception\CodeNotFoundException;
use CViniciusSDias\RecargaTvExpress\Service\SalesFinder;
use CViniciusSDias\RecargaTvExpress\Service\SerialCodeSender;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** @var ContainerInterface $container */
$container = require_once __DIR__ . '/config/dependencies.php';

try {
    /** @var SalesFinder $salesFinder */
    $salesFinder = $container->get(SalesFinder::class);
    /** @var SerialCodeSender $codeSender */
    $codeSender = $container->get(SerialCodeSender::class);

    $sales = $salesFinder->findSales();

    foreach ($sales as $sale) {
        $codeSender->sendCodeTo($sale);
    }
} catch (\Throwable $error) {
    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);
    $context = [
        'mensagem' => $error->getMessage(),
        'erro' => $error
    ];
    if ($error instanceof CodeNotFoundException) {
        $sale = $error->sale();

        $context['sale'] = [
            'product' => $sale->product,
            'costumerEmail' => $sale->costumerEmail,
        ];
    }

    $logger->error('Erro ao enviar códigos.', $context);
}
