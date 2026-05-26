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

        $url      = $urlField      ? $urlField->getValue()      : '';
        $ico      = $icoField      ? $icoField->getValue()      : '';
        $username = $usernameField ? $usernameField->getValue() : '';
        $password = $passwordField ? $passwordField->getValue() : '';

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

        $statusUrl = rtrim($url, '/').'/status';

        $infoPanel = new \Ease\TWB4\Panel(_('mServer Connection'), 'default');
        $infoList  = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Endpoint'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(
            new \Ease\Html\SpanTag($statusUrl, ['class' => 'font-monospace']),
            ['class' => 'col-sm-8'],
        ));

        $infoList->addItem(new \Ease\Html\DtTag(_('Username'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($username, ['class' => 'col-sm-8']));

        if (!empty($ico)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Organization Number'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($ico, ['class' => 'col-sm-8']));
        }

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        $result = self::testConnection($statusUrl, $username, $password);

        if ($result['success']) {
            $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                _('mServer connection to %s successful'),
                $url,
            )));

            if (!empty($result['info'])) {
                $serverPanel = new \Ease\TWB4\Panel(_('Server Status'), 'info');
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
     * Test mServer /status endpoint with HTTP Basic Auth.
     *
     * @return array{success: bool, message: string, info: array<string, string>}
     */
    private static function testConnection(string $statusUrl, string $username, string $password): array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => 'Authorization: Basic '.base64_encode($username.':'.$password)."\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($statusUrl, false, $context);

        if ($response === false) {
            return [
                'success' => false,
                'message' => error_get_last()['message'] ?? _('Connection failed'),
                'info'    => [],
            ];
        }

        $httpStatus = 0;

        if (isset($http_response_header) && \is_array($http_response_header)) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $httpStatus = (int) $m[1];
            }
        }

        if ($httpStatus === 401) {
            return [
                'success' => false,
                'message' => _('Authentication failed — check POHODA_USERNAME and POHODA_PASSWORD'),
                'info'    => [],
            ];
        }

        if ($httpStatus !== 200) {
            return [
                'success' => false,
                'message' => sprintf(_('Unexpected HTTP status: %d'), $httpStatus),
                'info'    => [],
            ];
        }

        $info = [];

        $xml = @simplexml_load_string($response);

        if ($xml !== false && $xml->getName() === 'mServer') {
            if (isset($xml->name)) {
                $info[_('Company')]    = (string) $xml->name;
            }

            if (isset($xml->status)) {
                $info[_('Status')]     = (string) $xml->status;
            }

            if (isset($xml->processing)) {
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
        ];
    }
}
