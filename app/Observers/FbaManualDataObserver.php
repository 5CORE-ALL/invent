<?php

namespace App\Observers;

use App\Models\FbaManualData;
use App\Services\FbaManualDataService;

class FbaManualDataObserver
{
    protected $fbaManualDataService;

    public function __construct(FbaManualDataService $fbaManualDataService)
    {
        $this->fbaManualDataService = $fbaManualDataService;
    }

    /**
     * Handle the FbaManualData "created" event.
     */
    public function created(FbaManualData $fbaManualData): void
    {
        $this->updateShipCalculation($fbaManualData);
    }

    /**
     * Handle the FbaManualData "updated" event.
     */
    public function updated(FbaManualData $fbaManualData): void
    {
        $this->updateShipCalculation($fbaManualData);
    }

    /**
     * Update FBA Ship Calculation automatically
     */
    protected function updateShipCalculation(FbaManualData $fbaManualData): void
    {
        $data = $fbaManualData->data ?? [];
        
        $this->fbaManualDataService->calculateFbaShipCalculation(
            $fbaManualData->sku,
            $data['fba_fee_manual'] ?? 0,
            $data['send_cost'] ?? 0,
            $data['in_charges'] ?? 0,
            true // Save to DB
        );
    }

    /**
     * Handle the FbaManualData "deleted" event.
     */
    public function deleted(FbaManualData $fbaManualData): void
    {
        // Optionally delete the calculation record
        \App\Models\FbaShipCalculation::where('sku', $fbaManualData->sku)->delete();
    }

    /**
     * Handle the FbaManualData "restored" event.
     */
    public function restored(FbaManualData $fbaManualData): void
    {
        $this->updateShipCalculation($fbaManualData);
    }

    /**
     * Handle the FbaManualData "force deleted" event.
     */
    public function forceDeleted(FbaManualData $fbaManualData): void
    {
        \App\Models\FbaShipCalculation::where('sku', $fbaManualData->sku)->delete();
    }
}
