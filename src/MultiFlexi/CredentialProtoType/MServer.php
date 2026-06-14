<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\CredentialProtoType;

/**
 * Stormware Pohoda mServer API credential prototype.
 *
 * @no-named-arguments
 */
class MServer extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface, \MultiFlexi\checkableCredentialInterface
{
    public static string $logo = 'MServer.svg';

    public function __construct()
    {
        parent::__construct();

        $icoField = new \MultiFlexi\ConfigField('POHODA_ICO', 'string', _('Organization Number'), _('Organization Number for Pohoda'));
        $icoField->setHint('123245678')->setValue('');

        $urlField = new \MultiFlexi\ConfigField('POHODA_URL', 'string', _('mServer API Endpoint'), _('URL of the mServer API'));
        $urlField->setHint('http://pohoda:40000')->setValue('');

        $usernameField = new \MultiFlexi\ConfigField('POHODA_USERNAME', 'string', _('mServer API Username'), _('Username for the mServer API'));
        $usernameField->setHint('winstrom')->setValue('');

        $passwordField = new \MultiFlexi\ConfigField('POHODA_PASSWORD', 'password', _('mServer API Password'), _('Password for the mServer API'));
        $passwordField->setHint('pohoda')->setValue('');

        $timeoutField = new \MultiFlexi\ConfigField('POHODA_TIMEOUT', 'integer', _('Connection Timeout'), _('Timeout for mServer API requests in seconds'));
        $timeoutField->setHint('30')->setValue('30');

        $compressField = new \MultiFlexi\ConfigField('POHODA_COMPRESS', 'bool', _('Enable Compression'), _('Enable gzip compression for mServer API requests'));
        $compressField->setHint('true')->setValue('true');

        $secUsernameField = new \MultiFlexi\ConfigField('POHODA_SECONDARY_USERNAME', 'string', _('Secondary Account Username'), _('Username for writing December data in January (previous year)'));
        $secUsernameField->setHint('winstrom2')->setValue('');

        $secPasswordField = new \MultiFlexi\ConfigField('POHODA_SECONDARY_PASSWORD', 'password', _('Secondary Account Password'), _('Password for writing December data in January (previous year)'));
        $secPasswordField->setHint('pohoda2')->setValue('');

        $this->configFieldsInternal->addField($icoField);
        $this->configFieldsInternal->addField($urlField);
        $this->configFieldsInternal->addField($usernameField);
        $this->configFieldsInternal->addField($passwordField);
        $this->configFieldsInternal->addField($timeoutField);
        $this->configFieldsInternal->addField($compressField);
        $this->configFieldsInternal->addField($secUsernameField);
        $this->configFieldsInternal->addField($secPasswordField);
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        foreach ($this->configFieldsInternal->getFields() as $field) {
            $this->configFieldsProvided->addField($field);
        }

        return $loaded;
    }

    #[\Override]
    public function prepareConfigForm(): void
    {
    }

    public function name(): string
    {
        return _('Stormware Pohoda mServer');
    }

    public function description(): string
    {
        return _('Credential type for connecting to Stormware Pohoda mServer API');
    }

    public function uuid(): string
    {
        return '853f1d90-7dcd-412b-ac86-47a867dbb844';
    }

    #[\Override]
    public function logo(): string
    {
        return self::$logo;
    }

    #[\Override]
    public function checkAvailability(): \MultiFlexi\CredentialCheckResult
    {
        $f = fn (string $c) => (string) ($this->configFieldsInternal->getFieldByCode($c)?->getValue() ?? '');
        $url  = $f('POHODA_URL');
        $user = $f('POHODA_USERNAME');
        $pass = $f('POHODA_PASSWORD');

        $missing = array_keys(array_filter([
            'POHODA_URL'      => $url  === '',
            'POHODA_USERNAME' => $user === '',
            'POHODA_PASSWORD' => $pass === '',
        ]));

        if ($missing) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                sprintf(_('Required fields not set: %s'), implode(', ', $missing)),
                time(),
            );
        }

        $client = new \mServer\Client(null, [
            'url'      => $url,
            'user'     => $user,
            'password' => $pass,
            'ico'      => $f('POHODA_ICO') ?: '0',
            'timeout'  => (int) ($f('POHODA_TIMEOUT') ?: 30) ?: 30,
            'compress' => filter_var($f('POHODA_COMPRESS'), \FILTER_VALIDATE_BOOLEAN),
        ]);

        try {
            $online = $client->isOnline();
        } catch (\mServer\HttpException $e) {
            return self::fromConnectorError($client, $e->getMessage());
        }

        if (!$online) {
            return self::fromConnectorError($client, '');
        }

        $details = self::parseStatus($client);
        $busy    = (int) ($details['processing'] ?? 0) > 0 || ($details['status'] ?? '') === 'busy';

        return new \MultiFlexi\CredentialCheckResult(
            $busy ? \MultiFlexi\CredentialState::Degraded : \MultiFlexi\CredentialState::Available,
            $busy ? _('mServer is busy — may be frozen or under heavy load') : '',
            time(),
            120,
            $details,
        );
    }

    private static function fromConnectorError(\mServer\Client $client, string $exceptionMessage): \MultiFlexi\CredentialCheckResult
    {
        $httpCode  = $client->lastResponseCode ?? 0;
        $curlError = $client->lastCurlError    ?? '';

        if ($httpCode === 401) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                _('Authentication failed — check POHODA_USERNAME and POHODA_PASSWORD'),
                time(),
            );
        }

        $msg = strtolower($curlError ?: $exceptionMessage);

        if (str_contains($msg, 'connection refused') || str_contains($msg, 'couldn\'t connect')) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('Cannot connect to mServer at %s — server may be down or firewall is blocking the port'), $client->url),
                time(),
                60,
            );
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'operation timed out')) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('mServer at %s is not responding (timeout) — server may be frozen'), $client->url),
                time(),
                60,
            );
        }

        if (str_contains($msg, 'could not resolve') || str_contains($msg, 'getaddrinfo')) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                sprintf(_('Cannot resolve hostname in %s — check POHODA_URL'), $client->url),
                time(),
            );
        }

        if (str_contains($msg, 'empty reply') || str_contains($msg, 'got nothing')) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('mServer at %s accepted the connection but sent no response — server may be frozen'), $client->url),
                time(),
                60,
            );
        }

        if ($httpCode > 0) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('mServer at %s returned HTTP %d'), $client->url, $httpCode),
                time(),
                60,
            );
        }

        return new \MultiFlexi\CredentialCheckResult(
            \MultiFlexi\CredentialState::Unavailable,
            sprintf(_('Connection to %s failed'), $client->url),
            time(),
            60,
        );
    }

    /**
     * @return array<string,string>
     */
    private static function parseStatus(\mServer\Client $client): array
    {
        $details = [];
        $xml     = @simplexml_load_string((string) $client->lastCurlResponse);

        if ($xml !== false && $xml->getName() === 'mServer') {
            if (isset($xml->name)) {
                $details[_('Company')] = (string) $xml->name;
            }

            if (isset($xml->status)) {
                $details['status']     = (string) $xml->status;
                $details[_('Status')] = (string) $xml->status;
            }

            if (isset($xml->processing)) {
                $details['processing']     = (string) $xml->processing;
                $details[_('Processing')] = (string) $xml->processing;
            }

            if (isset($xml->server)) {
                $details[_('Server')] = (string) $xml->server;
            }

            if (isset($xml->message)) {
                $details[_('Message')] = (string) $xml->message;
            }
        }

        return $details;
    }
}
