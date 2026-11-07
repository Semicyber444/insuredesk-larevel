<?php

namespace App\Livewire;

use App\Livewire\Concerns\CompareBasket;
use App\Models\CoverageType;
use App\Models\Insurer;
use App\Models\VehicleModel;
use App\Services\SearchService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('ค้นหาประกัน')]
class SearchPage extends Component
{
    use CompareBasket;

    public string $query = '';

    public array $insuranceTypes = [];

    public array $repairTypes = [];

    public array $insurerIds = [];

    public string $vehicleBrand = '';

    public string $vehicleModelId = '';

    public string $carAge = '';

    public string $usageType = '';

    public string $registrationPlate = '';

    public string $minPremium = '';

    public string $maxPremium = '';

    public bool $includeUnknownPrice = false;

    public string $minSumInsured = '';

    public string $maxSumInsured = '';

    public array $requiredCoverageCodes = [];

    #[Url(except: 'premium_asc')]
    public string $sortBy = 'premium_asc';

    #[Url(except: 1)]
    public int $page = 1;

    public function mount(): void
    {
        $this->authorize('canSearch');
    }

    /** Any filter change starts again from the first page. */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1;
        }
        if ($property === 'vehicleBrand' && $this->vehicleModelId !== ''
            && VehicleModel::whereKey($this->vehicleModelId)->value('brand') !== $this->vehicleBrand && $this->vehicleBrand !== '') {
            $this->vehicleModelId = '';
        }
    }

    public function toggle(string $list, string $value): void
    {
        abort_unless(in_array($list, ['insuranceTypes', 'repairTypes', 'insurerIds', 'requiredCoverageCodes'], true), 400);
        $this->{$list} = in_array($value, $this->{$list}, true)
            ? array_values(array_diff($this->{$list}, [$value]))
            : [...$this->{$list}, $value];
        $this->page = 1;
    }

    public function resetFilters(): void
    {
        $this->reset(array_diff(array_keys($this->all()), ['sortBy']));
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** The filter form as SearchService expects it: blank inputs mean "not set", numbers parsed. */
    private function filters(): array
    {
        $number = fn (string $v) => is_numeric($v) ? (float) $v : null;
        $strings = fn (array $values) => array_values(array_filter($values, 'is_string'));

        return array_filter([
            'query' => mb_substr($this->query, 0, 255),
            'insuranceTypes' => $strings($this->insuranceTypes),
            'repairTypes' => $strings($this->repairTypes),
            'insurerIds' => $strings($this->insurerIds),
            'vehicleBrand' => $this->vehicleBrand,
            'vehicleModelId' => $this->vehicleModelId,
            'carAge' => $number($this->carAge),
            'usageType' => $this->usageType,
            'registrationPlate' => in_array($this->registrationPlate, ['bangkok', 'metro', 'upcountry', 'bangkok_only_strict'], true) ? $this->registrationPlate : null,
            'minPremium' => $number($this->minPremium),
            'maxPremium' => $number($this->maxPremium),
            'includeUnknownPrice' => $this->includeUnknownPrice,
            'minSumInsured' => $number($this->minSumInsured),
            'maxSumInsured' => $number($this->maxSumInsured),
            'requiredCoverageCodes' => $strings($this->requiredCoverageCodes),
            'sortBy' => $this->sortBy,
            'page' => $this->page,
            'pageSize' => 20,
        ], fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false);
    }

    public function render()
    {
        $pool = SearchService::buildItems();
        $results = SearchService::search($this->filters(), $pool);
        $poolById = collect($pool)->keyBy('id');
        // Ids no longer searchable (unpublished, archived) drop out of the basket.
        $basket = array_values(array_filter($this->basket(), fn ($id) => isset($poolById[$id])));
        if ($basket !== $this->basket()) {
            $this->setBasket($basket);
        }

        $vehicleModels = VehicleModel::orderBy('created_at')->orderBy('id')->get();
        $coverageTypes = CoverageType::orderBy('sort_order')->orderBy('id')->get();
        $activeCount = count(array_filter([
            trim($this->query), $this->insuranceTypes, $this->repairTypes, $this->insurerIds, $this->vehicleBrand,
            $this->vehicleModelId, $this->carAge !== '', $this->usageType, $this->registrationPlate,
            $this->minPremium !== '' || $this->maxPremium !== '', $this->includeUnknownPrice,
            $this->minSumInsured !== '' || $this->maxSumInsured !== '', $this->requiredCoverageCodes,
        ]));

        return view('livewire.search-page', [
            'results' => $results,
            'basket' => $basket,
            'basketItems' => array_map(fn ($id) => $poolById[$id], $basket),
            'insurers' => Insurer::where('is_active', true)->orderByDesc('created_at')->orderBy('id')->get(),
            'brands' => $vehicleModels->pluck('brand')->unique()->values(),
            'visibleModels' => $this->vehicleBrand ? $vehicleModels->where('brand', $this->vehicleBrand) : $vehicleModels,
            'coverageCategories' => $coverageTypes->groupBy('category'),
            'activeCount' => $activeCount,
        ]);
    }
}
