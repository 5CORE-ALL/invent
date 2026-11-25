<?php

namespace App\Console\Commands;

use App\Services\ShipmentTrackingService;
use App\Support\TrackingCarrierGuesser;
use Illuminate\Console\Command;

class CheckShipmentTracking extends Command
{
    protected $signature = 'tracking:check {numbers* : One or more tracking numbers}';

    protected $description = 'Show what the carrier API (USPS / UPS / FedEx / GOFO) answers for tracking numbers, to see why a status stays blank';

    public function handle(ShipmentTrackingService $tracking): int
    {
        $this->line('USPS credentials: '.($tracking->hasUsps() ? 'yes' : 'no').', USPS calls allowed now: '.($tracking->uspsCanRequest() ? 'yes' : 'no (not authorized / quota)'));
        $this->line('UPS: '.($tracking->hasUps() ? 'yes' : 'no').', FedEx: '.($tracking->hasFedex() ? 'yes' : 'no').', GOFO: '.($tracking->hasGofo() ? 'yes' : 'no'));

        $numbers = array_values(array_filter(array_map('trim', (array) $this->argument('numbers'))));
        foreach ($numbers as $number) {
            $this->newLine();
            $this->info($number.'  (guessed carrier: '.(TrackingCarrierGuesser::labelFromNumber($number) ?? 'unknown').')');

            $result = $tracking->track([['number' => $number, 'carrier' => null]]);
            $this->line('  track() result        : '.json_encode($result));
        }

        return self::SUCCESS;
    }
}
