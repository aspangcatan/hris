<?php
// TEMPORARY local diagnostic - delete after use.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::capture();
$app->instance('request', $req);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$log = function ($m) { file_put_contents(__DIR__ . '/../storage/logs/dtr_probe.log', date('H:i:s') . " $m\n", FILE_APPEND); };
$log('start engine=' . config('pdf.dtr_engine') . ' xdebug=' . (extension_loaded('xdebug') ? ini_get('xdebug.mode') : 'off') . ' sapi=' . PHP_SAPI);
config(['pdf.dtr_engine' => 'dompdf']); $log('forced dompdf'); Auth::loginUsingId(1116);
$t = microtime(true);
$res = app(App\Http\Controllers\DTRController::class)->printDTR(Illuminate\Http\Request::create('/print_dtr', 'GET', ['userid' => 1116, 'month' => 10, 'year' => 2026]));
$log(sprintf('done status=%s %.1fs', $res->getStatusCode(), microtime(true) - $t));
$res->send();
