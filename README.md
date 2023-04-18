# com.graydigitalgroup.historicalmemberships

![Screenshot](/images/screenshot.png)

This extension allows for pulling up historical membership records for reporting means. It requires that the CiviCRM logging be enabled so that the table gets populate. The reporting allows for filtering by a given date which will then go back a year from the date provided to look for all membership log entries and locate the last record for each comtact/membership. It also allows for filtering based on membership type and membership status rules.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.4+
* CiviCRM 5.59

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl com.graydigitalgroup.historicalmemberships@https://github.com/graydigitalgroup/com.graydigitalgroup.historicalmemberships/archive/master.zip
```
or
```bash
cd <extension-dir>
cv dl com.graydigitalgroup.historicalmemberships@https://lab.civicrm.org/extensions/com.graydigitalgroup.historicalmemberships/-/archive/main/com.graydigitalgroup.historicalmemberships-main.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/graydigitalgroup/com.graydigitalgroup.historicalmemberships.git
cv en historicalmemberships
```
or
```bash
git clone https://lab.civicrm.org/extensions/com.graydigitalgroup.historicalmemberships.git
cv en historicalmemberships
```

## Getting Started

Once the extension is installed, you will locate this new report under the CiviCRM -> Search -> Custom Searches.

## Known Issues

See [Issues Tracker](https://github.com/graydigitalgroup/com.graydigitalgroup.historicalmemberships/issues) for more information.
