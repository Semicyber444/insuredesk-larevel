<?php

namespace App\Livewire;

use App\Livewire\Concerns\CompareBasket;
use App\Services\SearchService;
use App\Support\Format;
use App\Support\QuoteValidity;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('ใบเสนอราคาประกันภัยรถยนต์')]
class ComparePage extends Component
{
    use CompareBasket;

    #[Url]
    public string $ids = '';

    public function mount(): void
    {
        $this->authorize('canCompare');
        $urlIds = $this->urlIds();
        if (count($urlIds) >= 2) {
            $this->setBasket($urlIds);
        }
    }

    private function urlIds(): array
    {
        return array_slice(array_values(array_filter(explode(',', $this->ids), fn ($id) => $id !== '' && strlen($id) <= 64)), 0, 4);
    }

    public function removeOption(string $id)
    {
        $remaining = array_values(array_diff($this->basket(), [$id]));
        $this->setBasket($remaining);

        return $this->redirect(count($remaining) >= 2 ? '/compare?ids='.implode(',', $remaining) : '/search');
    }

    public function clearAll()
    {
        $this->clearCompare();

        return $this->redirect('/search');
    }

    public function render()
    {
        $urlIds = $this->urlIds();
        $target = count($urlIds) >= 2 ? $urlIds : array_slice($this->basket(), 0, 4);
        $data = count($target) >= 2 ? SearchService::comparison($target) : null;
        if (! $data || count($data['options']) < 2) {
            return view('livewire.compare-empty');
        }

        return view('livewire.compare-page', $data + $this->summary($data['options']));
    }

    /** Difference flags and the quotation header, derived from the compared options. */
    private function summary(array $options): array
    {
        $distinct = fn (callable $fn) => count(array_unique(array_map(fn ($o) => (string) $fn($o), $options))) > 1;
        $hasPlateRestriction = collect($options)->contains(fn ($o) => ($o['registrationZone'] ?? 'all') !== 'all');
        $scopeText = fn ($o) => match ($o['scopeType']) {
            'specific_model' => trim('เฉพาะ '.($o['vehicleBrand'] ?? '').' '.($o['vehicleModel'] ?? '')),
            'unknown' => 'ยังไม่ระบุขอบเขตรุ่นรถ',
            default => null,
        };

        $usages = array_unique(array_map(fn ($o) => $o['usageType'] ?: 'private', $options));
        $usage = count($usages) > 1 ? 'แตกต่างกันตามแต่ละแผน' : match ($options[0]['usageType']) {
            'commercial' => 'รถยนต์เชิงพาณิชย์ / รับจ้าง',
            'all' => 'รองรับทุกรูปแบบการใช้งาน',
            default => 'รถยนต์ใช้งานส่วนบุคคล',
        };
        if ($hasPlateRestriction) {
            $zones = array_unique(array_map(fn ($o) => $o['registrationZone'] ?: 'all', $options));
            $usage .= count($zones) === 1 ? ' ('.Format::registrationZone($options[0]['registrationZone'], true).')' : ' (จำกัดป้ายบางแผน)';
        }
        $scopes = array_unique(array_map(fn ($o) => $scopeText($o) ?? 'รองรับทุกรุ่นภายใต้เงื่อนไข', $options));
        $ages = array_unique(array_map(fn ($o) => 'อายุ '.Format::carAgeRange($o['minCarAge'], $o['maxCarAge']), $options));
        $inspections = array_unique(array_map(fn ($o) => (bool) $o['inspectionRequired'], $options));

        $validity = QuoteValidity::for(today()->toDateString(),
            collect($options)->flatMap(fn ($o) => [$o['priceValidTo'], $o['versionEffectiveTo']])->all());

        return [
            'diff' => [
                'sum_insured' => $distinct(fn ($o) => $o['sumInsuredMax'] ?? $o['sumInsuredMin'] ?? 'UNKNOWN'),
                'repair_type' => $distinct(fn ($o) => $o['repairType']),
                'deductible' => $distinct(fn ($o) => $o['deductibleAmount'] ?? 0),
                'driver_conditions' => $distinct(fn ($o) => $o['driverConditions'] ?: 'ไม่จำกัดชื่อผู้ขับขี่'),
                'compulsory' => $distinct(fn ($o) => $o['compulsoryIncluded']),
                'vehicle_conditions' => $distinct(fn ($o) => "{$o['scopeType']}:{$o['vehicleModelId']}:{$o['minCarAge']}-{$o['maxCarAge']}:".($o['registrationZone'] ?? 'all')),
            ],
            'hasPlateRestriction' => $hasPlateRestriction,
            'scopeText' => $scopeText,
            'usageSummary' => $usage,
            'scopeSummary' => count($scopes) === 1 ? reset($scopes) : 'ตามเงื่อนไขแต่ละแผน (ดูในตาราง)',
            'ageSummary' => count($ages) === 1 ? reset($ages) : 'แตกต่างกันตามแต่ละแผน',
            'inspectionSummary' => count($inspections) > 1 ? 'ขึ้นอยู่กับแผนที่เลือก' : ($inspections[0] ? 'ต้องตรวจสภาพรถก่อนทำประกัน' : 'ไม่ต้องตรวจสภาพรถ'),
            'validity' => $validity,
            'quotationNo' => 'QT-'.(now()->year + 543).now()->format('md').'-'.substr($options[0]['id'], -4),
        ];
    }
}
