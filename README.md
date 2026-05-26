# MultiFlexi mServer Credential Prototype

![multiflexi-mserver](multiflexi-mserver.svg?raw=true)

![MIT License](https://img.shields.io/badge/License-MIT-yellow.svg)

This repository contains the credential prototype for connecting MultiFlexi to Stormware Pohoda mServer API.

## Packages

| Package | Contents |
|---|---|
| `multiflexi-mserver` | Credential prototype JSON, SVG logo, `CredentialProtoType\MServer` PHP class |
| `multiflexi-mserver-ui` | `Ui\CredentialType\MServer` PHP class — web form with live connection test |

## Fields

| Keyword | Type | Required | Description |
|---|---|---|---|
| `POHODA_ICO` | string | yes | Organization Number (IČO) |
| `POHODA_URL` | string | yes | mServer API base URL, e.g. `http://pohoda:40000` |
| `POHODA_USERNAME` | string | yes | mServer API username |
| `POHODA_PASSWORD` | password | yes | mServer API password |
| `POHODA_SECONDARY_USERNAME` | string | no | Secondary account username for December→January data entry |
| `POHODA_SECONDARY_PASSWORD` | password | no | Secondary account password for December→January data entry |

## Smart Credential Prototype (UI)

When `multiflexi-mserver-ui` is installed, the credential form performs a live connection test against `{POHODA_URL}/status` using HTTP Basic Auth. On success it displays:

- **Company** — company name registered in Pohoda
- **Status** — server state (`idle` / `busy`)
- **Processing** — number of requests currently being processed
- **Server** — mServer self-reported URL

Authentication failures and network errors are shown as inline alerts.

## Year-End Data Entry

If data for December is being entered in January, provide the secondary account credentials. That account must have write permissions for the previous fiscal year in Pohoda.

## Installation

```sh
apt install multiflexi-mserver          # core fields + JSON prototype
apt install multiflexi-mserver-ui       # web form with connection check
```

The postinst script registers the prototype automatically:

```sh
multiflexi-cli credential-prototype:import-json \
  --file /usr/lib/multiflexi-mserver/multiflexi/mserver.credprototype.json
```

## License

This project is licensed under the MIT License. See the [LICENSE](./LICENSE) file for details.

## Author

Vítězslav Dvořák <info@vitexsoftware.cz>

For more information, visit [https://multiflexi.eu/](https://multiflexi.eu/)
