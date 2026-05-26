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

namespace MultiFlexi\Ui\CredentialType;

/**
 * Stormware Pohoda mServer credential UI form with live connection test.
 *
 * Uses mServer\Client (vitexsoftware/pohoda-connector) so that the credential
 * check exercises exactly the same code path the applications will use.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class MServer extends \MultiFlexi\Ui\CredentialFormHelperPrototype
{
    public function finalize(): void
    {
        $urlField      = $this->credential->getFields()->getFieldByCode('POHODA_URL');
        $icoField      = $this->credential->getFields()->getFieldByCode('POHODA_ICO');
        $usernameField = $this->credential->getFields()->getFieldByCode('POHODA_USERNAME');
        $passwordField = $this->credential->getFields()->getFieldByCode('POHODA_PASSWORD');
        $timeoutField  = $this->credential->getFields()->getFieldByCode('POHODA_TIMEOUT');
        $compressField = $this->credential->getFields()->getFieldByCode('POHODA_COMPRESS');

        $url      = $urlField      ? $urlField->getValue()      : '';
        $ico      = $icoField      ? $icoField->getValue()      : '';
        $username = $usernameField ? $usernameField->getValue() : '';
        $password = $passwordField ? $passwordField->getValue() : '';
        $timeout  = $timeoutField  ? (int) $timeoutField->getValue() : 30;
        $compress = $compressField ? filter_var($compressField->getValue(), \FILTER_VALIDATE_BOOLEAN) : true;

        if (empty($url) || empty($username) || empty($password)) {
            $missing = [];

            if (empty($url)) {
                $missing[] = 'POHODA_URL';
            }

            if (empty($username)) {
                $missing[] = 'POHODA_USERNAME';
            }

            if (empty($password)) {
                $missing[] = 'POHODA_PASSWORD';
            }

            $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                _('Required fields not set: %s'),
                implode(', ', $missing),
            )));
            parent::finalize();

            return;
        }

        $infoPanel = new \Ease\TWB4\Panel(_('mServer Connection'), 'default');
        $infoList  = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Endpoint'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(
            new \Ease\Html\SpanTag(rtrim($url, '/').'/status', ['class' => 'font-monospace']),
            ['class' => 'col-sm-8'],
        ));

        $infoList->addItem(new \Ease\Html\DtTag(_('Username'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($username, ['class' => 'col-sm-8']));

        if (!empty($ico)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Organization Number'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($ico, ['class' => 'col-sm-8']));
        }

        $infoList->addItem(new \Ease\Html\DtTag(_('Timeout'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(sprintf(_('%d s'), $timeout ?: 30), ['class' => 'col-sm-8']));

        $infoList->addItem(new \Ease\Html\DtTag(_('Compression'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($compress ? _('enabled') : _('disabled'), ['class' => 'col-sm-8']));

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        $result = self::testConnection($url, $username, $password, $ico, $timeout, $compress);

        if ($result['success']) {
            $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                _('mServer connection to %s successful'),
                $url,
            )));

            if (!empty($result['info'])) {
                $processing = (int) ($result['raw']['processing'] ?? 0);
                $status     = $result['raw']['status'] ?? '';
                $panelType  = ($processing > 0 || $status === 'busy') ? 'warning' : 'info';

                if ($panelType === 'warning') {
                    $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                        _('mServer is busy (processing: %d) — server may be frozen or under heavy load'),
                        $processing,
                    )));
                }

                $serverPanel = new \Ease\TWB4\Panel(_('Server Status'), $panelType);
                $serverList  = new \Ease\Html\DlTag(null, ['class' => 'row']);

                foreach ($result['info'] as $key => $value) {
                    $serverList->addItem(new \Ease\Html\DtTag($key, ['class' => 'col-sm-4']));
                    $serverList->addItem(new \Ease\Html\DdTag($value, ['class' => 'col-sm-8']));
                }

                $serverPanel->addItem($serverList);
                $this->addItem($serverPanel);
            }
        } else {
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('mServer connection to %s failed: %s'),
                $url,
                $result['message'],
            )));
        }

        parent::finalize();
    }

    /**
     * Test mServer connectivity using vitexsoftware/pohoda-connector (mServer\Client).
     *
     * This ensures the credential check exercises exactly the same authentication
     * and transport path that applications use via the connector library.
     *
     * @return array{success: bool, message: string, info: array<string, string>, raw: array<string, string>}
     */
    private static function testConnection(
        string $url,
        string $username,
        string $password,
        string $ico,
        int $timeout,
        bool $compress,
    ): array {
        $client = new \mServer\Client(null, [
            'url'      => $url,
            'user'     => $username,
            'password' => $password,
            'ico'      => $ico ?: '0',
            'timeout'  => $timeout > 0 ? $timeout : 30,
            'compress' => $compress,
        ]);

        try {
            $online = $client->isOnline();
        } catch (\mServer\HttpException $e) {
            return [
                'success' => false,
                'message' => self::humanizeConnectorError($client, $e->getMessage()),
                'info'    => [],
                'raw'     => [],
            ];
        }

        if (!$online) {
            return [
                'success' => false,
                'message' => self::humanizeConnectorError($client, ''),
                'info'    => [],
                'raw'     => [],
            ];
        }

        $info = [];
        $raw  = [];
        $xml  = @simplexml_load_string((string) $client->lastCurlResponse);

        if ($xml !== false && $xml->getName() === 'mServer') {
            if (isset($xml->name)) {
                $info[_('Company')]    = (string) $xml->name;
            }

            if (isset($xml->status)) {
                $raw['status']         = (string) $xml->status;
                $info[_('Status')]     = (string) $xml->status;
            }

            if (isset($xml->processing)) {
                $raw['processing']     = (string) $xml->processing;
                $info[_('Processing')] = (string) $xml->processing;
            }

            if (isset($xml->server)) {
                $info[_('Server')]     = (string) $xml->server;
            }

            if (isset($xml->message)) {
                $info[_('Message')]    = (string) $xml->message;
            }
        }

        return [
            'success' => true,
            'message' => '',
            'info'    => $info,
            'raw'     => $raw,
        ];
    }

    /**
     * Translate a connector failure into a human-readable message.
     */
    private static function humanizeConnectorError(\mServer\Client $client, string $exceptionMessage): string
    {
        $httpCode  = $client->lastResponseCode ?? 0;
        $curlError = $client->lastCurlError    ?? '';

        if ($httpCode === 401) {
            return _('Authentication failed — check POHODA_USERNAME and POHODA_PASSWORD');
        }

        $msg = strtolower($curlError ?: $exceptionMessage);

        if (str_contains($msg, 'connection refused') || str_contains($msg, 'couldn\'t connect')) {
            return sprintf(_('Cannot connect to mServer at %s — server may be down or firewall is blocking the port'), $client->url);
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'operation timed out')) {
            return sprintf(_('mServer at %s is not responding (timeout) — server may be frozen'), $client->url);
        }

        if (str_contains($msg, 'could not resolve') || str_contains($msg, 'getaddrinfo')) {
            return sprintf(_('Cannot resolve hostname in %s — check POHODA_URL'), $client->url);
        }

        if (str_contains($msg, 'empty reply') || str_contains($msg, 'got nothing')) {
            return sprintf(_('mServer at %s accepted the connection but sent no response — server may be frozen'), $client->url);
        }

        if ($httpCode > 0) {
            return sprintf(_('mServer at %s returned HTTP %d'), $client->url, $httpCode);
        }

        return sprintf(_('Connection to %s failed'), $client->url);
    }
}
