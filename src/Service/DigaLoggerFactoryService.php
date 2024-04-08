<?php 

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Service;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DigaLoggerFactoryService {

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService, string $logFile)
    {
        
        if($systemConfigService->get('DigaShopwareCacheHelper.config.loggingOutput') === 'shopwarelog') {
            $this->logger = $logger;
        } else {
            //instantiate new Logger instance
            $this->logger = new Logger('file_logger');
            $this->logger->pushHandler(new RotatingFileHandler($logFile, 0, Level::Debug));
        }
    }

        /**
         * dynamic method call, so we do not need to override all methods of logger
         * 
         * @param string $name name of called method
         * @param array $arguments array of passed arguments to method
         * 
         * @see https://www.php.net/manual/de/language.oop5.overloading.php#object.call
         */
    public function __call($name, $arguments)
    {
        $message = $arguments[0]; //message string
        $context = $arguments[1] ?? []; //context for logger method, could be null, so defaults to []

        if(method_exists($this->logger, $name)) {
            $this->logger->{$name}(
                $message, 
                $context
            );
        }
    }

    /**
     * special case since log() needs an additional parameter: $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->logger->log($level, $message, $context);
    }
}