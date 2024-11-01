<?php
interface VeLoggerInterface
{
    public function logMessage($message, $level = 'INFO');
    public function logException(\Exception $exception);
    public function trackMetric($name, $value, $forceFlush = true);
}