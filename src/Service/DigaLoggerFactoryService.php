<?php 

declare(strict_types=1);

namespace DigaShopwareCacheHelper\Service;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Magic methods, called by __call()
 * 
 * @method void info(string $message, array $context = [])
 * @method void warning(string $message, array $context = [])
 * @method void error(string $message, array $context = [])
 * @method void debug(string $message, array $context = [])
 * @method void emergency(string $message, array $context = [])
 * @method void alert(string $message, array $context = [])
 * @method void critical(string $message, array $context = [])
 * @method void notice(string $message, array $context = [])
 */
class DigaLoggerFactoryService {

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, SystemConfigService $systemConfigService, string $logFile)
    {
        
        if($systemConfigService->get('DigaShopwareCacheHelper.config.loggingOutput') === 'shopwarelog') {
            $this->logger = $logger;
        } else {
            //instantiate new Logger instance
            /** @var Logger $logger */
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
         * @return void
         * 
         * @see https://www.php.net/manual/de/language.oop5.overloading.php#object.call
         */
    public function __call(string $name, array $arguments = [])
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
     * @param mixed $level LogLevel
     * @param string $message
     * @return void
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void {
        $this->logger->log($level, $message, $context);
    }
}