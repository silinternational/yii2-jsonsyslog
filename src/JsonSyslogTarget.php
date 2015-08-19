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
        
        $logData['level'] = Logger::getLevelName($level);
        $logData['category'] = $category;
        $logData['message'] = $this->extractMessageContentData($messageContent);
        
        // Format the data as a JSON string and return it.
        return Json::encode($logData);
    }
    
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
