# Yii2-JsonSyslog

Yii2 log target for sending data to Syslog as a JSON encoded string, useful 
services such as [Logentries](https://logentries.com/).

## Tips

### Only send the JSON content
Make sure that the template you define for Logentries in your rsyslog.conf file 
does not add other content before the ```%msg%``` data (aside from your 
Logentries key). For example, do something like this...

    $template Logentries,"LOGENTRIESKEY %msg%\n"

... NOT like this...

    $template Logentries,"LOGENTRIESKEY %HOSTNAME% %syslogtag%%msg%\n"


### Have the log prefix (if used) return JSON

Example (to be placed into your Yii2 config file's 
```['components']['log']['targets']``` array):

    [
        'class' => 'sil\log\JsonSyslogTarget',
        'levels' => ['error', 'warning'],
        'except' => [
            'yii\web\HttpException:401',
            'yii\web\HttpException:404',
        ],
        'logVars' => [], // Disable logging of _SERVER, _POST, etc.
        'prefix' => function($message) use ($APP_ENV) {
            $prefixData = array(
                'env' => $APP_ENV,
            );
            if (! \Yii::$app->user->isGuest) {
                $prefixData['user'] = \Yii::$app->user->identity->email;
            }
            return \yii\helpers\Json::encode($prefixData);
        },
    ],

## License

This is released under the MIT license (see LICENSE file).