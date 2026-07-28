<?php

$GLOBALS['TL_LANG']['MOD']['sensorcollector_settings'] = ['Sensorcollector-Einstellungen', 'IQBox- und Heizstab-Zugangsdaten verwalten'];

$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['general_legend'] = 'Allgemein';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['ampere_legend'] = 'Ampere.IQ-Cloud';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstab_legend'] = 'Heizstabzugriff';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstab_local_legend'] = 'Heizstab lokal';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstab_cloud_legend'] = 'my-PV-Cloud';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberry_api_legend'] = 'Raspberry-Status-API';
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['title'] = ['Bezeichnung', 'Bezeichnung dieses Einstellungssatzes'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['ampereUsername'] = ['Ampere.IQ-Benutzername', 'E-Mail-Adresse der Ampere.IQ-Anmeldung'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['amperePassword'] = ['Ampere.IQ-Passwort', 'Passwort der Ampere.IQ-Anmeldung'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['ampereRetries'] = ['Maximale Versuche', 'Anzahl der Cloud-Abrufversuche'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['ampereRetryDelay'] = ['Wartezeit', 'Pause zwischen Versuchen in Sekunden'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['lifetimeCacheSeconds'] = ['Lifetime-Cachezeit', 'Gueltigkeit der Lifetime-Werte in Sekunden'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabAccess'] = ['Zugriffsart', 'Legt genau einen Zugriffsweg fuer den Heizstab fest.'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabAccessOptions'] = ['disabled' => 'Deaktiviert', 'local' => 'Lokal', 'cloud' => 'my-PV-Cloud'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabAuthEnabled'] = ['Authentifizierung verwenden', 'Am lokalen Heizstab anmelden und das Sitzungscookie verwenden'];

$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabLocalEnabled'] = ['Lokalen Heizstab aktivieren', 'Lokalen Zugriff auf den Heizstab verwenden'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabUrl'] = ['Lokale Heizstab-URL', 'Zum Beispiel https://192.168.178.68'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabLoginPath'] = ['Loginpfad', 'Standard: /auth.jsn'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabPassword'] = ['Heizstab-Passwort', 'Passwort fuer den lokalen Zugriff'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabPasswordField'] = ['Passwortfeld', 'Standard: pw'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabCookieFile'] = ['Cookie-Datei', 'Cookie-Datei innerhalb des installierten ContaoHab-Bundles'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabInsecureTls'] = ['Unsicheres TLS erlauben', 'Nur fuer lokale Geraete mit selbst signiertem Zertifikat'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabCloudEnabled'] = ['my-PV-Cloud aktivieren', 'Cloudzugriff statt lokaler URL verwenden'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabCloudBaseUrl'] = ['my-PV-Cloud-URL', 'Basis-URL der API'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabCloudSerial'] = ['Seriennummer', 'Seriennummer des my-PV-Geraets'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['heizstabCloudApiToken'] = ['API-Token', 'Bearer-Token der my-PV-Cloud'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberryApiEnabled'] = ['Raspberry-API aktivieren', 'Statuswerte ueber die HTTP-API des Raspberry lesen'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberryApiBaseUrl'] = ['Raspberry-Basis-URL', 'Zum Beispiel http://192.168.178.49'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberryApiToken'] = ['Raspberry-API-Token', 'Wird als X-COH-TOKEN an den Raspberry gesendet'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberryApiTimeout'] = ['HTTP-Timeout', 'Maximale Wartezeit pro Anfrage in Sekunden'];
$GLOBALS['TL_LANG']['tl_coh_sensorcollector_settings']['raspberryApiCacheSeconds'] = ['Raspberry-Cachezeit', 'Innerhalb dieser Zeit wird eine bereits geladene Statusantwort wiederverwendet'];
