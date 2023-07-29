<?php

use Illuminate\Support\Facades\Log as WHMCSLog;
use WHMCS\Application\Support\Facades\AdminLang as FacadesAdminLang;
use WHMCS\Application\Support\Facades\App as WHMCSApp;
use WHMCS\Application\Support\Facades\Di as WHMCSDI;
use WHMCS\Application\Support\Facades\Storage as WHMCSStorage;

class DI extends WHMCSDI {}
class App extends WHMCSApp {}
class AdminLang extends FacadesAdminLang {}
class Storage extends WHMCSStorage {}

/**
 * @mixin Monolog\Logger
 */
class Log extends WHMCSLog {}
