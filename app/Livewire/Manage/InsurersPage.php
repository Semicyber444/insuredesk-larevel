<?php

namespace App\Livewire\Manage;

use App\Exceptions\StoreException;
use App\Models\Insurer;
use App\Services\InsuranceStore;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('บริษัทประกัน')]
class InsurersPage extends Component
{
    public bool $editing = false;

    public ?string $insurerId = null;

    public string $name = '';

    public string $contact = '';

    public string $website = '';

    public bool $isActive = true;

    public string $error = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('canEditDraft');
    }

    public function create(): void
    {
        $this->reset('insurerId', 'name', 'contact', 'website', 'isActive', 'error');
        $this->editing = true;
    }

    public function edit(string $id): void
    {
        $insurer = Insurer::findOrFail($id);
        $this->insurerId = $insurer->id;
        $this->name = $insurer->name;
        $this->contact = $insurer->contact_channels ?? '';
        $this->website = $insurer->website ?? '';
        $this->isActive = $insurer->is_active;
        $this->error = '';
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorize('canEditDraft');
        $this->error = '';
        $validator = validator(
            ['name' => trim($this->name), 'contact_channels' => $this->contact, 'website' => trim($this->website)],
            [
                'name' => ['required', 'string', 'max:255'],
                'contact_channels' => ['nullable', 'string', 'max:1000'],
                // Rendered as a link, so only http(s) may get through (no javascript:).
                'website' => ['nullable', 'string', 'max:2048', 'regex:/^https?:\/\/\S+$/i'],
            ],
            [
                'name.required' => 'กรุณากรอกชื่อบริษัทประกันภัย',
                'name.max' => 'ข้อความยาวเกิน 255 ตัวอักษร',
                'contact_channels.max' => 'ข้อความยาวเกิน 1,000 ตัวอักษร',
                'website.*' => 'รูปแบบ URL ไม่ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)',
            ],
        );
        if ($validator->fails()) {
            $this->error = $validator->errors()->first();

            return;
        }
        try {
            InsuranceStore::saveInsurer($validator->validated() + ['id' => $this->insurerId, 'is_active' => $this->isActive], auth()->user());
            $this->editing = false;
        } catch (StoreException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function delete(string $id): void
    {
        $this->authorize('canEditDraft');
        $name = Insurer::whereKey($id)->value('name');
        $this->notice = InsuranceStore::deleteInsurer($id, auth()->user()) === 'deactivated'
            ? "บริษัท \"{$name}\" มีแพ็กเกจผูกอยู่ ระบบจึงเปลี่ยนสถานะเป็น \"ปิดใช้งาน\" แทนการลบเพื่อรักษาข้อมูลอ้างอิง"
            : "ลบบริษัท \"{$name}\" เรียบร้อยแล้ว";
    }

    public function render()
    {
        return view('livewire.manage.insurers-page', [
            'insurers' => Insurer::orderByDesc('created_at')->orderBy('id')->get(),
        ]);
    }
}
