<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SyncShopifyAllChannelDataController extends Controller
{
	public function index()
	{
		return view('shopify.shopify_all_channels');
	}

	public function data(Request $request)
	{
		$rows = $this->fetchRows();
		$result = $this->mapRows($rows);
		$result = $this->prependSummaryRow($result);

		return response()->json($result);
	}

	public function export(Request $request)
	{
		$rows = $this->fetchRows();
		$result = $this->mapRows($rows);
		$result = $this->prependSummaryRow($result);

		$fileName = 'shopify_all_channels_' . now()->format('Y_m_d_His') . '.xlsx';
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$this->populateSheet($sheet, $result);

		return response()->streamDownload(function () use ($spreadsheet) {
			if (ob_get_length()) {
				ob_end_clean();
			}

			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
		}, $fileName, [
			'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		]);
	}

	protected function populateSheet($sheet, array $rows): void
	{
		if (empty($rows)) {
			$sheet->setCellValue('A1', 'No data available');
			return;
		}

		$headers = array_keys($rows[0]);
		$sheet->fromArray($headers, null, 'A1');

		$rowIndex = 2;
		foreach ($rows as $row) {
			$sheet->fromArray(
				array_map([$this, 'formatValueForCell'], array_values($row)),
				null,
				'A' . $rowIndex
			);
			$rowIndex++;
		}
	}

	protected function prependSummaryRow(array $rows): array
	{
		if (empty($rows)) {
			return $rows;
		}

		$summary = [];
		$keys = array_keys($rows[0]);

		foreach ($keys as $key) {
			if ($key === 'Parent') {
				$summary[$key] = 'TOTAL';
				continue;
			}

			if ($key === 'SKU') {
				$summary[$key] = '';
				continue;
			}

			if ($key === 'Shopify_Qty' || str_contains($key, '_L30')) {
				$summary[$key] = collect($rows)->sum(function ($row) use ($key) {
					$value = $row[$key] ?? 0;
					return is_numeric($value) ? (float) $value : 0;
				});
				continue;
			}

			$summary[$key] = '';
		}

		array_unshift($rows, $summary);

		return $rows;
	}

	protected function formatValueForCell($value)
	{
		if (is_bool($value)) {
			return $value ? 'TRUE' : 'FALSE';
		}

		if (is_scalar($value) || $value === null) {
			return $value ?? '';
		}

		return json_encode($value);
	}

	protected function fetchRows(): Collection
	{
		return DB::table('shopify_all_channels_data')
			->select(['sku', 'parent', 'value'])
			->orderBy('parent')
			->orderBy('sku')
			->get();
	}

	protected function mapRows(Collection $rows): array
	{
		$result = [];

		foreach ($rows as $row) {
			$value = is_string($row->value) ? json_decode($row->value, true) : ($row->value ?? []);

			$orders = $value['shopifyorders'] ?? [];
			$orders = is_array($orders) ? $orders : [];
			$getOrders = function (string $key) use ($orders) {
				$m = $orders[$key] ?? [];
				return [
					'o_l30' => (int)($m['l30'] ?? 0),
					'o_l60' => (int)($m['l60'] ?? 0),
					'q_l30' => (int)($m['qty_l30'] ?? 0),
					'q_l60' => (int)($m['qty_l60'] ?? 0),
				];
			};

			$amzO = $getOrders('Amazon');
			$ebayO = $getOrders('eBay');
			$ebay2O = $getOrders('Ebay2');
			$ebay3O = $getOrders('Ebay3');
			$temuO = $getOrders('Temu');
			$walmartO = $getOrders('Walmart');
			$reverbO = $getOrders('Reverb');
			$wayfairO = $getOrders('Wayfair');
			$sheinO = $getOrders('Shein');
			$aliO = $getOrders('AliExpress');
			$mercariO = $getOrders('Mercari');
			$dobaO = $getOrders('doba');
			$bestbuyO = $getOrders('Best Buy USA');
			$macysO = $getOrders("Macy's");

			$result[] = [
				'Parent' => $row->parent,
				'SKU' => $row->sku,
				'Shopify_INV' => $value['shopify']['inv'] ?? 0,
				'Shopify_Qty' => $value['shopify']['qty'] ?? 0,
				
				'Ebay1_L30' => $value['ebay_one']['l30'] ?? 0,
				'Ebay_Q_L30' => $ebayO['q_l30'],
				'Ebay2_L30' => $value['ebay_two']['l30'] ?? 0,
				'Ebay2_Q_L30' => $ebay2O['q_l30'],
				'Ebay3_L30' => $value['ebay_three']['l30'] ?? 0,
				'Ebay3_Q_L30' => $ebay3O['q_l30'],
				'Amazon_L30' => $value['amazon']['l30'] ?? 0,
				'Amazon_Q_L30' => $amzO['q_l30'],
				'Reverb_L30' => $value['reverb']['l30'] ?? 0,
				'Reverb_O_L30' => $reverbO['o_l30'],
				'Reverb_Q_L30' => $reverbO['q_l30'],
				'Macy_L30' => $value['macy']['l30'] ?? 0,
				'Macys_Q_L30' => $macysO['q_l30'],
				'BestBuy_L30' => $value['bestbuy']['l30'] ?? 0,
				'BestBuyUSA_Q_L30' => $bestbuyO['q_l30'],
				'Wayfair_L30' => $value['wayfair']['l30'] ?? 0,
				'Wayfair_Q_L30' => $wayfairO['q_l30'],
				'Walmart_L30' => $value['walmart']['l30'] ?? 0,
				'Walmart_Q_L30' => $walmartO['q_l30'],
				'PLS_L30' => $value['pls']['l30'] ?? 0,
			
				'FBMkt_L30' => $value['fb_marketplace']['l30'] ?? 0,
				'TikTok_L30' => $value['tiktok']['l30'] ?? 0,
				'Temu_L30' => $value['temu']['l30'] ?? 0,
				'Temu_Q_L30' => $temuO['q_l30'],
				'Business5core_L30' => $value['business5core']['l30'] ?? 0,
				'AliExpress_L30' => $value['aliexpress']['l30'] ?? 0,
				
				'AliExpress_Q_L30' => $aliO['q_l30'],
				'Shein_L30' => $value['shein']['l30'] ?? 0,
			
				'Shein_Q_L30' => $sheinO['q_l30'],
				'MercariW_L30' => $value['mercariWship']['l30'] ?? 0,
				'MercariW_O_L30' => $mercariO['o_l30'],
				'MercariW_Q_L30' => $mercariO['q_l30'],
				'MercariWO_L30' => $value['mercariWithoutShip']['l30'] ?? 0,
				'Doba_O_L30' => $dobaO['o_l30'],
				'Doba_Q_L30' => $dobaO['q_l30'],
			];
		}

		return $result;
	}
}
 
