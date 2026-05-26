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
class MServer extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface
{
    public static string $logo = 'mServer.svg';

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

        $compressField = new \MultiFlexi\ConfigField('POHODA_COMPRESS', 'boolean', _('Enable Compression'), _('Enable gzip compression for mServer API requests'));
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

    public static function name(): string
    {
        return _('Stormware Pohoda mServer');
    }

    public static function description(): string
    {
        return _('Credential type for connecting to Stormware Pohoda mServer API');
    }

    public static function uuid(): string
    {
        return '6ba7b814-9dad-11d1-80b4-00c04fd430c8';
    }

    #[\Override]
    public static function logo(): string
    {
        return self::$logo;
    }
}
