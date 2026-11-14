<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingService;
use App\Support\TrackingCarrierGuesser;
use Illuminate\Console\Command;

class CheckShipmentTracking extends Command
{
    protected $signature = 'tracking:check {numbers* : One or more tracking numbers}';

    protected $description = 'Show what 17TRACK and the carrier API answer for tracking numbers (raw), to see why a status stays blank';

    public function handle(ShipmentTrackingService $tracking): int
    {
        $this->line('17TRACK key set: '.($tracking->has17Track() ? 'yes' : 'NO'));
        $this->line('USPS credentials: '.($tracking->hasUsps() ? 'yes' : 'no').', USPS calls allowed now: '.($tracking->uspsCanRequest() ? 'yes' : 'no (not authorized / quota)'));
        $this->line('UPS: '.($tracking->hasUps() ? 'yes' : 'no').', FedEx: '.($tracking->hasFedex() ? 'yes' : 'no').', GOFO: '.($tracking->hasGofo() ? 'yes' : 'no'));

        $numbers = array_values(array_filter(array_map('trim', (array) $this->argument('numbers'))));
        foreach ($numbers as $number) {
            $this->newLine();
            $this->info($number.'  (guessed carrier: '.(TrackingCarrierGuesser::labelFromNumber($number) ?? 'unknown').')');

            if ($tracking->has17Track()) {
                $item = ['number' => $number];
                $reg = $tracking->raw17Track('/register', [$item]);
                $this->line('  17TRACK /register     : '.json_encode($reg['response']).($reg['error'] !== '' ? '  ERROR: '.$reg['error'] : ''));
                $info = $tracking->raw17Track('/gettrackinfo', [$item]);
                $this->line('  17TRACK /gettrackinfo : '.mb_substr((string) json_encode($info['response']), 0, 1200).($info['error'] !== '' ? '  ERROR: '.$info['error'] : ''));
            }

            $result = $tracking->track([['number' => $number, 'carrier' => null]]);
            $this->line('  track() result        : '.json_encode($result));
        }

        return self::SUCCESS;
    }
}
