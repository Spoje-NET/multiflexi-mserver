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
        $fields    = $this->credential->getFields();
        $url       = (string) ($fields->getFieldByCode('POHODA_URL')?->getValue()      ?? '');
        $ico       = (string) ($fields->getFieldByCode('POHODA_ICO')?->getValue()      ?? '');
        $username  = (string) ($fields->getFieldByCode('POHODA_USERNAME')?->getValue() ?? '');
        $timeout   = (int)    ($fields->getFieldByCode('POHODA_TIMEOUT')?->getValue()  ?? 30);
        $compress  = filter_var($fields->getFieldByCode('POHODA_COMPRESS')?->getValue() ?? true, \FILTER_VALIDATE_BOOLEAN);

        $infoPanel = new \Ease\TWB4\Panel(_('mServer Connection'), 'default');
        $infoList  = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Endpoint'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(
            new \Ease\Html\SpanTag(rtrim($url, '/').'/status', ['class' => 'font-monospace']),
            ['class' => 'col-sm-8'],
        ));

        $infoList->addItem(new \Ease\Html\DtTag(_('Username'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($username, ['class' => 'col-sm-8']));

        if ($ico !== '') {
            $infoList->addItem(new \Ease\Html\DtTag(_('Organization Number'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($ico, ['class' => 'col-sm-8']));
        }

        $infoList->addItem(new \Ease\Html\DtTag(_('Timeout'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(sprintf(_('%d s'), $timeout ?: 30), ['class' => 'col-sm-8']));

        $infoList->addItem(new \Ease\Html\DtTag(_('Compression'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($compress ? _('enabled') : _('disabled'), ['class' => 'col-sm-8']));

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        /** @var \MultiFlexi\CredentialProtoType\MServer $prototype */
        $prototype = $this->credential->getCredentialType()->getPrototype();
        $result    = $prototype->checkAvailability();

        $styleMap = [
            \MultiFlexi\CredentialState::Available->value     => 'success',
            \MultiFlexi\CredentialState::Degraded->value      => 'warning',
            \MultiFlexi\CredentialState::Unavailable->value   => 'danger',
            \MultiFlexi\CredentialState::Misconfigured->value => 'danger',
            \MultiFlexi\CredentialState::Unknown->value       => 'info',
        ];
        $style = $styleMap[$result->state->value] ?? 'info';

        $alertText = $result->message !== ''
            ? $result->message
            : sprintf(_('mServer connection to %s successful'), $url);
        $this->addItem(new \Ease\TWB4\Alert($style, $alertText));

        if ($result->details) {
            $serverPanel = new \Ease\TWB4\Panel(_('Server Status'), $style);
            $serverList  = new \Ease\Html\DlTag(null, ['class' => 'row']);

            foreach ($result->details as $key => $value) {
                $serverList->addItem(new \Ease\Html\DtTag((string) $key, ['class' => 'col-sm-4']));
                $serverList->addItem(new \Ease\Html\DdTag((string) $value, ['class' => 'col-sm-8']));
            }

            $serverPanel->addItem($serverList);
            $this->addItem($serverPanel);
        }

        parent::finalize();
    }
}
