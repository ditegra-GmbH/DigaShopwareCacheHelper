<?php 

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DigaLoggerFactoryService implements LoggerInterface{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService)
    {
        if($systemConfigService->get('DigaShopwareCacheHelper.config.loggingOutput') === 'stderr') {
            $this->logger = $logger;
        } else {
            //instantiate new Logger instance
            $this->logger = new Logger('file_logger');
            $this->logger->pushHandler(new StreamHandler('./test.log', Level::Debug));
        }
    }

    public function emergency(string|\Stringable $message, array $context = []):void {
        $this->logger->emergency($message, $context);
    }

   
    public function alert(string|\Stringable $message, array $context = []): void {
        $this->logger->alert($message, $context);
    }

   
    public function critical(string|\Stringable $message, array $context = []): void {
        $this->logger->critical($message, $context);
    }

  
    public function error(string|\Stringable $message, array $context = []): void {
        $this->logger->error($message, $context);
    }

   
    public function warning(string|\Stringable $message, array $context = []): void {
        $this->logger->warning($message, $context);
    }

  
    public function notice(string|\Stringable $message, array $context = []): void {
        $this->logger->notice($message, $context);
    }

    
    public function info(string|\Stringable $message, array $context = []): void {
        $this->logger->info($message, $context);
    }

    
    public function debug(string|\Stringable $message, array $context = []): void {
        $this->logger->debug($message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->logger->log($level, $message, $context);
    }
}