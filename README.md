# MultiFlexi mServer Credential Prototype

![MIT License](https://img.shields.io/badge/License-MIT-yellow.svg)

This repository contains the credential prototype for connecting MultiFlexi to Stormware Pohoda mServer API.

## Overview

This credential prototype defines the configuration fields required to connect to the Pohoda mServer API, including support for a secondary account for year-end (December/January) data entry.

## Fields

- **POHODA_ICO**: Organization Number for Pohoda (required)
- **POHODA_URL**: URL of the mServer API (required)
- **POHODA_USERNAME**: Username for the mServer API (required)
- **POHODA_PASSWORD**: Password for the mServer API (required)
- **POHODA_SECONDARY_USERNAME**: Secondary account username for writing December data in January (optional)
- **POHODA_SECONDARY_PASSWORD**: Secondary account password for writing December data in January (optional)

## Year-End Data Entry Mechanism

If data for December is being entered in January, the system will use the secondary account credentials (if provided) to write data into the previous year, provided the secondary account has the necessary permissions.

## Usage

1. Place the `mserver.credprototype.json` file in your MultiFlexi credential prototypes directory.
2. Configure the required fields in the MultiFlexi UI or via configuration management.
3. For organizations that need to write December data in January, provide the secondary account credentials with permissions for the previous year.


## License

This project is licensed under the MIT License. See the [LICENSE](./LICENSE) file for details.

## Author

Vítězslav Dvořák <info@vitexsoftware.cz>

For more information, visit [https://multiflexi.eu/](https://multiflexi.eu/)
