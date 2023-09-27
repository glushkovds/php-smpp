PHP implementation SMPP v3.4 protocol
=============

[![Build Status](https://travis-ci.org/glushkovds/php-smpp.svg?branch=master)](https://travis-ci.org/glushkovds/php-smpp)
[![Latest Stable Version](https://poser.pugx.org/glushkovds/php-smpp/v/stable)](https://packagist.org/packages/glushkovds/php-smpp)
[![Total Downloads](https://poser.pugx.org/glushkovds/php-smpp/downloads)](https://packagist.org/packages/glushkovds/php-smpp)
[![Latest Unstable Version](https://poser.pugx.org/glushkovds/php-smpp/v/unstable)](https://packagist.org/packages/glushkovds/php-smpp)
[![License](https://poser.pugx.org/glushkovds/php-smpp/license)](https://packagist.org/packages/glushkovds/php-smpp)

Allows send and read SMS and USSD.  

This is a simplified SMPP client lib for sending or receiving smses through [SMPP v3.4](http://www.smsforum.net/SMPP_v3_4_Issue1_2.zip).

In addition to the client, this lib also contains an encoder for converting UTF-8 text to the GSM 03.38 encoding, and a socket wrapper. The socket wrapper provides connection pool, IPv6 and timeout monitoring features on top of PHP's socket extension.

This lib has changed significantly from it's parent.

This lib requires the [sockets](http://www.php.net/manual/en/book.sockets.php) PHP-extension, and is not supported on Windows.

Inheritance
-----

This implementation based on [php-smpp library](https://github.com/agladkov/php-smpp)  

Key differences:
1. Send and listen USSD messages
1. Object oriented way with Pdu, ShortMessage, Sms and other classes
1. PSR-1,4,12 support
1. Requires php7.4+
1. Phpunit auto tests 

Installation
-----

```bash
composer require glushkovds/php-smpp "^0.5"
```

Basic usage example
-----

To send a SMS you can do:

```php
<?php
require_once 'vendor/autoload.php';

$service = new \PhpSmpp\Service\Sender(['smschost.net'], 'login', 'pass');
$smsId = $service->send(79001001010, 'Hello world!', 'Sender');
```

To receive a SMS (or delivery receipt):

```php
<?php
require_once 'vendor/autoload.php';

$service = new \PhpSmpp\Service\Listener(['smschost.net'], 'login', 'pass');
$service->listen(function (\PhpSmpp\Pdu\Sm $sm) {
    var_dump($sm->msgId);
    if ($sm instanceof \PhpSmpp\Pdu\DeliverReceiptSm) {
        var_dump($sm->state);
        var_dump($sm->state == \PhpSmpp\SMPP::STATE_DELIVERED);
        // do some job with delivery receipt
    } else {
        echo 'not receipt';
    }
});
```

To send a USSD you can do:

```php
<?php
require_once 'vendor/autoload.php';

$service = new \PhpSmpp\Service\Sender(['smschost.net'], 'login', 'pass');
$smsId = $service->sendUSSD(79001001010, 'Hello world!', 'Sender', []);
```

To receive a USSD:

```php
require_once 'vendor/autoload.php';

$service = new \PhpSmpp\Service\Listener(['smschost.net'], 'login', 'pass');
$service->listen(function (\PhpSmpp\Pdu\Pdu $pdu) {
    var_dump($pdu->id);
    var_dump($pdu->sequence);
    if ($pdu instanceof \PhpSmpp\Pdu\Ussd) {
        var_dump($pdu->status);
        var_dump($pdu->source->value);
        var_dump($pdu->destination->value);
        var_dump($pdu->message);
        // do some job with ussd
    }
});
```

Perform testing your code with fake transport (also available for Listener):

```php

<?php
require_once 'vendor/autoload.php';

$service = new \PhpSmpp\Service\Sender(['smschost.net'], 'login', 'pass');
$service->client->setTransport(new \PhpSmpp\Transport\FakeTransport());
$smsId = $service->send(79001001010, 'Hello world!', 'Sender');
```


Connection pools
-----
You can specify a list of connections to have the SocketTransport attempt each one in succession or randomly. Also if you give it a hostname with multiple A/AAAA-records it will try each one.
If you want to monitor the DNS lookups, set defaultDebug to true before constructing the transport.

The (configurable) send timeout governs how long it will wait for each server to timeout. It can take a long time to try a long list of servers, depending on the timeout. You can change the timeout both before and after the connection attempts are made.

The transport supports IPv6 and will prefer IPv6 addresses over IPv4 when available. You can modify this feature by setting forceIpv6 or forceIpv4 to force it to only use IPv6 or IPv4.

In addition to the DNS lookups, it will also look for local IPv4 addresses using gethostbyname(), so "localhost" works for IPv4. For IPv6 localhost specify "::1". 


Implementation notes
-----

 - The SUBMIT_MULTI operation of SMPP, which sends a SMS to a list of recipients, is not supported atm. You can easily add it though.
 - The sockets will return false if the timeout is reached on read() (but not readAll or write). 
   You can use this feature to implement an enquire_link policy. If you need to send enquire_link for every 30 seconds of inactivity, 
   set a timeout of 30 seconds, and send the enquire_link command after readSMS() returns false.
 - The examples above assume that the SMSC default datacoding is [GSM 03.38](http://en.wikipedia.org/wiki/GSM_03.38).
 - Remember to activate registered delivery if you want delivery receipts (set to SMPP::REG_DELIVERY_SMSC_BOTH / 0x01).
 - Both the SmppClient and transport components support a debug callback, which defaults to [error_log](http://www.php.net/manual/en/function.error-log.php) . Use this to redirect debug information.
 
F.A.Q.
-----

**I can't send more than 160 chars**  
There are three built-in methods to send Concatenated SMS (csms); CSMS_16BIT_TAGS, CSMS_PAYLOAD, CSMS_8BIT_UDH. CSMS_16BIT_TAGS is the default, if it don't work try another.

**Can it run on windows?**  
Maybe! I think this is no good. But you can try it or even contribute windows supporting feature.  

**Why am I not seeing any debug output?**  
Remember to implement a debug callback for SocketTransport and SmppClient to use. Otherwise they default to [error_log](http://www.php.net/manual/en/function.error-log.php) which may or may not print to screen. 

**Why do I get 'res_nsend() failed' or 'Could not connect to any of the specified hosts' errors?**  
Your provider's DNS server probably has an issue with IPv6 addresses (AAAA records). Try to set ```SocketTransport::$forceIpv4=true;```. You can also try specifying an IP-address (or a list of IPs) instead. Setting ```SocketTransport:$defaultDebug=true;``` before constructing the transport is also useful in resolving connection issues.

**I tried forcing IPv4 and/or specifying an IP-address, but I'm still getting 'Could not connect to any of the specified hosts'?**  
It would be a firewall issue that's preventing your connection, or something else entirely. Make sure debug output is enabled and displayed. If you see something like 'Socket connect to 1.2.3.4:2775 failed; Operation timed out' this means a connection could not be etablished. If this isn't a firewall issue, you might try increasing the connect timeout. The sendTimeout also specifies the connect timeout, call ```$transport->setSendTimeout(10000);``` to set a 10-second timeout.

**Why do I get 'Failed to read reply to command: 0x4', 'Message Length is invalid' or 'Error in optional part' errors?**  
Most likely your SMPP provider doesn't support NULL-terminating the message field. The specs aren't clear on this issue, so there is a toggle. Set ```SmppClient::$sms_null_terminate_octetstrings = false;``` and try again.  

**What does 'Bind Failed' mean?**  
It typically means your SMPP provider rejected your login credentials, ie. your username or password.

**Can I test the client library without a SMPP server?**  
Yes, but not full functionality, by FakeTransport class.  
Also you can try simulators from official SMPP website: https://smpp.org/smpp-testing-development.html

**I have an issue that not mentioned here, what do I do?**  
Please obtain full debug information, and open an issue here on github. Make sure not to include the Send PDU hex-codes of the BindTransmitter call, since it will contain your username and password. Other hex-output is fine, and greatly appeciated. Any PHP Warnings or Notices could also be important. Please include information about what SMPP server you are connecting to, and any specifics.
