<?php
namespace Sil\JsonSyslog;

use yii\helpers\Json;
use yii\log\Logger;

/**
 * Log target for sending data to Syslog as a JSON encoded string.
 */
class JsonSyslogTarget extends \yii\log\SyslogTarget
{
    /**
     * rsyslog has severity codes fo
     * emerg 0, alert 1, crit 2, error 3, warning 4, notice 5, info 6, debug 7
     * LEVEL_INFO produces too many log entries so adding LEVEL_NOTICE allows for
     * a different option.
     */
    const LEVEL_NOTICE = 0x03;

    /**
     * @var array syslog levels
     */
    private $_syslogLevels = [
        Logger::LEVEL_TRACE => LOG_DEBUG,
        Logger::LEVEL_PROFILE_BEGIN => LOG_DEBUG,
        Logger::LEVEL_PROFILE_END => LOG_DEBUG,
        Logger::LEVEL_INFO => LOG_INFO,
        self::LEVEL_NOTICE => LOG_NOTICE,
        Logger::LEVEL_WARNING => LOG_WARNING,
        Logger::LEVEL_ERROR => LOG_ERR,
    ];

    /**
     * If the prefix is a JSON string with key-value data, extract it as an
     * associative array. Otherwise return null.
     * 
     * @param mixed $prefix The raw prefix string.
     * @return null|array
     */
    private function extractPrefixKeyValueData($prefix)
    {
        $result = null;
        
        // If it has key-value data, as evidenced by the raw prefix string
        // being a JSON object (not JSON array), use it.
        if (substr($prefix, 0, 1) === '{') {
            if ($this->isJsonString($prefix)) {
                $result = Json::decode($prefix);
            }
        }
        
        return $result;
    }

    /**
     * Extract the message content data into a more suitable format for
     * JSON-encoding for the log.
     *
     * @param mixed $messageContent The message content, which could be a
     *     string, array, exception, or other data type.
     * @return mixed The extracted data.
     */
    private function extractMessageContentData($messageContent)
    {
        $result = null;
        
        if ($messageContent instanceof \Exception) {
            
            // Handle log messages that are exceptions a little more
            // intelligently.
            // 
            // NOTE: In our limited testing, this is never used. Apparently
            //       something is converting the exceptions to strings despite
            //       the statement at
            //       http://www.yiiframework.com/doc-2.0/yii-log-logger.html#$messages-detail
            //       that the data could be an exception instance.
            //
            $result = array(
                'code' => $messageContent->getCode(),
                'exception' => $messageContent->getMessage(),
            );
            
            if ($messageContent instanceof \yii\web\HttpException) {
                $result['statusCode'] = $messageContent->statusCode;
            }
        } elseif ($this->isMultilineString($messageContent)) {
            
            // Split multiline strings (such as a stack trace) into an array
            // for easier reading in the log.
            $result = explode("\n", $messageContent);
            
        } else {
            
            // Use anything else as-is.
            $result = $messageContent;
        }
        
        return $result;
    }
    
    /**
     * Returns the text display of the specified level.
     * @param integer $level the message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
     * @return string the text display of the level
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            self::LEVEL_NOTICE => 'notice',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'trace',
            Logger::LEVEL_PROFILE_BEGIN => 'profile begin',
            Logger::LEVEL_PROFILE_END => 'profile end',
        ];

        $LevelName = $levels[$level];
        echo "getLevelName  is $LevelName\n";

        return isset($levels[$level]) ? $levels[$level] : 'unknown';
    }

    /**
     * Writes log messages to syslog
     */
    public function export()
    {
        openlog($this->identity, LOG_ODELAY | LOG_PID, $this->facility);
        foreach ($this->messages as $message) {
            syslog($this->_syslogLevels[$message[1]], $this->formatMessage($message));
        }
        closelog();
    }

    /**
     * @inheritdoc
     */
    public function formatMessage($loggerMessage)
    {
        // Retrieve the relevant pieces of data from the logger message data.
        list($messageContent, $level, $category) = $loggerMessage;

        // Begin assembling the data that we will JSON-encode for the log.
        $logData = array();

        // If the prefix is already a JSON string, decode it (to avoid
        // double-encoding it below).
        $prefix = $this->getMessagePrefix($loggerMessage);
        $prefixData = $this->extractPrefixKeyValueData($prefix);
        
        // Only include the prefix data and/or raw prefix if there was content.
        if ($prefixData) {
            foreach ($prefixData as $key => $value) {
                $logData[$key] = $value;
            }
        } elseif ($prefix) {
            $logData['prefix'] = $prefix;
        }
        
        $logData['level'] = $this->getLevelName($level);
        $logData['category'] = $category;
        $logData['message'] = $this->extractMessageContentData($messageContent);
        
        // Format the data as a JSON string and return it.
        return Json::encode($logData);
    }
    
    /**
     * Determine whether the given value is a string that parses as valid JSON.
     * 
     * @param string $string The value to check.
     * @return boolean
     */
    private function isJsonString($string)
    {
        if (! is_string($string)) {
            return false;
        }
        
        $firstChar = substr($string, 0, 1);
        
        // If it starts the way a JSON object or array would, and parsing it as
        // JSON returns no errors, consider it a JSON string.
        if (($firstChar === '{') || ($firstChar === '[')) {
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        }
        
        return false;
    }
    
    /**
     * Determine whether the given data is a string that contains at least one
     * line feed character ("\n").
     * 
     * @param mixed $data The data to check.
     * @return boolean
     */
    private function isMultilineString($data)
    {
        if (! is_string($data)) {
            return false;
        } else {
            return (strpos($data, "\n") !== false);
        }
    }
}
