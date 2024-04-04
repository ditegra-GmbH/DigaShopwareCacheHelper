<?php 

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DigaLoggerFactoryService {

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService, string $logFile)
    {
        
        if($systemConfigService->get('DigaShopwareCacheHelper.config.loggingOutput') === 'stderr') {
            $this->logger = $logger;
        } else {
            //instantiate new Logger instance
            $this->logger = new Logger('file_logger');
            $this->logger->pushHandler(new StreamHandler($logFile, Level::Debug));
        }
    }
    //dynamic method call
    public function __call($name, $arguments)
    {
        $this->logger->{$name}($arguments[0], $arguments[1] ?? []);
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->logger->log($level, $message, $context);
    }
}