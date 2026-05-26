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
                $panelType  = 'info';
                $processing = (int) ($result['raw']['processing'] ?? 0);
                $status     = $result['raw']['status'] ?? '';

                if ($processing > 0 || $status === 'busy') {
                    $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                        _('mServer is busy (processing: %d) — server may be frozen or under heavy load'),
                        $processing,
                    )));
                    $panelType = 'warning';
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
     * Translate a curl error code into a human-readable message.
     */
    private static function humanizeCurlError(int $errno, string $url): string
    {
        return match ($errno) {
            \CURLE_COULDNT_CONNECT    => sprintf(_('Cannot connect to mServer at %s — server may be down or firewall is blocking the port'), $url),
            \CURLE_OPERATION_TIMEDOUT => sprintf(_('mServer at %s is not responding (timeout) — server may be frozen'), $url),
            \CURLE_COULDNT_RESOLVE_HOST => sprintf(_('Cannot resolve hostname in %s — check POHODA_URL'), $url),
            \CURLE_GOT_NOTHING        => sprintf(_('mServer at %s accepted the connection but sent no response — server may be frozen'), $url),
            default                   => sprintf(_('Connection to %s failed'), $url),
        };
    }

    /**
     * Test mServer /status endpoint with HTTP Basic Auth.
     *
     * Uses curl so that both connect timeout and total request timeout are
     * enforced — file_get_contents only has a socket read timeout and can
     * block for the full OS TCP handshake time when mServer is unreachable.
     *
     * @return array{success: bool, message: string, info: array<string, string>, raw: array<string, string>}
     */
    private static function testConnection(string $statusUrl, string $username, string $password): array
    {
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPAUTH       => \CURLAUTH_BASIC,
            \CURLOPT_USERPWD        => $username.':'.$password,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_TIMEOUT        => 10,
            \CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlErrno  = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $curlErrno !== 0) {
            return [
                'success' => false,
                'message' => self::humanizeCurlError($curlErrno, $statusUrl),
                'info'    => [],
            ];
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
        $raw  = [];

        $xml = @simplexml_load_string($response);

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
}
